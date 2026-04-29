<?php

namespace Tests\Feature\Onboarding;

use App\Models\OnboardingDraft;
use App\Models\User;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W2-04 / W2-7: the commitment step asks 'Try once vs Subscribe' but the
 * customer may already have answered that on the marketing CTA
 * (`/?intent=subscription` or `/?intent=trial`). Re-asking is friction.
 *
 * After preferences:
 *  - intent already set → skip commitment, jump to schedule/delivery.
 *  - intent still 'undecided' → still show commitment.
 *
 * Hitting /onboard/commitment directly:
 *  - intent already set → bounce forward to next step.
 *  - intent still 'undecided' → render the page.
 */
class CommitmentStepSkippableTest extends TestCase
{
    use RefreshDatabase;

    private function draftFor(User $user, string $intent, array $extraData = []): OnboardingDraft
    {
        return OnboardingDraft::create([
            'user_id' => $user->id,
            'intent' => $intent,
            'step' => OnboardingDraftManager::STEP_PREFERENCES,
            'data' => array_merge([
                'account_completed' => true,
                'address_completed' => true,
            ], $extraData),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function test_after_preferences_subscription_intent_skips_commitment_and_lands_on_schedule(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_SUBSCRIPTION);

        $this->actingAs($user)
            ->post(route('app.onboarding.step.preferences.store'), [
                'box_size' => 'small',
                'preferences' => ['veg_only' => false, 'meat_add_on' => false, 'eggs_dairy_add_on' => false],
                'allergies' => [],
            ])
            ->assertRedirect(route('app.onboarding.step.subscription_schedule'));
    }

    public function test_after_preferences_trial_intent_skips_commitment_and_lands_on_trial_delivery(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_TRIAL);

        $this->actingAs($user)
            ->post(route('app.onboarding.step.preferences.store'), [
                'box_size' => 'small',
                'preferences' => ['veg_only' => false, 'meat_add_on' => false, 'eggs_dairy_add_on' => false],
                'allergies' => [],
            ])
            ->assertRedirect(route('app.onboarding.step.trial_delivery'));
    }

    public function test_after_preferences_undecided_intent_still_shows_commitment(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_UNDECIDED);

        $this->actingAs($user)
            ->post(route('app.onboarding.step.preferences.store'), [
                'box_size' => 'small',
                'preferences' => ['veg_only' => false, 'meat_add_on' => false, 'eggs_dairy_add_on' => false],
                'allergies' => [],
            ])
            ->assertRedirect(route('app.onboarding.step.commitment'));
    }

    public function test_direct_visit_to_commitment_with_subscription_intent_bounces_forward(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_SUBSCRIPTION, [
            'preferences_completed' => true,
        ]);

        $this->actingAs($user)
            ->get(route('app.onboarding.step.commitment'))
            ->assertRedirect(route('app.onboarding.step.subscription_schedule'));
    }

    public function test_direct_visit_to_commitment_when_undecided_renders_the_page(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_UNDECIDED, [
            'preferences_completed' => true,
        ]);

        $this->actingAs($user)
            ->get(route('app.onboarding.step.commitment'))
            ->assertOk()
            ->assertSee(__('Try it once'));
    }
}
