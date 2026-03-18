<?php

namespace Tests\Feature\Marketing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_marketing_copy_and_guest_cta_links(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('Farm-to-consumer in Berlin', false);
        $response->assertSee('Fresh weekly boxes from local farms, delivered transparently.', false);
        $response->assertSee('Transparent sourcing', false);
        $response->assertSee('Flexible weekly deliveries', false);
        $response->assertSee('Fair pricing for farmers', false);
        $response->assertSee('Each product is linked to its producer and region', false);
        $response->assertSee('Customers can manage delivery preferences and adjust upcoming boxes', false);
        $response->assertSee('Erdgut emphasizes a direct relationship between producer and household', false);
        $response->assertSee('sm:px-6', false);
        $response->assertSee('md:grid-cols-3', false);
        $response->assertSee('lg:px-8', false);
        $response->assertSee(route('public.about'), false);
        $response->assertSee(route('auth.register'), false);
        $response->assertSee(route('auth.login'), false);
    }

    public function test_homepage_shows_dashboard_link_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('public.home'));

        $response->assertOk();
        $response->assertSee(route('app.dashboard'), false);
    }
}
