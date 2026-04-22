<?php

namespace Tests\Feature\Marketing;

use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_producers_page_lists_producers_with_toolbar(): void
    {
        $a = Producer::factory()->create();
        $b = Producer::factory()->create();
        $a->translations()->where('locale', 'en')->update(['name' => 'Alpha Acres', 'region' => 'Berlin']);
        $b->translations()->where('locale', 'en')->update(['name' => 'Beta Fields', 'region' => 'Brandenburg']);

        $response = $this->get(route('public.producers'));

        $response->assertOk();
        $response->assertSee('Alpha Acres', false);
        $response->assertSee('Beta Fields', false);
        $response->assertSee('data-testid="producers-toolbar"', false);
        $response->assertSee('data-testid="producers-grid"', false);
        $response->assertSee('data-testid="producer-card"', false);
    }

    public function test_producers_page_filters_by_region(): void
    {
        $a = Producer::factory()->create();
        $b = Producer::factory()->create();
        $a->translations()->where('locale', 'en')->update(['name' => 'Berlin Grower', 'region' => 'Berlin']);
        $b->translations()->where('locale', 'en')->update(['name' => 'Rural Roots', 'region' => 'Brandenburg']);

        $response = $this->get(route('public.producers', ['region' => 'Berlin']));

        $response->assertOk();
        $response->assertSee('Berlin Grower', false);
        $response->assertDontSee('Rural Roots', false);
    }

    public function test_producers_page_search_matches_name(): void
    {
        $a = Producer::factory()->create();
        $b = Producer::factory()->create();
        $a->translations()->where('locale', 'en')->update(['name' => 'UniqueProducerXYZ', 'region' => 'Berlin']);
        $b->translations()->where('locale', 'en')->update(['name' => 'Other Farm', 'region' => 'Berlin']);

        $response = $this->get(route('public.producers', ['q' => 'UniqueProducer']));

        $response->assertOk();
        $response->assertSee('UniqueProducerXYZ', false);
        $response->assertDontSee('Other Farm', false);
    }

    public function test_producers_page_shows_global_empty_when_no_producers(): void
    {
        $response = $this->get(route('public.producers'));

        $response->assertOk();
        $response->assertSee('data-testid="empty-state"', false);
        $response->assertSee('No producers available yet', false);
    }

    public function test_producers_page_shows_filtered_empty_when_no_matches(): void
    {
        $p = Producer::factory()->create();
        $p->translations()->where('locale', 'en')->update(['name' => 'Solo Farm', 'region' => 'Berlin']);

        $response = $this->get(route('public.producers', ['q' => 'ZZNO_MATCH_ZZ']));

        $response->assertOk();
        $response->assertSee('data-testid="empty-state"', false);
        $response->assertSee('No producers match your filters', false);
    }

    public function test_guest_sees_signup_cta_on_cards(): void
    {
        $p = Producer::factory()->create();
        $p->translations()->where('locale', 'en')->update(['name' => 'Guest Card Farm']);

        $response = $this->get(route('public.producers'));

        $response->assertOk();
        $response->assertSee(route('auth.register'), false);
        $response->assertSee('data-testid="producer-card-signup-link"', false);
    }

    public function test_authenticated_user_sees_products_catalog_link(): void
    {
        $user = User::factory()->create();
        $p = Producer::factory()->create();
        $p->translations()->where('locale', 'en')->update(['name' => 'Products Link Farm']);

        $response = $this->actingAs($user)->get(route('public.producers'));

        $response->assertOk();
        $response->assertSee(route('app.products.index'), false);
        $response->assertSee('data-testid="producer-card-products-link"', false);
    }
}
