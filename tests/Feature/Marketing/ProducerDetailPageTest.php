<?php

namespace Tests\Feature\Marketing;

use App\Models\Producer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the public producer detail page at /producers/{slug}: it is built
 * entirely from the admin-entered producer fields plus the producer's
 * catalog-visible products, is reachable by slug, and is linked from both the
 * producers index and the shop storefront.
 */
class ProducerDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private function producerNamed(string $name, string $region = 'Berlin', ?string $description = 'A small family farm.'): Producer
    {
        $producer = Producer::factory()->create();
        $producer->translations()->where('locale', 'en')->update([
            'name' => $name,
            'region' => $region,
            'description' => $description,
        ]);

        return $producer->fresh();
    }

    public function test_detail_page_renders_by_slug_with_core_fields(): void
    {
        $producer = $this->producerNamed('Kalkwitz Asparagus', 'Spreewald', 'Sandy soil, slow asparagus.');

        $this->assertNotNull($producer->slug);

        $response = $this->get(route('public.producers.show', $producer));

        $response->assertOk();
        $response->assertSee('Kalkwitz', false);
        $response->assertSee('Asparagus', false);
        $response->assertSee('Spreewald', false);
        $response->assertSee('Sandy soil, slow asparagus.', false);
        // Breadcrumb back to the listing.
        $response->assertSee(route('public.producers'), false);
        // Head title: "{name} · Producer · Erdgut".
        $response->assertSee('Kalkwitz Asparagus · Producer', false);
    }

    public function test_slug_is_url_safe_kebab_case(): void
    {
        $producer = $this->producerNamed('Größe & Söhne Hof');

        $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $producer->slug);

        $this->get('/producers/'.$producer->slug)->assertOk();
    }

    public function test_detail_page_lists_only_visible_products(): void
    {
        $producer = $this->producerNamed('Mixed Catalog Farm');

        $visible = Product::factory()->for($producer)->create(['is_available' => true, 'is_published' => true]);
        $visible->translations()->where('locale', 'en')->update(['name' => 'Visible Carrot']);

        $draft = Product::factory()->for($producer)->create(['is_available' => true, 'is_published' => false]);
        $draft->translations()->where('locale', 'en')->update(['name' => 'Hidden Draft Beet']);

        $unavailable = Product::factory()->for($producer)->create(['is_available' => false, 'is_published' => true]);
        $unavailable->translations()->where('locale', 'en')->update(['name' => 'Out Of Stock Leek']);

        $response = $this->get(route('public.producers.show', $producer));

        $response->assertOk();
        $response->assertSee('Visible Carrot', false);
        $response->assertDontSee('Hidden Draft Beet', false);
        $response->assertDontSee('Out Of Stock Leek', false);
        // "In the box" section heading interpolates the full producer name.
        $response->assertSee('Mixed', false);
        // Products render as full cards (image + price), linking to the detail page.
        $response->assertSee('data-testid="producer-products-grid"', false);
        $response->assertSee('data-testid="producer-product-card"', false);
        $response->assertSee(route('public.products.show', $visible), false);
        $response->assertSee($visible->displayPrice(), false);
    }

    public function test_detail_page_lists_products_supplied_as_secondary_farm(): void
    {
        // The product's PRIMARY producer is another farm; this farm only
        // supplies it via a secondary pivot row — it must still appear.
        $secondary = $this->producerNamed('Secondary Supplier Farm');
        $primary = Producer::factory()->create();

        $product = Product::factory()->for($primary)->create(['is_available' => true, 'is_published' => true]);
        $product->translations()->where('locale', 'en')->update(['name' => 'Shared Parsnip']);
        $product->syncProducers([$primary->id, $secondary->id]);

        $this->get(route('public.producers.show', $secondary))
            ->assertOk()
            ->assertSee('Shared Parsnip', false)
            ->assertSee('data-testid="producer-product-card"', false);
    }

    public function test_products_section_hidden_when_no_visible_products(): void
    {
        $producer = $this->producerNamed('No Products Farm');

        Product::factory()->for($producer)->create(['is_published' => false]);

        $response = $this->get(route('public.producers.show', $producer));

        $response->assertOk();
        $response->assertDontSee('producer-products-heading', false);
    }

    public function test_missing_description_shows_placeholder(): void
    {
        $producer = $this->producerNamed('Quiet Farm', 'Berlin', null);

        $response = $this->get(route('public.producers.show', $producer));

        $response->assertOk();
        $response->assertSee(__('producer.detail.description_missing'), false);
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->get('/producers/this-slug-does-not-exist')->assertNotFound();
    }

    public function test_producers_index_links_to_detail_page(): void
    {
        $producer = $this->producerNamed('Linked From Index Farm');

        $response = $this->get(route('public.producers'));

        $response->assertOk();
        $response->assertSee(route('public.producers.show', $producer), false);
        $response->assertSee('data-testid="producer-card-profile-link"', false);
    }
}
