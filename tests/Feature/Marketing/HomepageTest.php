<?php

namespace Tests\Feature\Marketing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_for_guest_with_primary_and_secondary_ctas(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('Fresh weekly boxes from local farms, delivered transparently.', false);
        $response->assertSee('How it works', false);
        $response->assertSee('Join Berlin households supporting local food', false);
        $response->assertSee(route('public.about'), false);
        $response->assertSee(route('auth.login'), false);
        $response->assertSee(route('app.onboarding.start', ['intent' => 'trial']), false);
        $response->assertSee(route('app.onboarding.start', ['intent' => 'subscription']), false);

        $response->assertSee('data-testid="home-cta-try-box"', false);
        $response->assertSee('data-testid="home-cta-subscribe"', false);
        $response->assertSee('data-testid="home-secondary-cta-login"', false);
        $response->assertSee('data-testid="home-trust-row"', false);
        $response->assertSee('data-testid="home-how-it-works"', false);
        $response->assertSee('data-testid="home-social-proof"', false);
        $response->assertSee('data-testid="home-value-cards"', false);
        $response->assertSee('data-testid="home-bottom-primary-guest"', false);
    }

    public function test_homepage_renders_for_authenticated_user_with_dashboard_and_subscription_links(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('public.home'));

        $response->assertOk();
        $response->assertSee(route('app.dashboard'), false);
        $response->assertSee(route('app.onboarding.start', ['intent' => 'trial']), false);
        $response->assertSee(route('app.onboarding.start', ['intent' => 'subscription']), false);
        $response->assertSee('data-testid="home-primary-cta-authenticated"', false);
        $response->assertSee('data-testid="home-cta-try-box"', false);
        $response->assertSee('data-testid="home-cta-subscribe"', false);
        $response->assertSee('data-testid="home-bottom-primary-authenticated"', false);
    }
}
