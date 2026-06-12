<?php

namespace Tests\Unit\Services;

use App\Enums\BoxStatus;
use App\Enums\CustomerType;
use App\Enums\ShopOrderStatus;
use App\Enums\ShopStockAudience;
use App\Enums\SpargelOrderStatus;
use App\Models\Address;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ProductStockInflowSchedule;
use App\Models\ShopOrder;
use App\Models\SpargelOrder;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InventoryService::class);
    }

    public function test_available_on_date_respects_freshness_window_inclusive(): void
    {
        $product = Product::factory()->create();
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => '2026-05-25',
            'quantity' => 1000.0,
            'freshness_window_days' => 3,
        ]);

        $this->assertEqualsWithDelta(1000.0, $this->service->availableOnDate($product, Carbon::parse('2026-05-25')), 0.001);
        $this->assertEqualsWithDelta(1000.0, $this->service->availableOnDate($product, Carbon::parse('2026-05-26')), 0.001);
        $this->assertEqualsWithDelta(1000.0, $this->service->availableOnDate($product, Carbon::parse('2026-05-27')), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->service->availableOnDate($product, Carbon::parse('2026-05-28')), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->service->availableOnDate($product, Carbon::parse('2026-05-24')), 0.001);
    }

    /** Create a non-cancelled shop order committing $qty of $product for $date. */
    private function commitShopOrder(Product $product, float $qty, string $date): void
    {
        $order = ShopOrder::create([
            'name' => 'C', 'email' => 'c@example.com', 'phone' => null,
            'fulfillment_mode' => 'pickup', 'pickup_location' => 'karl_kunger',
            'expected_delivery_date' => $date,
            'items_total_eur' => 0, 'delivery_fee_eur' => 0, 'total_amount_eur' => 0,
            'payment_method' => 'in_person', 'status' => ShopOrderStatus::Paid,
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'quantity' => $qty, 'unit' => 'piece',
            'unit_price_snapshot_eur' => 1, 'line_total_eur' => $qty,
            'vat_rate_snapshot_percent' => 7, 'vat_amount_eur' => 0,
        ]);
    }

    public function test_batch_depletion_carries_across_the_whole_freshness_window(): void
    {
        // Reported bug: inflow 100, freshness 4 (Aug 12–15); a sale of 10 on the
        // 12th must reduce ALL four days to 90, not just the 12th.
        $product = Product::factory()->create();
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => '2026-08-12',
            'quantity' => 100,
            'freshness_window_days' => 4, // active 12–15
        ]);

        $this->commitShopOrder($product, 10, '2026-08-12');

        foreach (['2026-08-12', '2026-08-13', '2026-08-14', '2026-08-15'] as $iso) {
            $this->assertEqualsWithDelta(90.0, $this->service->availableOnDate($product, Carbon::parse($iso)), 0.001, "Day {$iso}");
        }
    }

    public function test_batch_depletion_from_a_mid_window_day_also_reduces_all_days(): void
    {
        $product = Product::factory()->create();
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => '2026-08-12',
            'quantity' => 100,
            'freshness_window_days' => 4,
        ]);

        $this->commitShopOrder($product, 10, '2026-08-14'); // sell from the 14th

        foreach (['2026-08-12', '2026-08-13', '2026-08-14', '2026-08-15'] as $iso) {
            $this->assertEqualsWithDelta(90.0, $this->service->availableOnDate($product, Carbon::parse($iso)), 0.001, "Day {$iso}");
        }
    }

    public function test_fefo_drains_the_soonest_expiring_batch_first(): void
    {
        // Batch A: 100, active 12–15 (expires 15). Batch B: 100, active 14–17.
        $product = Product::factory()->create();
        ProductStockInflow::create(['product_id' => $product->id, 'received_on' => '2026-08-12', 'quantity' => 100, 'freshness_window_days' => 4]);
        ProductStockInflow::create(['product_id' => $product->id, 'received_on' => '2026-08-14', 'quantity' => 100, 'freshness_window_days' => 4]);

        // Commit 100 on the 14th, where both batches are live. FEFO → drain A.
        $this->commitShopOrder($product, 100, '2026-08-14');

        // Day 12: only A is live and it's fully drained → 0.
        $this->assertEqualsWithDelta(0.0, $this->service->availableOnDate($product, Carbon::parse('2026-08-12')), 0.001);
        // Day 14: A drained (0) + B intact (100) = 100.
        $this->assertEqualsWithDelta(100.0, $this->service->availableOnDate($product, Carbon::parse('2026-08-14')), 0.001);
        // Day 16: only B is live and untouched → 100.
        $this->assertEqualsWithDelta(100.0, $this->service->availableOnDate($product, Carbon::parse('2026-08-16')), 0.001);
    }

    public function test_fefo_preserves_feasibility_for_short_window_batch(): void
    {
        // Batch A: 10, active 12 only. Batch B: 10, active 12–13.
        $product = Product::factory()->create();
        ProductStockInflow::create(['product_id' => $product->id, 'received_on' => '2026-08-12', 'quantity' => 10, 'freshness_window_days' => 1]);
        ProductStockInflow::create(['product_id' => $product->id, 'received_on' => '2026-08-12', 'quantity' => 10, 'freshness_window_days' => 2]);

        // Demand 10 on the 12th must take expiring A, leaving B for the 13th.
        $this->commitShopOrder($product, 10, '2026-08-12');

        // Day 12: A drained (0) + B intact (10) = 10.
        $this->assertEqualsWithDelta(10.0, $this->service->availableOnDate($product, Carbon::parse('2026-08-12')), 0.001);
        // Day 13: only B is live, still 10 (A correctly wasn't consumed by day-13 demand).
        $this->assertEqualsWithDelta(10.0, $this->service->availableOnDate($product, Carbon::parse('2026-08-13')), 0.001);
    }

    public function test_over_commitment_exhausts_batch_across_window(): void
    {
        $product = Product::factory()->create();
        ProductStockInflow::create(['product_id' => $product->id, 'received_on' => '2026-08-12', 'quantity' => 100, 'freshness_window_days' => 4]);

        $this->commitShopOrder($product, 150, '2026-08-12'); // oversell

        foreach (['2026-08-12', '2026-08-13', '2026-08-14', '2026-08-15'] as $iso) {
            $this->assertEqualsWithDelta(0.0, $this->service->availableOnDate($product, Carbon::parse($iso)), 0.001, "Day {$iso}");
        }
    }

    public function test_available_subtracts_spargel_outflow_for_configured_white_product(): void
    {
        $product = Product::factory()->create();
        config(['spargel.white_product_id' => $product->id]);

        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => '2026-05-25',
            'quantity' => 5000,
            'freshness_window_days' => 1,
        ]);

        SpargelOrder::create([
            'name' => 'A',
            'email' => 'a@example.com',
            'phone' => null,
            'quantity_white_grams' => 1500,
            'quantity_green_grams' => 0,
            'unit_price_eur' => 21.50,
            'fulfillment_mode' => 'pickup',
            'delivery_fee_eur' => 0,
            'total_amount_eur' => 32.25,
            'payment_method' => 'online',
            'expected_delivery_date' => '2026-05-25',
            'status' => SpargelOrderStatus::PendingPayment,
        ]);

        $this->assertEqualsWithDelta(
            5000 - 1500,
            $this->service->availableOnDate($product, Carbon::parse('2026-05-25')),
            0.001,
        );
    }

    public function test_committed_excludes_cancelled_and_refunded_spargel_orders(): void
    {
        $product = Product::factory()->create();
        config(['spargel.green_product_id' => $product->id]);

        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => '2026-05-25',
            'quantity' => 5000,
            'freshness_window_days' => 1,
        ]);

        foreach ([SpargelOrderStatus::Cancelled, SpargelOrderStatus::Refunded] as $status) {
            SpargelOrder::create([
                'name' => 'A',
                'email' => 'a@example.com',
                'phone' => null,
                'quantity_white_grams' => 0,
                'quantity_green_grams' => 1000,
                'unit_price_eur' => 21.50,
                'fulfillment_mode' => 'pickup',
                'delivery_fee_eur' => 0,
                'total_amount_eur' => 21.50,
                'payment_method' => 'online',
                'expected_delivery_date' => '2026-05-25',
                'status' => $status,
            ]);
        }

        $this->assertEqualsWithDelta(0.0, $this->service->committedOnDate($product, Carbon::parse('2026-05-25')), 0.001);
    }

    public function test_box_outflow_routes_by_resolved_delivery_date(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);

        $wedSub = Subscription::factory()->withPaymentToken()->create([
            'user_id' => $user->id,
            'delivery_weekday' => 3, // Wed
        ]);

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $wedSub->id,
            'week_start_date' => '2026-05-25', // Mon → Wed 2026-05-27
            'status' => BoxStatus::Locked,
            'estimated_value' => 0,
        ]);

        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $this->assertEqualsWithDelta(0.0, $this->service->committedOnDate($product, Carbon::parse('2026-05-25')), 0.001);
        $this->assertEqualsWithDelta(2.0, $this->service->committedOnDate($product, Carbon::parse('2026-05-27')), 0.001);
    }

    public function test_availability_matrix_matches_per_day_available_on_date(): void
    {
        // Two products, overlapping inflows + every kind of commitment, so the
        // bulk matrix must agree cell-for-cell with the trusted single-point
        // availableOnDate() across the whole window.
        $white = Product::factory()->create();
        $other = Product::factory()->create();
        config(['spargel.white_product_id' => $white->id]);

        // Inflows with multi-day freshness windows that overlap.
        ProductStockInflow::create([
            'product_id' => $white->id,
            'received_on' => '2026-05-25',
            'quantity' => 5000,
            'freshness_window_days' => 3, // active 25–27
        ]);
        ProductStockInflow::create([
            'product_id' => $white->id,
            'received_on' => '2026-05-27',
            'quantity' => 2000,
            'freshness_window_days' => 2, // active 27–28 (stacks on 27)
        ]);
        ProductStockInflow::create([
            'product_id' => $other->id,
            'received_on' => '2026-05-26',
            'quantity' => 800,
            'freshness_window_days' => 4, // active 26–29
        ]);

        // Spargel commitment against the white product.
        SpargelOrder::create([
            'name' => 'A', 'email' => 'a@example.com', 'phone' => null,
            'quantity_white_grams' => 1500, 'quantity_green_grams' => 0,
            'unit_price_eur' => 21.50, 'fulfillment_mode' => 'pickup',
            'delivery_fee_eur' => 0, 'total_amount_eur' => 32.25,
            'payment_method' => 'online', 'expected_delivery_date' => '2026-05-26',
            'status' => SpargelOrderStatus::Paid,
        ]);
        // Cancelled spargel order must NOT count.
        SpargelOrder::create([
            'name' => 'B', 'email' => 'b@example.com', 'phone' => null,
            'quantity_white_grams' => 9999, 'quantity_green_grams' => 0,
            'unit_price_eur' => 21.50, 'fulfillment_mode' => 'pickup',
            'delivery_fee_eur' => 0, 'total_amount_eur' => 32.25,
            'payment_method' => 'online', 'expected_delivery_date' => '2026-05-26',
            'status' => SpargelOrderStatus::Cancelled,
        ]);

        // Box commitment routed by resolved delivery date (Wed 2026-05-27).
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        $sub = Subscription::factory()->withPaymentToken()->create([
            'user_id' => $user->id,
            'delivery_weekday' => 3,
        ]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'week_start_date' => '2026-05-25',
            'status' => BoxStatus::Locked,
            'estimated_value' => 0,
        ]);
        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $other->id,
            'quantity' => 300,
            'unit' => 'g',
            'is_extra' => false,
        ]);

        $from = Carbon::parse('2026-05-24');
        $to = Carbon::parse('2026-05-30');
        $products = Product::query()->whereIn('id', [$white->id, $other->id])->get();

        $matrix = $this->service->availabilityMatrix($products, $from, $to);

        foreach ($products as $product) {
            $cursor = $from->copy();
            while ($cursor->lte($to)) {
                $iso = $cursor->toDateString();
                $expected = $this->service->availableOnDate($product, $cursor);
                $actual = (float) ($matrix[$product->id][$iso] ?? 0.0);

                $this->assertEqualsWithDelta(
                    $expected,
                    $actual,
                    0.001,
                    "Mismatch for product {$product->id} on {$iso}: expected {$expected}, got {$actual}",
                );
                $cursor->addDay();
            }
        }
    }

    public function test_materialize_window_is_idempotent(): void
    {
        $product = Product::factory()->create();

        // Wednesday-recurring schedule, indefinite, starts 2026-05-27 (Wed).
        $schedule = ProductStockInflowSchedule::create([
            'product_id' => $product->id,
            'weekday' => 3,
            'planned_quantity' => 5000,
            'freshness_window_days' => 1,
            'starts_on' => '2026-05-27',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-05-20'));
        try {
            $horizon = Carbon::now()->addWeeks(4); // 2026-06-17 → 4 Wednesdays
            $first = $this->service->materializeWindow($horizon);
            $second = $this->service->materializeWindow($horizon);

            $this->assertSame(4, $first);
            $this->assertSame(0, $second);

            $rows = ProductStockInflow::query()
                ->where('source_schedule_id', $schedule->id)
                ->orderBy('received_on')
                ->pluck('received_on')
                ->map(fn ($d) => $d->toDateString())
                ->all();

            $this->assertSame(['2026-05-27', '2026-06-03', '2026-06-10', '2026-06-17'], $rows);
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_cancel_from_preserves_manual_override_quantity(): void
    {
        $product = Product::factory()->create();
        $schedule = ProductStockInflowSchedule::create([
            'product_id' => $product->id,
            'weekday' => 3,
            'planned_quantity' => 5000,
            'freshness_window_days' => 1,
            'starts_on' => '2026-05-27',
        ]);

        // Materialize a few weeks.
        $this->service->materializeSchedule($schedule, Carbon::parse('2026-06-17'));

        // Admin manually overrides one row.
        ProductStockInflow::query()
            ->where('source_schedule_id', $schedule->id)
            ->whereDate('received_on', '2026-06-10')
            ->update(['quantity' => 1234]);

        $this->service->cancelScheduleFrom($schedule->id, Carbon::parse('2026-06-03'));

        // The 2026-06-03 + 2026-06-17 rows (qty still = 5000) deleted;
        // the manually edited 2026-06-10 row stays.
        $remaining = ProductStockInflow::query()
            ->where('source_schedule_id', $schedule->id)
            ->orderBy('received_on')
            ->pluck('received_on')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        $this->assertContains('2026-05-27', $remaining);
        $this->assertContains('2026-06-10', $remaining);
        $this->assertNotContains('2026-06-03', $remaining);
        $this->assertNotContains('2026-06-17', $remaining);
    }

    public function test_b2b_only_inflow_invisible_to_consumer_visible_to_business_trade(): void
    {
        $product = Product::factory()->create();
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => '2026-06-10',
            'quantity' => 25,
            'freshness_window_days' => 1,
            'b2b_only' => true,
        ]);

        $date = Carbon::parse('2026-06-10');

        $this->assertEqualsWithDelta(
            0.0,
            $this->service->availableOnDate($product, $date, ShopStockAudience::Consumer),
            0.001,
        );
        $this->assertEqualsWithDelta(
            25.0,
            $this->service->availableOnDate($product, $date, ShopStockAudience::BusinessTrade),
            0.001,
        );
    }

    public function test_shared_inflow_visible_to_both_audiences_and_depleted_by_all_shop_orders(): void
    {
        $product = Product::factory()->create();
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => '2026-06-10',
            'quantity' => 100,
            'freshness_window_days' => 1,
            'b2b_only' => false,
        ]);

        $order = ShopOrder::create([
            'name' => 'C', 'email' => 'c@example.com', 'phone' => null,
            'fulfillment_mode' => 'pickup', 'pickup_location' => 'karl_kunger',
            'expected_delivery_date' => '2026-06-10',
            'customer_type' => CustomerType::Private->value,
            'items_total_eur' => 0, 'delivery_fee_eur' => 0, 'total_amount_eur' => 0,
            'payment_method' => 'in_person', 'status' => ShopOrderStatus::Paid,
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'quantity' => 30, 'unit' => 'piece',
            'unit_price_snapshot_eur' => 1, 'line_total_eur' => 30,
            'vat_rate_snapshot_percent' => 7, 'vat_amount_eur' => 0,
        ]);

        $date = Carbon::parse('2026-06-10');

        $this->assertEqualsWithDelta(70.0, $this->service->availableOnDate($product, $date, ShopStockAudience::Consumer), 0.001);
        $this->assertEqualsWithDelta(70.0, $this->service->availableOnDate($product, $date, ShopStockAudience::BusinessTrade), 0.001);
    }
}
