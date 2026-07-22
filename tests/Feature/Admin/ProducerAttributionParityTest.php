<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductUnit;
use App\Exports\ShopOrderFarmTotalsSheet;
use App\Http\Controllers\Admin\AdminShopOrderController;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

/**
 * GOLDEN MASTER for the producer-attribution switch (multi-producer P3).
 *
 * In the single-producer state (every product has exactly one pivot
 * producer — production's state until P5 ships) the allocation-based
 * attribution MUST reproduce today's producer_id-based outputs exactly:
 * farmer export rows, the farmer dropdown, farm totals' producer column,
 * and the packing page's producer labels. Written against the pre-refactor
 * code; must stay green, unmodified, after the refactor.
 */
class ProducerAttributionParityTest extends TestCase
{
    use RefreshDatabase;

    private Producer $farmA;

    private Producer $farmB;

    private Product $stockedA;   // farm A, mass/kg, has an inflow

    private Product $inflowlessA; // farm A, NO inflow rows at all

    private Product $stockedB;   // farm B, piece

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-14 08:00:00'));
        $this->date = '2026-07-15';

        // Fixed, apostrophe-free names: the packing payload encodes labels
        // via @js (apostrophes become \u0027), so faker names like
        // "O'Conner ..." make raw-string assertions flake.
        $this->farmA = Producer::factory()->create();
        $this->farmA->translations()->update(['name' => 'Parity Farm Alpha']);
        $this->farmA = $this->farmA->fresh();
        $this->farmB = Producer::factory()->create();
        $this->farmB->translations()->update(['name' => 'Parity Farm Beta']);
        $this->farmB = $this->farmB->fresh();

        $this->stockedA = Product::factory()->create([
            'producer_id' => $this->farmA->id,
            'unit' => ProductUnit::Mass->value, 'price_display_unit' => 'kg',
        ]);
        $this->inflowlessA = Product::factory()->create([
            'producer_id' => $this->farmA->id,
            'unit' => ProductUnit::Mass->value, 'price_display_unit' => 'kg',
        ]);
        $this->stockedB = Product::factory()->create([
            'producer_id' => $this->farmB->id,
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
        ]);

        ProductStockInflow::create([
            'product_id' => $this->stockedA->id, 'producer_id' => $this->farmA->id,
            'received_on' => $this->date, 'quantity' => 10000, 'freshness_window_days' => 2,
        ]);
        ProductStockInflow::create([
            'product_id' => $this->stockedB->id, 'producer_id' => $this->farmB->id,
            'received_on' => $this->date, 'quantity' => 50, 'freshness_window_days' => 2,
        ]);

