<?php

namespace Tests\Feature\Onboarding;

use App\Models\User;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingStartTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_route_creates_draft_and_redirects_to_account_step_for_guest(): void
    {
        $this->get(route('app.onboarding.start', ['intent' => 'trial']))
            ->assertRedirect(route('app.onboarding.step.account'));

        $this->assertDatabaseHas('onboarding_drafts', [
            'intent' => OnboardingDraftManager::INTENT_TRIAL,
            'step' => OnboardingDraftManager::STEP_ACCOUNT,
        ]);
    }

    public function test_trial_intent_is_forced_to_subscription_when_feature_flag_is_off(): void
    {
        config(['features.trial_orders_enabled' => false]);

        $this->get(route('app.onboarding.start', ['intent' => 'trial']))
            ->assertRedirect(route('app.onboarding.step.account'));

        $this->assertDatabaseHas('onboarding_drafts', [
            'intent' => OnboardingDraftManager::INTENT_SUBSCRIPTION,
        ]);
    }

    public function test_legacy_subscription_onboard_route_redirects_to_wizard(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get('/app/subscriptions/onboard')
            ->assertRedirect(route('app.onboarding.start', ['intent' => 'subscription']));
    }
}
