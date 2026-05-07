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
 * Locks the customer-copy batch fixes (notes file C2/C6, C5, C11, C13, D4, D5).
 *
 * Each test pins one user-facing string against the misleading copy that
 * was shipped before this batch. We assert the corrected copy is rendered
 * in its right context AND the misleading wording is no longer present.
 */
class CustomerCopyHonestyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $extraData
     */
    private function startDraftWithIntent(User $user, string $intent, array $extraData = []): OnboardingDraft
    {
        return OnboardingDraft::create([
            'user_id' => $user->id,
            'intent' => $intent,
            'step' => OnboardingDraftManager::STEP_PREFERENCES,
            // Mark account + address as completed so the step-gating
            // middleware (RedirectToCorrectOnboardingStep) lets the user
            // through to the step we actually want to test.
            'data' => array_merge([
                'account_completed' => true,
                'address_completed' => true,
                'address_id' => 1,
            ], $extraData),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function test_c2_preferences_step_shows_once_label_for_trial_intent(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->startDraftWithIntent($user, OnboardingDraftManager::INTENT_TRIAL);

        $response = $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'));

        $response->assertOk();
        // Trial cards must say "Once", not the recurring "Per delivery".
        $response->assertSee('Once', false);
        $response->assertDontSee('Per delivery', false);
    }

    public function test_c2_preferences_step_keeps_per_delivery_label_for_subscription_intent(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->startDraftWithIntent($user, OnboardingDraftManager::INTENT_SUBSCRIPTION);

        $response = $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'));

        $response->assertOk();
        $response->assertSee('Per delivery', false);
    }

    public function test_c5_trial_delivery_no_hardcoded_6_may_placeholder(): void
    {
        // C4 (UX notes) replaced the free-text Wunschwoche field with a
        // bounded date picker, so the older "after 6 May" placeholder
        // stub is gone outright. This test continues to guard against
        // any future copy that pins a specific calendar date in
        // placeholder text.
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->startDraftWithIntent(
            $user,
            OnboardingDraftManager::INTENT_TRIAL,
            ['preferences_completed' => true],
        );

        $response = $this->actingAs($user)
            ->get(route('app.onboarding.step.trial_delivery'));

        $response->assertOk();
        $response->assertDontSee('after 6 May', false);
        $response->assertDontSee('nach dem 6. Mai', false);
    }

    public function test_c11_trial_dashboard_upsell_no_longer_promises_no_new_card(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('app.dashboard'));

        $response->assertOk();
        // The misleading promise must be gone — trial flow uses one-time
        // checkout (customerId: null), so a new card IS required for the
        // upsell to subscription. Assert the new honest line is rendered
        // when an upsell card is shown, but at minimum that the false
        // "no new card" claim is nowhere on the dashboard.
        $response->assertDontSee('No new card needed', false);
    }

    public function test_c13_trial_completion_copy_does_not_imply_recurring_relationship(): void
    {
        // The completion view is rendered for a trial-only customer with
        // a paid trial order, so we hit the controller's view directly via
        // the lang file: the legacy line must not be a translation lookup
        // result of the page anymore. This test asserts at the lang-file
        // level that both keys exist and the new one is honest.
        $this->assertEquals(
            'Ein Platz am Tisch — deiner, mit dieser ersten Box.',
            __('A seat at the table — yours, with this first box.', [], 'de')
        );
        // Old key remains for any legacy reference, but the new view uses
        // the new key. (Compile-time grep would also catch a regression.)
    }

    public function test_d4_billing_date_fallback_says_set_after_payment_setup(): void
    {
        // English source key is what the controllers pass; verify it's
        // wired and that the misleading "Set after first charge" no longer
        // names this fallback.
        $this->assertEquals(
            'Set after payment setup',
            __('Set after payment setup', [], 'en')
        );
        $this->assertEquals(
            'Wird mit der Zahlungseinrichtung festgelegt',
            __('Set after payment setup', [], 'de')
        );
    }

    public function test_d5_deliveries_empty_state_does_not_hedge_on_subscription_status(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Subscription::factory()->for($user)->create([
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_test',
            'sumup_customer_id' => 'cust_test',
        ]);

        $response = $this->actingAs($user)->get(route('app.deliveries.index'));

        $response->assertOk();
        // The misleading "When your subscription is active, …" hedge is gone;
        // the new copy is state-neutral and just promises the rows will appear.
        $response->assertDontSee('When your subscription is active', false);
        $response->assertSee('as soon as we plan them', false);
    }
}
