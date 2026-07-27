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

    public function test_shop_storefront_renders_full_marketing_footer(): void
    {
        $this->get(route('public.shop.index'))
            ->assertOk()
            ->assertSee(__('footer.tagline'), false)
            ->assertSee('instagram.com/erdgut.berlin', false)
            ->assertSee('href="'.route('public.imprint').'"', false);
    }

    public function test_shop_storefront_is_isolated(): void
    {
        $response = $this->get(route('public.shop.index'));

        $response->assertOk();
        $response->assertDontSee('data-testid="customer-header-marketing"', false);
    }

    public function test_shop_products_index_is_isolated_and_links_stay_in_shop(): void
    {
        $product = $this->publishedProduct();
        $product->translations()->where('locale', 'en')->update(['name' => 'Organic Green Asparagus']);
        $producer = $product->producer;
        $this->assertNotNull($producer);

        $response = $this->get(route('public.shop.products.index'));

        $response->assertOk();
        $response->assertSee('data-testid="products-grid"', false);
        // Product cards + category rail link inside the shop.
        $response->assertSee(route('public.shop.products.show', $product->fresh()), false);
        $response->assertSee('href="'.route('public.shop.products.index').'"', false);
        // Farm name on the card links into the shop producer page.
        $response->assertSee(route('public.shop.producers.show', $producer->fresh()), false);
        $response->assertDontSee(route('public.producers.show', $producer->fresh()), false);
        // Never the marketing catalogue or the app/dashboard catalogue.
        $response->assertDontSee('data-testid="customer-header-marketing"', false);
        $response->assertDontSee('href="'.route('public.products.index').'"', false);
        $response->assertDontSee(route('public.products.show', $product->fresh()), false);
        $response->assertDontSee('href="'.route('public.marketing-home').'"', false);
    }

    public function test_shop_producers_index_is_isolated_and_links_stay_in_shop(): void
    {
        $producer = Producer::factory()->create();
        $producer->translations()->where('locale', 'en')->update(['name' => 'Spargelhof Kalkwitz']);

        $response = $this->get(route('public.shop.producers.index'));

        $response->assertOk();
        $response->assertSee('Spargelhof Kalkwitz', false);
        $response->assertSee('data-testid="producers-grid"', false);
        // Cards + filter form link inside the shop; the per-card products
        // link goes to the shop catalogue, not the app dashboard.
        $response->assertSee(route('public.shop.producers.show', $producer->fresh()), false);
        $response->assertSee('href="'.route('public.shop.products.index').'"', false);
        $response->assertSee('href="'.route('public.shop-contact').'"', false);
        // No marketing / auth / dashboard / contact links escape the shop.
        $response->assertDontSee('data-testid="customer-header-marketing"', false);
        $response->assertDontSee('href="'.route('public.producers').'"', false);
        $response->assertDontSee(route('public.producers.show', $producer->fresh()), false);
        $response->assertDontSee(route('app.products.index'), false);
        // Neither the box consumer sign-up nor a login escape the shop header;
        // the B2B login lives only on the Geschäftskunden page.
        $response->assertDontSee(route('auth.register'), false);
        $response->assertDontSee(route('auth.login'), false);
        $response->assertDontSee('href="'.route('public.contact').'"', false);
    }

    public function test_shop_header_nav_links_into_catalog_on_every_page(): void
    {
        // The shop surface shares one nav: Startseite · Über uns · Höfe ·
        // Produkte · Hofladen · Kontakt, present on the storefront and on
        // every catalogue page. The logo points back to the landing page.
        foreach (['public.shop.index', 'public.shop.products.index', 'public.shop.producers.index'] as $route) {
            $this->get(route($route))
                ->assertOk()
                // Same shared shop header as the landing / About / Kontakt pages.
                ->assertSee('data-testid="customer-header-shop"', false)
                ->assertSee('href="'.route('public.shop.products.index').'"', false)
                ->assertSee('href="'.route('public.shop.producers.index').'"', false)
                ->assertSee('href="'.route('public.home').'"', false)
                ->assertSee('href="'.route('public.shop-about').'"', false)
                ->assertSee('href="'.route('public.shop-contact').'"', false)
                // Still no box pages or account chrome on the shop surface.
                ->assertDontSee('href="'.route('public.marketing-home').'"', false)
                ->assertDontSee('href="'.route('auth.register').'"', false);
        }
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
