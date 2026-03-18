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
