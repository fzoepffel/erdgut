<?php

namespace Tests\Unit\Services;

use App\Enums\ProductUnit;
use App\Enums\ShopStockAudience;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Admin\ProducerAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The producer-allocation read-model: ranking, exhaustion, fallback. */
class ProducerAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProducerAllocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProducerAllocationService::class);
        Carbon::setTestNow(Carbon::parse('2026-07-14 08:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function product(Producer $producer, array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'producer_id' => $producer->id,
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
        ], $attrs));
    }

    private function inflow(Product $product, Producer $producer, string $date, float $qty, int $freshness = 2): void
    {
        ProductStockInflow::create([
            'product_id' => $product->id, 'producer_id' => $producer->id,
            'received_on' => $date, 'quantity' => $qty, 'freshness_window_days' => $freshness,
        ]);
    }

    private function order(string $date, Product $product, float $qty): void
    {
        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => $date]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => $qty, 'unit' => 'piece',
        ]);
    }

    public function test_single_producer_gets_everything(): void
    {
        $farm = Producer::factory()->create();
        $product = $this->product($farm);
        $this->inflow($product, $farm, '2026-07-15', 20);
        $this->order('2026-07-15', $product, 8);

        $shares = $this->service->allocate([$product->id], ['2026-07-15']);

        $this->assertEqualsWithDelta(8.0, $shares['2026-07-15'][$product->id][0][$farm->id], 0.001);
    }

    public function test_anchored_producer_is_drained_before_the_other(): void
    {
        // Farm A is the SOLE candidate for product X → anchor score 1.
        // For product Y both farms have batches; A must be exhausted first.
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $productX = $this->product($farmA);
        $productY = $this->product($farmA);
        $productY->syncProducers([$farmA->id, $farmB->id]);

        $this->inflow($productX, $farmA, '2026-07-15', 10);
        $this->inflow($productY, $farmA, '2026-07-15', 4);
        $this->inflow($productY, $farmB, '2026-07-15', 50);

        $this->order('2026-07-15', $productX, 5);
        $this->order('2026-07-15', $productY, 10);

        $shares = $this->service->allocate([$productX->id, $productY->id], ['2026-07-15']);

        // Product Y: farm A's 4 pieces first (anchored via product X), rest from B.
        $y = $shares['2026-07-15'][$productY->id][0];
        $this->assertEqualsWithDelta(4.0, $y[$farmA->id], 0.001);
        $this->assertEqualsWithDelta(6.0, $y[$farmB->id], 0.001);
    }

    public function test_tie_breaks_by_breadth_then_producer_id(): void
    {
        // No anchors (both farms candidate everywhere). Farm A supplies TWO
        // (product, grade) demands, farm B one → A ranks first on breadth.
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $p1 = $this->product($farmA);
        $p1->syncProducers([$farmA->id, $farmB->id]);
        $p2 = $this->product($farmA);

        $this->inflow($p1, $farmA, '2026-07-15', 30);
        $this->inflow($p1, $farmB, '2026-07-15', 30);
        $this->inflow($p2, $farmA, '2026-07-15', 30);
        $this->inflow($p2, $farmB, '2026-07-15', 30); // B also has a p2 batch → no anchors at all

        $this->order('2026-07-15', $p1, 10);
        $this->order('2026-07-15', $p2, 10);

        $shares = $this->service->allocate([$p1->id, $p2->id], ['2026-07-15']);

        // Equal breadth (both supply both) → producer_id asc wins: farm A
        // (created first, lower id) covers everything.
        $this->assertEqualsWithDelta(10.0, $shares['2026-07-15'][$p1->id][0][$farmA->id] ?? 0.0, 0.001);
        $this->assertEqualsWithDelta(10.0, $shares['2026-07-15'][$p2->id][0][$farmA->id] ?? 0.0, 0.001);
        $this->assertArrayNotHasKey($farmB->id, $shares['2026-07-15'][$p1->id][0]);
    }

    public function test_batch_consumption_persists_across_days(): void
    {
        // One 10-piece batch alive Mon+Tue; Monday consumes 7 → Tuesday's
        // demand of 5 gets only 3 from the batch, 2 fall back to primary.
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = $this->product($farmB); // primary = B (fallback target)
        $product->syncProducers([$farmB->id, $farmA->id]);
        $this->inflow($product, $farmA, '2026-07-20', 10, 2); // lives Mon 20th + Tue 21st

        $this->order('2026-07-20', $product, 7);
        $this->order('2026-07-21', $product, 5);

        $shares = $this->service->allocate([$product->id], ['2026-07-20', '2026-07-21']);

        $this->assertEqualsWithDelta(7.0, $shares['2026-07-20'][$product->id][0][$farmA->id], 0.001);
        $this->assertEqualsWithDelta(3.0, $shares['2026-07-21'][$product->id][0][$farmA->id], 0.001);
        $this->assertEqualsWithDelta(2.0, $shares['2026-07-21'][$product->id][0][$farmB->id], 0.001);
    }

    public function test_fefo_within_a_producer(): void
    {
        $farm = Producer::factory()->create();
        $product = $this->product($farm);
        // Two farm batches on the same day: one expires sooner.
        $this->inflow($product, $farm, '2026-07-15', 5, 1); // expires 15th
        $this->inflow($product, $farm, '2026-07-15', 5, 3); // lives till 17th
        $this->order('2026-07-15', $product, 4);
        $this->order('2026-07-16', $product, 5);

        $shares = $this->service->allocate([$product->id], ['2026-07-15', '2026-07-16']);

        // The 15th's 4 pieces come from the short batch (FEFO), leaving the
        // long batch intact to fully cover the 16th.
        $this->assertEqualsWithDelta(4.0, $shares['2026-07-15'][$product->id][0][$farm->id], 0.001);
        $this->assertEqualsWithDelta(5.0, $shares['2026-07-16'][$product->id][0][$farm->id], 0.001);
    }

    public function test_uncovered_demand_falls_back_to_the_primary_producer(): void
    {
        $farm = Producer::factory()->create();
        $product = $this->product($farm); // NO inflows at all
        $this->order('2026-07-15', $product, 6);

        $shares = $this->service->allocate([$product->id], ['2026-07-15']);

        $this->assertEqualsWithDelta(6.0, $shares['2026-07-15'][$product->id][0][$farm->id], 0.001);
    }

    public function test_product_without_primary_lands_in_key_zero(): void
    {
        $farm = Producer::factory()->create();
        $product = $this->product($farm);
        $this->order('2026-07-15', $product, 6);
        $product->forceFill(['producer_id' => null])->save();
        DB::table('product_producer')->where('product_id', $product->id)->delete();

        $shares = $this->service->allocate([$product->id], ['2026-07-15']);

        $this->assertEqualsWithDelta(6.0, $shares['2026-07-15'][$product->id][0][0], 0.001);
    }

    public function test_injected_demand_overrides_global_commitments(): void
    {
        $farm = Producer::factory()->create();
        $product = $this->product($farm);
        $this->inflow($product, $farm, '2026-07-15', 20);
        $this->order('2026-07-15', $product, 8); // global commitment, must be ignored

        $shares = $this->service->allocate([$product->id], ['2026-07-15'], [
            $product->id => [0 => ['2026-07-15' => 3.0]],
        ]);

        $this->assertEqualsWithDelta(3.0, $shares['2026-07-15'][$product->id][0][$farm->id], 0.001);
    }

    public function test_b2b_only_batches_take_part_in_attribution(): void
    {
        $farm = Producer::factory()->create();
        $product = $this->product($farm);
        ProductStockInflow::create([
            'product_id' => $product->id, 'producer_id' => $farm->id,
            'received_on' => '2026-07-15', 'quantity' => 10, 'freshness_window_days' => 2,
            'b2b_only' => true,
        ]);
        $this->order('2026-07-15', $product, 4);

        $shares = $this->service->allocate([$product->id], ['2026-07-15']);

        // Covered by the b2b-only batch (the farm delivers either way), not
        // via the primary fallback — same producer here, but zero remainder.
        $this->assertEqualsWithDelta(4.0, $shares['2026-07-15'][$product->id][0][$farm->id], 0.001);
    }

    public function test_remaining_by_producer_lists_both_farms_quantity_desc(): void
    {
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = $this->product($farmA);
        $product->syncProducers([$farmA->id, $farmB->id]);
        $this->inflow($product, $farmA, '2026-07-15', 4);
        $this->inflow($product, $farmB, '2026-07-15', 50);

        $remaining = $this->service->remainingByProducer([$product->id], ['2026-07-15']);

        $shares = $remaining['2026-07-15'][$product->id];
        $this->assertSame([$farmB->id, $farmA->id], array_keys($shares));
        $this->assertEqualsWithDelta(50.0, $shares[$farmB->id], 0.001);
        $this->assertEqualsWithDelta(4.0, $shares[$farmA->id], 0.001);
    }

    public function test_remaining_by_producer_drops_a_farm_depleted_by_commitments(): void
    {
        // Farm A (lower id, equal breadth) is drained first by the 4-piece
        // order; only farm B keeps sellable stock.
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = $this->product($farmA);
        $product->syncProducers([$farmA->id, $farmB->id]);
        $this->inflow($product, $farmA, '2026-07-15', 4);
        $this->inflow($product, $farmB, '2026-07-15', 50);
        $this->order('2026-07-15', $product, 4);

        $remaining = $this->service->remainingByProducer([$product->id], ['2026-07-15']);

        $shares = $remaining['2026-07-15'][$product->id];
        $this->assertArrayNotHasKey($farmA->id, $shares);
        $this->assertEqualsWithDelta(50.0, $shares[$farmB->id], 0.001);
    }

    public function test_remaining_by_producer_hides_b2b_only_stock_from_consumers(): void
    {
        $farm = Producer::factory()->create();
        $product = $this->product($farm);
        ProductStockInflow::create([
            'product_id' => $product->id, 'producer_id' => $farm->id,
            'received_on' => '2026-07-15', 'quantity' => 10, 'freshness_window_days' => 2,
            'b2b_only' => true,
        ]);

        $consumer = $this->service->remainingByProducer([$product->id], ['2026-07-15'], ShopStockAudience::Consumer);
        $business = $this->service->remainingByProducer([$product->id], ['2026-07-15'], ShopStockAudience::BusinessTrade);

        $this->assertArrayNotHasKey($product->id, $consumer['2026-07-15'] ?? []);
        $this->assertEqualsWithDelta(10.0, $business['2026-07-15'][$product->id][$farm->id], 0.001);
    }

    public function test_remaining_by_producer_omits_products_without_batches(): void
    {
        $farm = Producer::factory()->create();
        $product = $this->product($farm); // no inflows

        $remaining = $this->service->remainingByProducer([$product->id], ['2026-07-15']);

        $this->assertArrayNotHasKey($product->id, $remaining['2026-07-15'] ?? []);
    }

    public function test_remaining_by_producer_maps_null_producer_batches_to_the_primary(): void
    {
        $farm = Producer::factory()->create();
        $product = $this->product($farm);
        ProductStockInflow::create([
            'product_id' => $product->id, 'producer_id' => null,
            'received_on' => '2026-07-15', 'quantity' => 7, 'freshness_window_days' => 2,
        ]);

        $remaining = $this->service->remainingByProducer([$product->id], ['2026-07-15']);

        $this->assertEqualsWithDelta(7.0, $remaining['2026-07-15'][$product->id][$farm->id], 0.001);
    }

    public function test_remaining_by_producer_uses_end_state_across_days(): void
    {
        // One 10-piece batch alive Mon+Tue; Tuesday's order of 7 has already
        // drawn the batch down for Monday's display too (end-state semantics,
        // mirroring the availability math).
        $farm = Producer::factory()->create();
        $product = $this->product($farm);
        $this->inflow($product, $farm, '2026-07-20', 10, 2);
        $this->order('2026-07-21', $product, 7);

        $remaining = $this->service->remainingByProducer([$product->id], ['2026-07-20']);

        $this->assertEqualsWithDelta(3.0, $remaining['2026-07-20'][$product->id][$farm->id], 0.001);
    }

    public function test_remaining_by_producer_leaves_allocate_untouched(): void
    {
        $farm = Producer::factory()->create();
        $product = $this->product($farm);
        $this->inflow($product, $farm, '2026-07-15', 20);
        $this->order('2026-07-15', $product, 8);

        $this->service->remainingByProducer([$product->id], ['2026-07-15']);
        $shares = $this->service->allocate([$product->id], ['2026-07-15']);

        $this->assertEqualsWithDelta(8.0, $shares['2026-07-15'][$product->id][0][$farm->id], 0.001);
    }

    public function test_names_by_item_key_joins_split_lines_share_desc(): void
    {
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = $this->product($farmA);
        $product->syncProducers([$farmA->id, $farmB->id]);
        $this->inflow($product, $farmA, '2026-07-15', 4);
        $this->inflow($product, $farmB, '2026-07-15', 50);
        $this->order('2026-07-15', $product, 10);

        $names = $this->service->namesByItemKey([$product->id], Carbon::parse('2026-07-15'));

        // Farm A is drained first (4), farm B covers the rest (6) — the
        // label orders by allocated share desc.
        $this->assertSame($farmB->name.' · '.$farmA->name, $names[$product->id.'|0']);
    }

    public function test_names_by_item_key_omits_products_without_demand(): void
    {
        $farm = Producer::factory()->create();
        $product = $this->product($farm);
        $this->inflow($product, $farm, '2026-07-15', 20);

        $names = $this->service->namesByItemKey([$product->id], Carbon::parse('2026-07-15'));

        $this->assertArrayNotHasKey($product->id.'|0', $names);
    }
}
