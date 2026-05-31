<?php

namespace Tests\Feature\Marketing;

use App\Models\Producer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the public product detail page at /products/{product}: it renders
 * dynamically from admin-entered product fields (name, origin, variety,
 * taste profile, category, tags, gallery) plus pairings — without any price
 * or availability information — and uses the shared marketing chrome.
 */
class ProductDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private function publishedProduct(string $category = 'produce'): Product
    {
        return Product::factory()->create([
            'category' => $category,
            'is_available' => true,
            'is_published' => true,
        ]);
    }

    public function test_detail_page_renders_core_fields_without_price(): void
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
            'variety' => 'Grün',
            'taste_profile' => 'Fresh and nutty with a clear note of asparagus.',
        ]);
        $product = $product->fresh();

        $response = $this->get(route('public.products.show', $product));

        $response->assertOk();
        $response->assertSee('data-testid="customer-header-marketing"', false);
        // Hero copy: name, eyebrow, meta-line, taste profile.
        $response->assertSee('Organic', false);
        $response->assertSee('Asparagus', false);
        $response->assertSee(__('product.detail.eyebrow'), false);
        $response->assertSee('Spreewald, Brandenburg', false);
        $response->assertSee('Grün', false);
        $response->assertSee('Fresh and nutty with a clear note of asparagus.', false);
        // Producer name links to the producer detail page.
        $response->assertSee(route('public.producers.show', $producer), false);
        $response->assertSee('Spargelhof Kalkwitz', false);
        // Head title: "{name} · Product · Erdgut".
        $response->assertSee('Organic Green Asparagus · '.__('product.detail.eyebrow'), false);
        // No price anywhere on the public page.
        $response->assertDontSee($product->displayPrice(), false);
    }

    public function test_empty_tag_blocks_show_fallback(): void
    {
        $product = $this->publishedProduct();
        $product->update(['taste_profile_tags' => [], 'dietary_tags' => [], 'allergens' => []]);

        $response = $this->get(route('public.products.show', $product->fresh()));

        $response->assertOk();
        // Taste / Dietary / Allergens eyebrows always render.
        $response->assertSee(__('product.detail.taste_label'), false);
        $response->assertSee(__('product.detail.dietary_label'), false);
        $response->assertSee(__('Allergens'), false);
        // Fallback label for empty arrays.
        $response->assertSee(__('product.detail.allergens_none'), false);
    }

    public function test_gallery_hidden_when_no_images(): void
    {
        $product = $this->publishedProduct();

        $response = $this->get(route('public.products.show', $product));

        $response->assertOk();
        $response->assertDontSee('data-testid="product-gallery"', false);
    }

    public function test_pairings_section_lists_related_products_and_links(): void
    {
        $product = $this->publishedProduct();

        $pair = $this->publishedProduct();
        $pair->translations()->where('locale', 'en')->update(['name' => 'Organic White Asparagus']);
        $product->pairings()->attach($pair->id);

        $response = $this->get(route('public.products.show', $product->fresh()));

        $response->assertOk();
        $response->assertSee('data-testid="product-pairings"', false);
        $response->assertSee('Organic White Asparagus', false);
        $response->assertSee(route('public.products.show', $pair), false);
    }

    public function test_pairings_section_hidden_when_empty(): void
    {
        $product = $this->publishedProduct();

        $response = $this->get(route('public.products.show', $product));

        $response->assertOk();
        $response->assertDontSee('data-testid="product-pairings"', false);
    }

    public function test_unpublished_product_returns_404(): void
    {
        $product = Product::factory()->create([
            'is_available' => true,
            'is_published' => false,
        ]);

        $this->get(route('public.products.show', $product))->assertNotFound();
    }
}
