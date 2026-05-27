<?php

namespace Tests\Feature\Marketing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_renders_coming_soon_page(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('data-testid="coming-soon"', false);
        // Translated strings (test suite runs with APP_LOCALE=en).
        // The headline is split: "Fresh produce is" + <span italic> "on its way."
        // so we assert both parts independently.
        $response->assertSee('Fresh produce is', false);
        $response->assertSee('on its way.', false);
        $response->assertSee('producer', false);
        $response->assertSee('kontakt@erd-gut.de', false);

        // Coming-soon page must not leak the full marketing nav / CTAs.
        $response->assertDontSee('data-testid="home-cta-try-box"', false);
        $response->assertDontSee('data-testid="home-cta-subscribe"', false);

        // No social links on the holding page (we don't have those channels yet).
        $response->assertDontSee('instagram.com', false);
    }

    public function test_marketing_home_renders_for_guest_with_primary_and_secondary_ctas(): void
    {
        $response = $this->get(route('public.marketing-home'));

        $response->assertOk();
        // New editorial hero + founder letter + chapter strip copy.
        $response->assertSee('Weekly from small farms, in the rhythm of the seasons.', false);
        $response->assertSee('Why we started Erdgut.', false);
        $response->assertSee('Seven chapters, one season.', false);
        $response->assertSee(route('public.about'), false);
        $response->assertSee(route('public.producers'), false);
        $response->assertSee(route('auth.login'), false);
        $response->assertSee(route('app.onboarding.start', ['intent' => 'trial']), false);
        $response->assertSee(route('app.onboarding.start', ['intent' => 'subscription']), false);

        $response->assertSee('data-testid="home-cta-try-box"', false);
        $response->assertSee('data-testid="home-cta-subscribe"', false);
        $response->assertSee('data-testid="home-secondary-cta-login"', false);
        $response->assertSee('data-testid="home-founder-letter"', false);
        $response->assertSee('data-testid="home-chapter-strip"', false);
        $response->assertSee('data-testid="home-how-it-works"', false);
        $response->assertSee('data-testid="home-social-proof"', false);
        $response->assertSee('data-testid="home-value-cards"', false);
        $response->assertSee('data-testid="home-bottom-primary-guest"', false);
    }

    public function test_marketing_home_renders_for_authenticated_user_with_dashboard_and_subscription_links(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('public.marketing-home'));

        $response->assertOk();
        $response->assertSee(route('app.dashboard'), false);
        $response->assertSee(route('app.onboarding.start', ['intent' => 'trial']), false);
        $response->assertSee(route('app.onboarding.start', ['intent' => 'subscription']), false);
        $response->assertSee('data-testid="home-primary-cta-authenticated"', false);
        $response->assertSee('data-testid="home-cta-try-box"', false);
        $response->assertSee('data-testid="home-cta-subscribe"', false);
        $response->assertSee('data-testid="home-bottom-primary-authenticated"', false);
    }

    public function test_marketing_home_nav_home_link_points_to_marketing_home_not_coming_soon(): void
    {
        $response = $this->get(route('public.marketing-home'));

        $response->assertOk();
        // The "Home" link in the marketing nav should route back to /home,
        // not to the coming-soon page at /, so authenticated visitors can
        // navigate the real site without bouncing through the holding page.
        $response->assertSee('href="'.route('public.marketing-home').'"', false);
    }
}
