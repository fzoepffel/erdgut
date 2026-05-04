<?php

namespace Tests\Feature\Onboarding;

use App\Enums\SubscriptionStatus;
use App\Models\OnboardingDraft;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Discovered during real-SumUp E2E walk 2026-05-04: a customer could jump
 * straight to /onboard/subscription/payment by URL, bypassing every prior
 * step. The wizard had no per-step gate. RedirectToCorrectOnboardingStep
 * middleware now enforces:
 *
 *   - Forward jumps redirect to the customer's actual current step.
 *   - Wrong-path jumps (e.g. trial steps when intent=subscription) redirect.
 *   - Backward navigation (Edit links) stays allowed.
 *
 * Plus RedirectIfOnboardingComplete blocks /onboard/* access once the
 * customer already has a finalised Subscription — wizard is single-shot.
 */
class StepGatingTest extends TestCase
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
            ], $extraData),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function test_subscription_intent_jumping_to_payment_without_address_is_blocked(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_SUBSCRIPTION, OnboardingDraftManager::STEP_ADDRESS);

        // Customer is at the address step but tries to jump to payment.
        $this->actingAs($user)
            ->get(route('app.onboarding.step.subscription_payment'))
            ->assertRedirect(route('app.onboarding.step.address'));
    }

    public function test_subscription_intent_jumping_to_payment_without_preferences_is_blocked(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_SUBSCRIPTION, OnboardingDraftManager::STEP_PREFERENCES, [
            'address_completed' => true,
        ]);

        $this->actingAs($user)
            ->get(route('app.onboarding.step.subscription_payment'))
            ->assertRedirect(route('app.onboarding.step.preferences'));
    }

    public function test_subscription_intent_blocked_from_trial_path(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_SUBSCRIPTION, OnboardingDraftManager::STEP_PREFERENCES, [
            'address_completed' => true,
        ]);

        $this->actingAs($user)
            ->get(route('app.onboarding.step.trial_delivery'))
            ->assertRedirect(route('app.onboarding.step.preferences'));
    }

    public function test_trial_intent_blocked_from_subscription_path(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_TRIAL, OnboardingDraftManager::STEP_PREFERENCES, [
            'address_completed' => true,
        ]);

        $this->actingAs($user)
            ->get(route('app.onboarding.step.subscription_schedule'))
            ->assertRedirect(route('app.onboarding.step.preferences'));
    }

    public function test_backward_navigation_to_earlier_step_is_allowed(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_SUBSCRIPTION, OnboardingDraftManager::STEP_PREFERENCES, [
            'address_completed' => true,
        ]);

        // Edit-link back to address should not be blocked.
        $this->actingAs($user)
            ->get(route('app.onboarding.step.address'))
            ->assertOk();
    }

    public function test_current_step_is_accessible(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user, OnboardingDraftManager::INTENT_SUBSCRIPTION, OnboardingDraftManager::STEP_PREFERENCES, [
            'address_completed' => true,
        ]);

        $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertOk();
    }

    public function test_user_with_active_subscription_is_redirected_off_onboarding_to_dashboard(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);
        $this->draftFor($user, OnboardingDraftManager::INTENT_SUBSCRIPTION, OnboardingDraftManager::STEP_PREFERENCES);

        $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertRedirect(route('app.dashboard'));
    }

    public function test_unverified_user_with_pending_subscription_is_redirected_to_verify_email(): void
    {
        $user = User::factory()->unverified()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::PendingPayment,
        ]);
        $this->draftFor($user, OnboardingDraftManager::INTENT_SUBSCRIPTION, OnboardingDraftManager::STEP_PREFERENCES);

        $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertRedirect(route('verification.notice'));
    }
}
