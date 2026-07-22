<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductUnit;
use App\Http\Controllers\Admin\AdminShopOrderController;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Admin\ProducerReassignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Manual sourcing moves in the receiving panel: a position covered by several
 * farms can be relocated to another farm (or a look-alike product), and the
 * panel plus the farmer export honor the override without touching the order.
 */
class AdminProducerReassignmentTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-07-15';

    private Producer $farmA;

    private Producer $farmB;

    private Producer $farmC;

    private Product $zucchini;      // primary A, supplied by A and B

    private Product $zucchiniGelb;  // look-alike, primary B

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-14 08:00:00'));

        $this->farmA = $this->farm('Farm Alpha');
        $this->farmB = $this->farm('Farm Beta');
        $this->farmC = $this->farm('Farm Gamma');

        $this->zucchini = Product::factory()->create([
            'producer_id' => $this->farmA->id,
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'is_available' => true, 'is_published' => true,
        ]);
        $this->zucchini->translations()->update(['name' => 'Zucchini gruen']);
        $this->zucchini = $this->zucchini->fresh();
        // Both farms can supply the zucchini (pivot drives the target label).
        $this->zucchini->producers()->sync([$this->farmA->id, $this->farmB->id]);

        $this->zucchiniGelb = Product::factory()->create([
            'producer_id' => $this->farmB->id,
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'is_available' => true, 'is_published' => true,
        ]);
        $this->zucchiniGelb->translations()->update(['name' => 'Zucchini gelb']);
        $this->zucchiniGelb = $this->zucchiniGelb->fresh();

        // Stock: A has 5, B has 20 zucchini; B has 20 zucchini gelb; C has only 1.
        $this->inflow($this->zucchini, $this->farmA, 5);
        $this->inflow($this->zucchini, $this->farmB, 20);
        $this->inflow($this->zucchiniGelb, $this->farmB, 20);

        // A firm shop order for 3 zucchini.
        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => self::DATE]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $this->zucchini->id,
            'quantity' => 3, 'unit' => 'piece', 'line_total_eur' => 9.0, 'vat_amount_eur' => 0.59,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function farm(string $name): Producer
    {
        $p = Producer::factory()->create();
        $p->translations()->update(['name' => $name]);

        return $p->fresh();
    }

    private function inflow(Product $product, Producer $producer, float $qty): void
    {
        ProductStockInflow::create([
            'product_id' => $product->id, 'producer_id' => $producer->id,
            'received_on' => self::DATE, 'quantity' => $qty, 'freshness_window_days' => 2,
        ]);
    }

    private function service(): ProducerReassignmentService
    {
        return app(ProducerReassignmentService::class);
    }

    private function targets(): array
    {
        $qty = $this->service()->positionDemand($this->zucchini->id, 0, self::DATE);
        $current = $this->service()->currentSourcing($this->zucchini->id, 0, self::DATE);

        return $this->service()->moveTargetsForPosition(
            $this->zucchini->fresh(), 0, self::DATE, $qty, $current['product_id'], $current['producer_id'],
        );
    }

    public function test_move_targets_include_other_farm_and_similar_product(): void
    {
        $targets = collect($this->targets());

        // Farm B can take the whole zucchini position (same product).
        $sameProduct = $targets->firstWhere(fn ($t) => $t['target_product_id'] === $this->zucchini->id && $t['producer_id'] === $this->farmB->id);
        $this->assertNotNull($sameProduct);
        $this->assertTrue($sameProduct['same_product']);

        // Capacity carries the target product's unit so the panel can label it.
        $this->assertArrayHasKey('unit', $sameProduct);
        $this->assertNotSame('', $sameProduct['unit']);

        // The look-alike "Zucchini gelb" from farm B is offered too.
        $lookalike = $targets->firstWhere(fn ($t) => $t['target_product_id'] === $this->zucchiniGelb->id);
        $this->assertNotNull($lookalike);
        $this->assertFalse($lookalike['same_product']);
        $this->assertSame($this->farmB->id, $lookalike['producer_id']);
    }

    public function test_farm_without_enough_stock_is_not_a_target(): void
    {
        // Farm C has only 1 piece; it must never appear for a 3-piece position.
        $this->assertNull(collect($this->targets())->firstWhere('producer_id', $this->farmC->id));
    }

    public function test_registered_farm_without_inflow_is_still_a_same_product_target(): void
    {
        // Farm C is a listed supplier of the zucchini (pivot) but has no tracked
        // inflow. A same-product, different-farm move must still be possible.
        $this->zucchini->producers()->syncWithoutDetaching([$this->farmC->id => ['buying_price_cents' => 150]]);

        $target = collect($this->targets())->firstWhere(
            fn ($t) => $t['target_product_id'] === $this->zucchini->id && $t['producer_id'] === $this->farmC->id,
        );

        $this->assertNotNull($target);
        $this->assertTrue($target['same_product']);
    }

    public function test_move_targets_carry_buying_price_and_margin_warning(): void
    {
        // Customer keeps the 2,50 € zucchini price. Farm B buys cheaper (1,50),
        // the pricier look-alike drives the margin negative.
        $this->zucchini->update(['unit_price' => 2.5, 'buying_price_cents' => 180]);
        $this->zucchini->producers()->syncWithoutDetaching([$this->farmB->id => ['buying_price_cents' => 150]]);
        $this->zucchiniGelb->update(['unit_price' => 2.5, 'buying_price_cents' => 300]);

        $targets = collect($this->targets());

        $bSame = $targets->firstWhere(
            fn ($t) => $t['target_product_id'] === $this->zucchini->id && $t['producer_id'] === $this->farmB->id,
        );
        $this->assertNotNull($bSame);
        $this->assertSame(1.5, $bSame['buying_price']);
        $this->assertSame(1.0, $bSame['margin']);
        $this->assertTrue($bSame['price_differs']);
        $this->assertFalse($bSame['margin_negative']);

        $gelb = $targets->firstWhere(fn ($t) => $t['target_product_id'] === $this->zucchiniGelb->id);
        $this->assertNotNull($gelb);
        $this->assertTrue($gelb['margin_negative']);
    }

    public function test_lookalike_product_from_another_farm_makes_row_movable(): void
    {
        $admin = User::factory()->admin()->create();

        // Two separate products with the SAME name, one farm each. Looke covers
        // its own small order; Melchhof (a look-alike product) has spare stock.
        $looke = Product::factory()->create([
            'producer_id' => $this->farmA->id,
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'is_available' => true, 'is_published' => true,
        ]);
        $looke->translations()->update(['name' => 'Yellow Zucchini']);
        $looke = $looke->fresh();
        $looke->producers()->sync([$this->farmA->id]);

        $melchhof = Product::factory()->create([
            'producer_id' => $this->farmB->id,
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'is_available' => true, 'is_published' => true,
        ]);
        $melchhof->translations()->update(['name' => 'Yellow Zucchini']);
        $melchhof = $melchhof->fresh();
        $melchhof->producers()->sync([$this->farmB->id]);

        $this->inflow($looke, $this->farmA, 2);
        $this->inflow($melchhof, $this->farmB, 20);

        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => self::DATE]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $looke->id,
            'quantity' => 2, 'unit' => 'piece', 'line_total_eur' => 5.0, 'vat_amount_eur' => 0.3,
        ]);

        $producers = collect($this->actingAs($admin)
            ->get(route('app.admin.producer-orders.index', ['delivery_from' => self::DATE, 'delivery_to' => self::DATE]))
            ->assertOk()
            ->viewData('producers'));

        $a = $producers->firstWhere('id', $this->farmA->id);
        $this->assertNotNull($a);
        $row = collect($a['rows'])->firstWhere('product_id', $looke->id);
        $this->assertNotNull($row);
        // Melchhof's look-alike product can cover the whole Looke position, so
        // the position must be movable even though Looke lists only one farm.
        $this->assertTrue($row['movable']);
    }

    public function test_move_to_registered_farm_without_inflow_is_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        $this->zucchini->producers()->syncWithoutDetaching([$this->farmC->id => ['buying_price_cents' => 150]]);

        $this->actingAs($admin)
            ->postJson(route('app.admin.producer-orders.move'), [
                'product_id' => $this->zucchini->id,
                'variant_id' => 0,
                'delivery_date' => self::DATE,
                'target_product_id' => $this->zucchini->id,
                'target_producer_id' => $this->farmC->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('producer_position_overrides', [
            'product_id' => $this->zucchini->id,
            'target_producer_id' => $this->farmC->id,
        ]);
    }

    public function test_move_endpoint_creates_override_and_reattributes_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('app.admin.producer-orders.move'), [
                'product_id' => $this->zucchini->id,
                'variant_id' => 0,
                'delivery_date' => self::DATE,
                'target_product_id' => $this->zucchini->id,
                'target_producer_id' => $this->farmB->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('producer_position_overrides', [
            'product_id' => $this->zucchini->id,
            'variant_id' => 0,
            'delivery_date' => self::DATE,
            'target_product_id' => $this->zucchini->id,
            'target_producer_id' => $this->farmB->id,
        ]);

        $producers = collect($this->actingAs($admin)
            ->get(route('app.admin.producer-orders.index', ['delivery_from' => self::DATE, 'delivery_to' => self::DATE]))
            ->assertOk()
            ->viewData('producers'));

        // Zucchini is now under farm B (moved), and not under farm A.
        $b = $producers->firstWhere('id', $this->farmB->id);
        $this->assertNotNull($b);
        $bRow = collect($b['rows'])->firstWhere('product_id', $this->zucchini->id);
        $this->assertNotNull($bRow);
        $this->assertTrue($bRow['overridden']);
        $this->assertSame('3', (string) $bRow['qty_display']);

        $a = $producers->firstWhere('id', $this->farmA->id);
        if ($a !== null) {
            $this->assertNull(collect($a['rows'])->firstWhere('product_id', $this->zucchini->id));
        }
    }

    public function test_move_endpoint_rejects_insufficient_target(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('app.admin.producer-orders.move'), [
                'product_id' => $this->zucchini->id,
                'variant_id' => 0,
                'delivery_date' => self::DATE,
                'target_product_id' => $this->zucchini->id,
                'target_producer_id' => $this->farmC->id,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('producer_position_overrides', 0);
    }

    public function test_farmer_export_follows_the_move(): void
    {
        $this->service()->move($this->zucchini->id, 0, self::DATE, $this->zucchini->id, $this->farmB->id, null);

        // Farm B's order now lists the zucchini; farm A's no longer does.
        $rowsB = $this->farmerRows($this->farmB->id);
        $this->assertcontainsProduct('Zucchini gruen', $rowsB);

        $rowsA = $this->farmerRows($this->farmA->id);
        $this->assertNotContainsProduct('Zucchini gruen', $rowsA);
    }

    public function test_reset_restores_default_attribution(): void
    {
        $admin = User::factory()->admin()->create();
        $this->service()->move($this->zucchini->id, 0, self::DATE, $this->zucchini->id, $this->farmB->id, null);

        $this->actingAs($admin)
            ->postJson(route('app.admin.producer-orders.move.reset'), [
                'product_id' => $this->zucchini->id,
                'variant_id' => 0,
                'delivery_date' => self::DATE,
            ])
            ->assertOk();

        $this->assertDatabaseCount('producer_position_overrides', 0);
    }

    private function farmerRows(int $producerId): array
    {
        $orders = ShopOrder::query()->finalized()
            ->with(['items.product.producer.translations', 'items.product.translations', 'items.product.variants'])
            ->orderBy('expected_delivery_date')->orderBy('id')
            ->get();

        $method = new ReflectionMethod(AdminShopOrderController::class, 'buildFarmerOrderRows');

        return $method->invoke(app(AdminShopOrderController::class), $orders, $producerId);
    }

    private function assertContainsProduct(string $name, array $rows): void
    {
        $this->assertContains($name, array_column($rows, 'product_name'));
    }

    private function assertNotContainsProduct(string $name, array $rows): void
    {
        $this->assertNotContains($name, array_column($rows, 'product_name'));
    }
}
