<?php

namespace Tests\Feature\Shop;

use App\Enums\CountUnitLabel;
use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Restaurant\RestaurantCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Count-unit display words on the storefronts and documents: a labelled
 * piece product reads "Topf" (pot) everywhere "Stück" used to appear;
 * unlabelled products are untouched.
 */
class ShopCountUnitLabelTest extends TestCase
{
    use RefreshDatabase;

    private const DELIVERY_DATE = '2026-06-05';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-30 10:00:00'));
        app()->setLocale('de');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pieceProduct(?CountUnitLabel $label): Product
    {
        Producer::factory()->create();
        $product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 4.00,
            'is_available' => true,
            'is_published' => true,
            'count_unit_label' => $label?->value,
        ]);
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 10,
            'freshness_window_days' => 1,
        ]);

        return $product;
    }

    public function test_shop_index_shows_the_word_for_labelled_and_stueck_for_plain(): void
    {
        $this->pieceProduct(CountUnitLabel::Pot);
        $this->pieceProduct(null);

        $html = $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->getContent();

        // Prices live in the @js registry payload with escaped unicode;
        // the HTTP request renders in the test env's default locale (en).
        $plain = str_replace(['\\u20ac', '\\/'], ['€', '/'], $html);
        $this->assertStringContainsString('€/Pot', $plain);
        $this->assertStringContainsString('€/Piece', $plain);
    }

    public function test_b2b_catalog_grade_symbol_uses_the_word(): void
    {
        $labelled = $this->pieceProduct(CountUnitLabel::Bag);
        $plain = $this->pieceProduct(null);

        $rows = app(RestaurantCatalogService::class)
            ->productsForDate(Carbon::parse(self::DELIVERY_DATE))
            ->keyBy(fn (array $r) => $r['product']->id);

        $this->assertSame('Beutel', $rows[$labelled->id]['grades'][0]['unitSymbol']);
        $this->assertSame('Stück', $rows[$plain->id]['grades'][0]['unitSymbol']);
    }

    public function test_receipt_line_pluralizes_the_word(): void
    {
        $product = $this->pieceProduct(CountUnitLabel::Pot);
        $order = ShopOrder::factory()->paid()->create([
            'expected_delivery_date' => self::DELIVERY_DATE,
            'locale' => 'de',
        ]);
        $item = ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'piece',
        ]);

        $this->assertSame('2 Töpfe', $item->fresh()->displayQuantityLabel());
    }
}
