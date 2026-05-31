<?php

namespace Tests\Feature\Shop;

use App\Models\Producer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The preview shop is a hidden, self-contained surface: the storefront and
 * its catalog subpages (product + producer detail) must render WITHOUT the
 * marketing header/footer and must never link out into the rest of the
 * (still-unpublished) project. Every internal link stays inside /shop so a
 * visitor can't accidentally wander into the marketing site.
 *
 * These tests lock that isolation in place alongside the shared detail
 * markup reused from the public /products + /producers pages.
 */
class ShopCatalogIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function publishedProduct(): Product
    {
        return Product::factory()->create([
            'category' => 'produce',
            'is_available' => true,
            'is_published' => true,
        ]);
    }

    public function test_shop_product_page_is_isolated_and_links_stay_in_shop(): void
    {
        $producer = Producer::factory()->create();
        $producer->translations()->where('locale', 'en')->update(['name' => 'Spargelhof Kalkwitz']);

        $product = Product::factory()->for($producer->fresh())->create([
            'category' => 'produce',
            'is_available' => true,
            'is_published' => true,
        ]);
        $product->translations()->where('locale', 'en')->update([
            'name' => 'Organic Green Asparagus',
            'origin' => 'Spreewald, Brandenburg',
        ]);
        $product = $product->fresh();

        $response = $this->get(route('public.shop.products.show', $product));

        $response->assertOk();
        // Renders the shared detail body.
        $response->assertSee('Organic', false);
        $response->assertSee('Asparagus', false);
        $response->assertSee('Spreewald, Brandenburg', false);
        // No marketing chrome.
        $response->assertDontSee('data-testid="customer-header-marketing"', false);
        // Producer link points back INTO the shop, never the public producer page.
        $response->assertSee(route('public.shop.producers.show', $producer), false);
        $response->assertDontSee(route('public.producers.show', $producer), false);
        // No outbound links into the hidden rest-of-site.
        $response->assertDontSee('href="'.route('public.products.index').'"', false);
        $response->assertDontSee('href="'.route('public.marketing-home').'"', false);
    }

    public function test_shop_product_pairings_link_within_shop(): void
    {
        $product = $this->publishedProduct();

        $pair = $this->publishedProduct();
        $pair->translations()->where('locale', 'en')->update(['name' => 'Organic White Asparagus']);
        $product->pairings()->attach($pair->id);

        $response = $this->get(route('public.shop.products.show', $product->fresh()));

        $response->assertOk();
        $response->assertSee('data-testid="product-pairings"', false);
        $response->assertSee(route('public.shop.products.show', $pair), false);
        $response->assertDontSee(route('public.products.show', $pair), false);
    }

    public function test_shop_producer_page_is_isolated_and_cards_link_in_shop(): void
    {
        $producer = Producer::factory()->create();
        $producer->translations()->where('locale', 'en')->update(['name' => 'Spargelhof Kalkwitz']);

        $product = Product::factory()->for($producer->fresh())->create([
            'category' => 'produce',
            'is_available' => true,
            'is_published' => true,
        ]);
        $product->translations()->where('locale', 'en')->update(['name' => 'Organic Green Asparagus']);

        $response = $this->get(route('public.shop.producers.show', $producer->fresh()));

        $response->assertOk();
        $response->assertSee('Spargelhof Kalkwitz', false);
        $response->assertSee('data-testid="producer-products-grid"', false);
        // No marketing chrome.
        $response->assertDontSee('data-testid="customer-header-marketing"', false);
        // Product card links stay inside the shop.
        $response->assertSee(route('public.shop.products.show', $product), false);
        $response->assertDontSee(route('public.products.show', $product), false);
        // No outbound producers-index link.
        $response->assertDontSee('href="'.route('public.producers').'"', false);
    }

    public function test_shop_storefront_is_isolated(): void
    {
        $response = $this->get(route('public.shop.index'));

        $response->assertOk();
        $response->assertDontSee('data-testid="customer-header-marketing"', false);
    }

    public function test_unpublished_product_returns_404_in_shop(): void
    {
        $product = Product::factory()->create([
            'is_available' => true,
            'is_published' => false,
        ]);

        $this->get(route('public.shop.products.show', $product))->assertNotFound();
    }
}
