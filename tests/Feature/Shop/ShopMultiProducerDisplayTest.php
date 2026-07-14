<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ProductVariant;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Shop\ShopOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Customer-facing multi-producer display (P4): storefront cards name the
 * farms with sellable stock on the selected delivery date; order documents
 * and status pages name the farms the allocation says actually deliver.
 */
class ShopMultiProducerDisplayTest extends TestCase
{
    use RefreshDatabase;

    private const DELIVERY_DATE = '2026-06-05';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-30 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param  array<int, float>  $stockByProducerId */
    private function productWithFarms(Producer $primary, array $farmIds, array $stockByProducerId): Product
    {
        $product = Product::factory()->create([
            'producer_id' => $primary->id,
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 4.00,
            'is_available' => true,
            'is_published' => true,
        ]);
        $product->syncProducers($farmIds);

        foreach ($stockByProducerId as $producerId => $qty) {
            ProductStockInflow::create([
                'product_id' => $product->id,
                'producer_id' => $producerId,
                'received_on' => self::DELIVERY_DATE,
                'quantity' => $qty,
                'freshness_window_days' => 1,
            ]);
        }

        return $product;
    }

    public function test_shop_card_links_every_farm_with_stock(): void
    {
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $this->productWithFarms($farmA, [$farmA->id, $farmB->id], [
            $farmA->id => 4,
            $farmB->id => 50,
        ]);

        $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertSee(route('public.shop.producers.show', $farmA), false)
            ->assertSee(route('public.shop.producers.show', $farmB), false)
            ->assertSee($farmA->name)
            ->assertSee($farmB->name);
    }

    public function test_depleted_farm_disappears_from_the_card(): void
    {
        // Farm A's 4 pieces are fully committed (it is drained first: equal
        // anchor and breadth, lower id) — only farm B still sells.
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = $this->productWithFarms($farmA, [$farmA->id, $farmB->id], [
            $farmA->id => 4,
            $farmB->id => 50,
        ]);
        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => self::DELIVERY_DATE]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 4, 'unit' => 'piece',
        ]);

        $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertDontSee(route('public.shop.producers.show', $farmA), false)
            ->assertSee(route('public.shop.producers.show', $farmB), false);
    }

    public function test_more_than_three_farms_collapse_to_the_multiple_label(): void
    {
        $farms = Producer::factory()->count(4)->create();
        $this->productWithFarms(
            $farms->first(),
            $farms->pluck('id')->all(),
            $farms->mapWithKeys(fn (Producer $f): array => [$f->id => 10.0])->all(),
        );

        $response = $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertSee(__('shop.producers_multiple'));

        foreach ($farms as $farm) {
            $response->assertDontSee(route('public.shop.producers.show', $farm), false);
        }
    }

    public function test_single_farm_product_renders_the_primary_as_before(): void
    {
        $farm = Producer::factory()->create();
        $this->productWithFarms($farm, [$farm->id], [$farm->id => 10]);

        $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertSee(route('public.shop.producers.show', $farm), false)
            ->assertSee($farm->name);
    }

    public function test_grade_rows_carry_their_own_farms(): void
    {
        // "Standard" only from farm A, the named grade only from farm B:
        // each grade row lists just its farm, the card union shows both.
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = $this->productWithFarms($farmA, [$farmA->id, $farmB->id], [
            $farmA->id => 10,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id, 'name' => ['de' => 'Premium'],
        ]);
        ProductStockInflow::create([
            'product_id' => $product->id, 'producer_id' => $farmB->id, 'variant_id' => $variant->id,
            'received_on' => self::DELIVERY_DATE, 'quantity' => 6, 'freshness_window_days' => 1,
        ]);

        $row = app(ShopOrderService::class)
            ->weeklyProductsForDate(Carbon::parse(self::DELIVERY_DATE))
            ->firstWhere(fn (array $r): bool => $r['product']->id === $product->id);

        $this->assertNotNull($row);
        $this->assertSame([$farmA->id], collect($row['gradeProducers'][0])->pluck('id')->all());
        $this->assertSame([$farmB->id], collect($row['gradeProducers'][$variant->id])->pluck('id')->all());
        // The card union names both farms.
        $this->assertEqualsCanonicalizing(
            [$farmA->id, $farmB->id],
            collect($row['producers'])->pluck('id')->all(),
        );
    }

    public function test_track_page_names_the_farm_that_actually_delivers(): void
    {
        // Primary is farm A, but the only recorded inflow for the date comes
        // from farm B — the tracking page must say farm B.
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = $this->productWithFarms($farmA, [$farmA->id, $farmB->id], [
            $farmB->id => 50,
        ]);
        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => self::DELIVERY_DATE]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 3, 'unit' => 'piece',
        ]);

        $this->get(ShopOrderService::trackUrl($order))
            ->assertOk()
            ->assertSee($farmB->name)
            ->assertDontSee($farmA->name);
    }

    public function test_receipt_pdf_receives_the_allocation_based_names(): void
    {
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = $this->productWithFarms($farmA, [$farmA->id, $farmB->id], [
            $farmB->id => 50,
        ]);
        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => self::DELIVERY_DATE]);
        ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 3, 'unit' => 'piece',
        ]);

        $captured = null;
        $fakePdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $fakePdf->shouldReceive('setPaper')->andReturnSelf();
        $fakePdf->shouldReceive('download')
            ->andReturn(response()->make('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function (string $view, array $data) use (&$captured, $fakePdf) {
            $captured = $data;

            return $fakePdf;
        });

        $this->get(ShopOrderService::receiptUrl($order))->assertOk();

        $this->assertSame($farmB->name, $captured['producerNames'][$product->id.'|0'] ?? null);
    }
}
