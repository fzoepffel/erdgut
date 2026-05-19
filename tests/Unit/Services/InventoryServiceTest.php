<?php

namespace Tests\Unit\Services;

use App\Enums\BoxStatus;
use App\Enums\SpargelOrderStatus;
use App\Models\Address;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ProductStockInflowSchedule;
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
}
