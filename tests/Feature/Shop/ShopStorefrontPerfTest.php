<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\ProductStockInflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Guards the storefront client-performance safeguards that keep product-heavy
 * days from crashing low-RAM phones (a large reactive catalog + eager per-card
 * quantity option lists blew past mobile Safari's memory ceiling). These markers
 * being present is the regression fence — if a refactor drops them, heavy days
 * regress silently.
 */
class ShopStorefrontPerfTest extends TestCase
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

    private function stockSingleVariantProduct(): Product
    {
        $product = Product::factory()->create([
            'category' => 'produce',
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 4.00,
            'is_available' => true,
            'is_published' => true,
        ]);

        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 10,
            'freshness_window_days' => 1,
        ]);

        return $product;
    }

    public function test_storefront_keeps_the_catalog_out_of_alpine_reactive_state(): void
    {
        $this->stockSingleVariantProduct();

        $html = $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->getContent();

        // Fix 2: the (large, read-only) catalog lives in a closure const, not on
        // the reactive x-data object, so Alpine never deep-proxies it.
        $this->assertStringContainsString('const products = config.products', $html);
        $this->assertStringNotContainsString('products: config.products', $html);
    }

    public function test_single_variant_quantity_options_render_lazily(): void
    {
        $this->stockSingleVariantProduct();

        $html = $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->getContent();

        // Fix 1: the up-to-60-entry <option> list is gated behind stepsOpen, so
        // Alpine only materialises it when the customer opens that dropdown.
        $this->assertStringContainsString('stepsOpen', $html);
        $this->assertStringContainsString('stepsOpen ? cardStepOptions', $html);
    }

    public function test_shop_does_not_use_content_visibility_on_cards(): void
    {
        $this->stockSingleVariantProduct();

        $html = $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->getContent();

        // content-visibility:auto was removed: on older mobile WebKit/Chrome it
        // left product cards' boxes unpainted (blank grid) on product-heavy days.
        // The de-reactive catalog + lazy dropdowns are the real crash fix.
        $this->assertStringNotContainsString('content-visibility', $html);
    }

    public function test_product_grid_is_render_limited(): void
    {
        $this->stockSingleVariantProduct();

        $html = $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->getContent();

        // Cards are gated by the render limit (only the first N paint; the rest
        // stay display:none so their images never load on low-end phones), with
        // a "show more" control to reveal the remainder.
        $this->assertStringContainsString('cardOnGrid(', $html);
        $this->assertStringContainsString('showMoreProducts()', $html);
        $this->assertStringContainsString('data-testid="shop-show-more"', $html);
    }
}
