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
        Producer::factory()->create(['name' => 'Alpha Acres', 'region' => 'Berlin']);
        Producer::factory()->create(['name' => 'Beta Fields', 'region' => 'Brandenburg']);

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
        Producer::factory()->create(['name' => 'Berlin Grower', 'region' => 'Berlin']);
        Producer::factory()->create(['name' => 'Rural Roots', 'region' => 'Brandenburg']);

        $response = $this->get(route('public.producers', ['region' => 'Berlin']));

        $response->assertOk();
        $response->assertSee('Berlin Grower', false);
        $response->assertDontSee('Rural Roots', false);
    }

    public function test_producers_page_search_matches_name(): void
    {
        Producer::factory()->create(['name' => 'UniqueProducerXYZ', 'region' => 'Berlin']);
        Producer::factory()->create(['name' => 'Other Farm', 'region' => 'Berlin']);

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
        Producer::factory()->create(['name' => 'Solo Farm', 'region' => 'Berlin']);

        $response = $this->get(route('public.producers', ['q' => 'ZZNO_MATCH_ZZ']));

        $response->assertOk();
        $response->assertSee('data-testid="empty-state"', false);
        $response->assertSee('No producers match your filters', false);
    }

    public function test_guest_sees_signup_cta_on_cards(): void
    {
        Producer::factory()->create(['name' => 'Guest Card Farm']);

        $response = $this->get(route('public.producers'));

        $response->assertOk();
        $response->assertSee(route('auth.register'), false);
        $response->assertSee('data-testid="producer-card-signup-link"', false);
    }

    public function test_authenticated_user_sees_shop_link_with_producer_filter(): void
    {
        $user = User::factory()->create();
        $producer = Producer::factory()->create(['name' => 'Shop Link Farm']);

        $response = $this->actingAs($user)->get(route('public.producers'));

        $response->assertOk();
        $response->assertSee(route('app.shop.index', ['producer' => $producer->id]), false);
        $response->assertSee('data-testid="producer-card-shop-link"', false);
    }

    public function test_authenticated_user_does_not_see_shop_link_when_shop_disabled(): void
    {
        config(['features.shop_extras_enabled' => false]);

        $user = User::factory()->create();
        Producer::factory()->create(['name' => 'No Shop Farm']);

        $response = $this->actingAs($user)->get(route('public.producers'));

        $response->assertOk();
        $response->assertDontSee('data-testid="producer-card-shop-link"', false);
        $response->assertSee('Shop extras are not available right now.', false);
    }
}
