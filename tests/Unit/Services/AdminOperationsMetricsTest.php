<?php

namespace Tests\Unit\Services;

use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductProducerVariantPrice;
use App\Models\ProductStockInflow;
use App\Models\ProductVariant;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Admin\AdminOperationsMetrics;
use App\Services\Admin\AdminOperationsSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Net revenue buckets, top products and KPIs of the operations dashboard. */
class AdminOperationsMetricsTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::parse('2026-07-08 09:00:00'); // a Wednesday, ISO week 28
    }

    private function build(): AdminOperationsSnapshot
    {
        return app(AdminOperationsMetrics::class)->build($this->now->copy());
    }

    private function product(array $attrs = []): Product
    {
        if (Producer::query()->count() === 0) {
            Producer::factory()->create();
        }

        return Product::factory()->create(array_merge([
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'unit_price' => 10.00, 'vat_rate_percent' => 7,
            'is_available' => true, 'is_published' => true,
        ], $attrs));
    }

    private function shopOrder(string $date, float $gross, float $itemVat, array $attrs = [], ?Product $product = null, float $qty = 1): ShopOrder
    {
        $order = ShopOrder::factory()->paid()->create(array_merge([
            'expected_delivery_date' => $date,
            'total_amount_eur' => $gross,
            'items_total_eur' => $gross,
            'delivery_fee_eur' => 0,
            'delivery_fee_vat_amount_eur' => 0,
            'discount_eur' => 0,
        ], $attrs));
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id,
            'product_id' => ($product ?? $this->product())->id,
            'quantity' => $qty,
            'unit' => 'piece',
            'line_total_eur' => $gross,
            'vat_amount_eur' => $itemVat,
        ]);

        return $order;
    }

    private function restaurantOrder(string $date, float $net, string $status = 'confirmed', array $attrs = [], ?Product $product = null, float $qty = 1, string $lineStatus = 'confirmed'): RestaurantOrder
    {
        $profile = RestaurantProfile::factory()->verified()->create();
        $gross = round($net * 1.07, 2);
        $order = RestaurantOrder::create(array_merge([
            'user_id' => $profile->user_id,
            'status' => $status,
            'kind' => 'order',
            'expected_delivery_date' => $date,
            'delivery_street' => 'S 1', 'delivery_postal_code' => '10115', 'delivery_city' => 'Berlin',
            'items_total_net_eur' => $net, 'items_total_gross_eur' => $gross, 'vat_total_eur' => round($gross - $net, 2),
        ], $attrs));
        $order->items()->create([
            'product_id' => ($product ?? $this->product())->id,
            'quantity' => $qty, 'unit' => 'piece', 'track' => 'reserved', 'line_status' => $lineStatus,
            'unit_price_snapshot_eur' => 1, 'vat_rate_snapshot_percent' => 7,
            'line_total_eur' => $gross, 'vat_amount_eur' => round($gross - $net, 2),
        ]);

        return $order;
    }

    private function bucketFor(AdminOperationsSnapshot $snapshot, string $weekStart): ?object
    {
        foreach ($snapshot->weeklyRevenue as $bucket) {
            if ($bucket->weekStart === $weekStart) {
                return $bucket;
            }
        }

        return null;
    }

    public function test_weekly_buckets_span_and_attribute_by_delivery_date(): void
    {
        // Two weeks ago (week of 2026-06-22): a B2C order.
        $this->shopOrder('2026-06-24', 107.0, 7.0);
        // Next week (week of 2026-07-13): a committed (materialized) B2B order.
        $this->restaurantOrder('2026-07-15', 200.0);

        $snapshot = $this->build();

        $this->assertCount(16, $snapshot->weeklyRevenue);
        $this->assertSame('2026-04-20', $snapshot->weeklyRevenue[0]->weekStart);
        $this->assertSame('2026-08-03', $snapshot->weeklyRevenue[15]->weekStart);

        $past = $this->bucketFor($snapshot, '2026-06-22');
        $this->assertEqualsWithDelta(100.0, $past->b2cNet, 0.01); // 107 gross − 7 VAT
        $this->assertFalse($past->isFuture);

        $future = $this->bucketFor($snapshot, '2026-07-13');
        $this->assertEqualsWithDelta(200.0, $future->b2bNet, 0.01);
        $this->assertTrue($future->isFuture);

        $current = $this->bucketFor($snapshot, '2026-07-06');
        $this->assertFalse($current->isFuture);
    }

    public function test_b2c_net_subtracts_item_and_delivery_fee_vat(): void
    {
        // 107 items gross (7 VAT) + 5.35 delivery (0.35 VAT) = 112.35 gross → 105 net.
        $this->shopOrder('2026-07-08', 112.35, 7.0, [
            'delivery_fee_eur' => 5.35,
            'delivery_fee_vat_amount_eur' => 0.35,
        ]);

        $snapshot = $this->build();

        $this->assertEqualsWithDelta(105.0, $snapshot->revenueThisWeekNet, 0.01);
        $this->assertEqualsWithDelta(112.35, $snapshot->revenueThisWeekGross, 0.01);
    }

    public function test_exclusions_per_channel(): void
    {
        // Excluded B2C: cancelled, refunded, failed, online pending.
        foreach (['cancelled', 'refunded', 'failed'] as $status) {
            $this->shopOrder('2026-07-08', 50.0, 3.0, ['status' => $status]);
        }
        $this->shopOrder('2026-07-08', 50.0, 3.0, ['status' => 'pending_payment', 'payment_method' => 'online']);
        // Included B2C: in-person still pending payment.
        $this->shopOrder('2026-07-08', 107.0, 7.0, ['status' => 'pending_payment', 'payment_method' => 'in_person']);
        // Excluded B2B: cancelled, declined, submitted.
        foreach (['cancelled', 'declined', 'submitted'] as $status) {
            $this->restaurantOrder('2026-07-08', 99.0, $status);
        }
        // Included B2B: fulfilled.
        $this->restaurantOrder('2026-07-08', 40.0, 'fulfilled');

        $snapshot = $this->build();

        $this->assertEqualsWithDelta(140.0, $snapshot->revenueThisWeekNet, 0.01);
        $this->assertSame(1, $snapshot->ordersThisWeekB2c);
        $this->assertSame(1, $snapshot->ordersThisWeekB2b);
    }

    public function test_top_products_merge_channels_and_skip_declined_lines(): void
    {
        $shared = $this->product();
        // Same product sold via both channels within 30 days.
        $this->shopOrder('2026-07-01', 107.0, 7.0, [], $shared, 10);
        $b2b = $this->restaurantOrder('2026-06-30', 50.0, 'confirmed', [], $shared, 5);
        // A declined line on the same order never counts.
        $b2b->items()->create([
            'product_id' => $this->product()->id,
            'quantity' => 99, 'unit' => 'piece', 'track' => 'requested', 'line_status' => 'declined',
            'unit_price_snapshot_eur' => 1, 'vat_rate_snapshot_percent' => 7,
            'line_total_eur' => 999, 'vat_amount_eur' => 0,
        ]);

        $snapshot = $this->build();

        $this->assertNotEmpty($snapshot->topProducts);
        $topRow = collect($snapshot->topProducts)->firstWhere('name', $shared->name);
        $this->assertNotNull($topRow);
        $this->assertEqualsWithDelta(15.0, $topRow->qtyDisplay, 0.01); // 10 + 5 pieces
        $this->assertEqualsWithDelta(150.0, $topRow->netRevenue, 0.01); // 100 + 50
        $this->assertNull(collect($snapshot->topProducts)->firstWhere('netRevenue', 999.0));
        $this->assertLessThanOrEqual(8, count($snapshot->topProducts));
    }

    public function test_kpis_open_requests_unpaid_invoices_cash_and_margin(): void
    {
        // Open request.
        $this->restaurantOrder('2026-07-09', 10.0, 'submitted');
        // Unpaid invoice (confirmed + invoiced, not paid).
        $this->restaurantOrder('2026-07-02', 80.0, 'fulfilled', [
            'invoice_number' => '2026-042', 'invoiced_at' => '2026-07-03 10:00:00',
            'invoice_due_date' => '2026-07-17', 'invoice_paid_at' => null,
        ]);
        // Outstanding cash.
        $this->shopOrder('2026-07-08', 25.0, 1.5, ['status' => 'pending_payment', 'payment_method' => 'in_person']);

        // Margin: product with buying price 4 €/piece, sold 10 pieces at 107 gross / 100 net.
        $priced = $this->product(['buying_price_cents' => 400]);
        $this->shopOrder('2026-07-07', 107.0, 7.0, [], $priced, 10);

        $snapshot = $this->build();

        $this->assertSame(1, $snapshot->openB2bRequests);
        $this->assertSame(1, $snapshot->unpaidB2bInvoicesCount);
        $this->assertEqualsWithDelta(80.0, $snapshot->unpaidB2bInvoicesNet, 0.01);
        $this->assertEqualsWithDelta(25.0, $snapshot->outstandingInPersonEur, 0.01);
        // Margin = 100 net − 10 × 4 = 60 over the priced line only.
        $this->assertEqualsWithDelta(60.0, $snapshot->marginThisWeekNet, 0.01);
        $this->assertGreaterThan(0.0, $snapshot->marginCoveragePct);
        $this->assertLessThan(100.0, $snapshot->marginCoveragePct); // the cash order has no buying price
    }

    public function test_margin_is_null_without_any_buying_prices(): void
    {
        $this->shopOrder('2026-07-08', 107.0, 7.0);

        $this->assertNull($this->build()->marginThisWeekNet);
    }

    public function test_margin_costs_grade_shares_at_the_grade_price(): void
    {
        // The named grade comes from farm B at 3 euro/piece for THIS
        // grade (farm price 2, default 4). 10 pieces → cost 30, margin 70.
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = $this->product(['producer_id' => $farmA->id, 'buying_price_cents' => 400]);
        $product->syncProducers([$farmA->id, $farmB->id]);
        $product->producers()->updateExistingPivot($farmB->id, ['buying_price_cents' => 200]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id, 'name' => ['de' => 'Premium'],
        ]);
        ProductProducerVariantPrice::create([
            'product_id' => $product->id, 'producer_id' => $farmB->id,
            'variant_id' => $variant->id, 'buying_price_cents' => 300,
        ]);
        ProductStockInflow::create([
            'product_id' => $product->id, 'producer_id' => $farmB->id, 'variant_id' => $variant->id,
            'received_on' => '2026-07-07', 'quantity' => 50, 'freshness_window_days' => 2,
        ]);
        $order = $this->shopOrder('2026-07-07', 107.0, 7.0, [], $product, 10);
        $order->items()->first()->forceFill(['variant_id' => $variant->id])->save();

        $this->assertEqualsWithDelta(70.0, $this->build()->marginThisWeekNet, 0.01);
    }

    public function test_margin_costs_each_allocation_share_at_its_farms_price(): void
    {
        // Farm A (primary, default 4 €/piece) has 4 pieces in stock, farm B
        // (override 3 €/piece) has 50. A 10-piece order: A is drained first
        // (equal anchor/breadth, lower id) → cost 4×4 + 6×3 = 34.
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = $this->product(['producer_id' => $farmA->id, 'buying_price_cents' => 400]);
        $product->syncProducers([$farmA->id, $farmB->id]);
        $product->producers()->updateExistingPivot($farmB->id, ['buying_price_cents' => 300]);
        foreach ([[$farmA->id, 4], [$farmB->id, 50]] as [$farmId, $qty]) {
            ProductStockInflow::create([
                'product_id' => $product->id, 'producer_id' => $farmId,
                'received_on' => '2026-07-07', 'quantity' => $qty, 'freshness_window_days' => 2,
            ]);
        }
        $this->shopOrder('2026-07-07', 107.0, 7.0, [], $product, 10);

        // Margin = 100 net − 34 buying.
        $this->assertEqualsWithDelta(66.0, $this->build()->marginThisWeekNet, 0.01);
    }
}