        // B2C order: 2 kg stocked A + 1 kg inflowless A + 3 pieces B.
        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => $this->date]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $this->stockedA->id,
            'quantity' => 2000, 'unit' => 'g', 'line_total_eur' => 12.0, 'vat_amount_eur' => 0.79,
        ]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $this->inflowlessA->id,
            'quantity' => 1000, 'unit' => 'g', 'line_total_eur' => 6.0, 'vat_amount_eur' => 0.39,
        ]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $this->stockedB->id,
            'quantity' => 3, 'unit' => 'piece', 'line_total_eur' => 9.0, 'vat_amount_eur' => 0.59,
        ]);

        // B2B order: 5 pieces of B, confirmed.
        $profile = RestaurantProfile::factory()->verified()->create();
        $b2b = RestaurantOrder::create([
            'user_id' => $profile->user_id, 'status' => 'confirmed', 'kind' => 'order',
            'expected_delivery_date' => $this->date,
            'delivery_street' => 'S 1', 'delivery_postal_code' => '10115', 'delivery_city' => 'Berlin',
            'items_total_net_eur' => 14.02, 'items_total_gross_eur' => 15.0, 'vat_total_eur' => 0.98,
        ]);
        $b2b->items()->create([
            'product_id' => $this->stockedB->id, 'quantity' => 5, 'unit' => 'piece',
            'track' => 'reserved', 'line_status' => 'confirmed',
            'unit_price_snapshot_eur' => 3, 'vat_rate_snapshot_percent' => 7,
            'line_total_eur' => 15.0, 'vat_amount_eur' => 0.98,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    /** @return array<int, array<string, mixed>> */
    private function farmerRows(int $producerId): array
    {
        $orders = ShopOrder::query()->finalized()
            ->with(['items.product.producer.translations', 'items.product.translations', 'items.product.variants'])
            ->orderBy('expected_delivery_date')->orderBy('id')
            ->get();

        $method = new ReflectionMethod(AdminShopOrderController::class, 'buildFarmerOrderRows');

        return $method->invoke(app(AdminShopOrderController::class), $orders, $producerId);
    }

    public function test_farmer_rows_match_primary_producer_attribution(): void
    {
        // Farm A gets BOTH its products: the stocked one AND the one without
        // any inflow rows (the fallback case the refactor must preserve).
        $rowsA = $this->farmerRows($this->farmA->id);
        $namesA = array_column($rowsA, 'product_name');
        sort($namesA);
        $expectedA = [$this->inflowlessA->name, $this->stockedA->name];
        sort($expectedA);
        $this->assertSame($expectedA, $namesA);
        // Rows now carry a natural-unit amount + unit pair (Product | Preis |
        // Unit | Amount) instead of the old numeric qty_display. 2 kg / 1 kg.
        $byName = [];
        foreach ($rowsA as $row) {
            $byName[$row['product_name']] = $row;
        }
        $this->assertSame('2', $byName[$this->stockedA->name]['amount']);
        $this->assertSame('kg', $byName[$this->stockedA->name]['unit']);
        $this->assertSame('1', $byName[$this->inflowlessA->name]['amount']);
        $this->assertSame('kg', $byName[$this->inflowlessA->name]['unit']);

        // Farm B gets only its product; B2C and B2B quantities are NOT merged
        // here (farmer rows come from the shop-order set only, as today).
        $rowsB = $this->farmerRows($this->farmB->id);
        $this->assertCount(1, $rowsB);
        $this->assertSame($this->stockedB->name, $rowsB[0]['product_name']);
        $this->assertSame('3', $rowsB[0]['amount']);
    }

    public function test_farmer_dropdown_lists_exactly_the_two_farms(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('app.admin.shop-orders.farmer-export.producers'))
            ->assertOk()
            ->json();

        $ids = array_column($response, 'id');
        sort($ids);
        $this->assertSame([$this->farmA->id, $this->farmB->id], $ids);
    }

    public function test_farm_totals_producer_column_matches_primary_producers(): void
    {
        $orders = ShopOrder::query()->finalized()
            ->with(['items.product.producer.translations', 'items.product.translations'])
            ->get();
        $restaurantOrders = RestaurantOrder::query()
            ->whereIn('status', ['confirmed', 'fulfilled'])
            ->with(['user.restaurantProfile', 'items.product.producer.translations', 'items.product.translations'])
            ->get();

        $rows = (new ShopOrderFarmTotalsSheet($orders, $restaurantOrders))->array();

        $producerByProduct = [];
        $qtyByProduct = [];
        foreach ($rows as $row) {
            $producerByProduct[$row[1]] = $row[0];
            $qtyByProduct[$row[1]] = $row[2];
        }

        $this->assertSame($this->farmA->name, $producerByProduct[$this->stockedA->name]);
        $this->assertSame($this->farmA->name, $producerByProduct[$this->inflowlessA->name]);
        $this->assertSame($this->farmB->name, $producerByProduct[$this->stockedB->name]);
        // B2C 3 + B2B 5 pieces merge into one product bucket.
        $this->assertEqualsWithDelta(8.0, $qtyByProduct[$this->stockedB->name], 0.001);
        $this->assertEqualsWithDelta(2.0, $qtyByProduct[$this->stockedA->name], 0.001);
    }

    public function test_packing_page_labels_groups_with_their_producers(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)
            ->get(route('app.admin.packing.index', ['date' => $this->date]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($this->farmA->name, $html);
        $this->assertStringContainsString($this->farmB->name, $html);
    }
}
