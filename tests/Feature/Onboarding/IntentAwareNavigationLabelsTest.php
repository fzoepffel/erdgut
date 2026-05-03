<?php

namespace Tests\Feature\Onboarding;

use App\Models\OnboardingDraft;
use App\Models\User;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug regression: when a customer's intent is already known
 * (subscription/trial), Commitment is skipped on the way out of Preferences
 * (per CommitmentStepSkippableTest). The "Continue to ..." button on the
 * preferences step and the "Back to ..." link on the schedule step both used
 * to hardcode "Commitment" as the destination — leaving subscription users
 * with a back link that bounced them right back to where they came from.
 *
 * These tests pin the labels to the actual destination steps per intent.
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

    public function test_preferences_forward_button_says_schedule_for_subscription_intent(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_SUBSCRIPTION, OnboardingDraftManager::STEP_PREFERENCES);

        $html = $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            __('Continue to :step', ['step' => __('Schedule')]),
            $html,
            'subscription intent should label the forward button "Continue to Schedule" — Commitment is skipped on POST.'
        );
        $this->assertStringNotContainsString(
            __('Continue to :step', ['step' => __('Commitment')]),
            $html,
            'subscription intent should not point at the skipped Commitment step.'
        );
    }

    public function test_preferences_forward_button_says_delivery_for_trial_intent(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_TRIAL, OnboardingDraftManager::STEP_PREFERENCES);

        $html = $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            __('Continue to :step', ['step' => __('Delivery')]),
            $html,
            'trial intent should label the forward button "Continue to Delivery".'
        );
    }

    public function test_preferences_forward_button_keeps_commitment_when_intent_undecided(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_UNDECIDED, OnboardingDraftManager::STEP_PREFERENCES);

        $html = $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            __('Continue to :step', ['step' => __('Commitment')]),
            $html,
            'undecided intent should still flow Preferences → Commitment.'
        );
    }

    public function test_schedule_back_link_points_to_preferences_for_subscription_intent(): void
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

        $this->assertStringContainsString(
            __('Back to :step', ['step' => __('Preferences')]),
            $html,
            'subscription intent skipped Commitment, so the back link should land on Preferences instead.'
        );
        $this->assertStringNotContainsString(
            __('Back to :step', ['step' => __('Commitment')]),
            $html,
            'subscription intent should not link back to the skipped Commitment step.'
        );
    }
}
