<?php

namespace Tests\Feature\Onboarding;

use App\Models\OnboardingDraft;
use App\Models\User;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step-bar / forward-button taxonomy regression test.
 *
 * Earlier the preferences-step forward button labelled itself with
 * the *next inner* step name (Schedule / Delivery / Commitment) which
 * contradicted the wizard's progress bar (where preferences /
 * commitment / schedule / delivery all live inside the single "Box"
 * pill). The button now reads a generic "Continue" regardless of
 * intent — this test pins that taxonomy alignment (B11 in UX notes).
 *
 * The schedule-step back-link logic IS still intent-aware: subscribers
 * who skipped Commitment land back on Preferences. That contract is
 * still tested below.
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

    public function test_preferences_forward_button_uses_generic_continue_for_subscription(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_SUBSCRIPTION, OnboardingDraftManager::STEP_PREFERENCES);

        $html = $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            __('Continue'),
            $html,
            'subscription intent should render a generic "Continue" — the bar pill stays "Box".'
        );
        $this->assertStringNotContainsString(
            __('Continue to :step', ['step' => __('Schedule')]),
            $html,
        );
        $this->assertStringNotContainsString(
            __('Continue to :step', ['step' => __('Commitment')]),
            $html,
        );
    }

    public function test_preferences_forward_button_uses_generic_continue_for_trial(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_TRIAL, OnboardingDraftManager::STEP_PREFERENCES);

        $html = $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('Continue'), $html);
        $this->assertStringNotContainsString(
            __('Continue to :step', ['step' => __('Delivery')]),
            $html,
        );
    }

    public function test_preferences_forward_button_uses_generic_continue_when_intent_undecided(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_UNDECIDED, OnboardingDraftManager::STEP_PREFERENCES);

        $html = $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('Continue'), $html);
        $this->assertStringNotContainsString(
            __('Continue to :step', ['step' => __('Commitment')]),
            $html,
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
