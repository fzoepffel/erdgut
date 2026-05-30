<?php

namespace Tests\Feature\Onboarding;

use App\Models\OnboardingDraft;
use App\Models\User;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wizard navigation taxonomy regression test (editorial redesign).
 *
 * The preferences-step forward button names the *next visible segment*:
 * "Continue to schedule" for subscription/undecided, "Continue to delivery"
 * for trial. The schedule-step back link uses a generic "Back" label, but its
 * href is still intent-aware — subscribers who skipped Commitment land back on
 * Preferences, while the undecided path returns to Commitment. This test pins
 * both contracts.
 */
class IntentAwareNavigationLabelsTest extends TestCase
{
    use RefreshDatabase;

    private function draftFor(User $user, string $intent, string $step, array $extraData = []): OnboardingDraft
    {
        return OnboardingDraft::create([
            'user_id' => $user->id,
            'intent' => $intent,
            'step' => $step,
            'data' => array_merge([
                'account_completed' => true,
                'address_completed' => true,
            ], $extraData),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function test_preferences_forward_button_points_to_schedule_for_subscription(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_SUBSCRIPTION, OnboardingDraftManager::STEP_PREFERENCES);

        $html = $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('onboarding.cta.to_schedule'), $html);
        $this->assertStringNotContainsString(__('onboarding.cta.to_delivery'), $html);
    }

    public function test_preferences_forward_button_points_to_delivery_for_trial(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_TRIAL, OnboardingDraftManager::STEP_PREFERENCES);

        $html = $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('onboarding.cta.to_delivery'), $html);
        $this->assertStringNotContainsString(__('onboarding.cta.to_schedule'), $html);
    }

    public function test_preferences_forward_button_points_to_schedule_when_intent_undecided(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_UNDECIDED, OnboardingDraftManager::STEP_PREFERENCES);

        $html = $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('onboarding.cta.to_schedule'), $html);
    }

    public function test_schedule_back_link_targets_preferences_for_subscription_intent(): void
    {
        $user = User::factory()->create();
        $this->draftFor(
            $user,
            OnboardingDraftManager::INTENT_SUBSCRIPTION,
            OnboardingDraftManager::STEP_SUBSCRIPTION_SCHEDULE,
            ['preferences_completed' => true]
        );

        $html = $this->actingAs($user)
            ->get(route('app.onboarding.step.subscription_schedule'))
            ->assertOk()
            ->getContent();

        // Generic label, intent-aware href: subscription skipped Commitment so
        // the back link should land on Preferences, not Commitment.
        $this->assertStringContainsString('href="'.route('app.onboarding.step.preferences').'"', $html);
        $this->assertStringNotContainsString('href="'.route('app.onboarding.step.commitment').'"', $html);
    }
}
