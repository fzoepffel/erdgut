<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWelcomeCtasTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_dashboard_shows_both_subscription_and_trial_ctas(): void
    {
        config(['features.trial_orders_enabled' => true]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertSee('data-testid="welcome-cta-subscribe"', false);
        $response->assertSee('data-testid="welcome-cta-try-box"', false);
        $response->assertSee(route('app.onboarding.start', ['intent' => 'subscription']), false);
        $response->assertSee(route('app.onboarding.start', ['intent' => 'trial']), false);
    }

    public function test_welcome_dashboard_hides_trial_cta_when_feature_flag_is_off(): void
    {
        config(['features.trial_orders_enabled' => false]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertSee('data-testid="welcome-cta-subscribe"', false);
        $response->assertDontSee('data-testid="welcome-cta-try-box"', false);
    }

    public function test_address_step_renders_for_authenticated_user(): void
    {
        // Regression: the onboarding layout previously called route('logout') /
        // route('login'), which are not defined in this project (auth.logout / auth.login).
        // That caused every wizard step to 500 on render.
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('app.onboarding.step.address'))
            ->assertOk();
    }
}
