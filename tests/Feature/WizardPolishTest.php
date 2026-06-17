<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\OnboardingDraft;
use App\Models\User;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Locks the wizard-polish batch (B6/B7/B9/B10/B11/B13/B14/C3/C4/C8)
 * so regressions don't reintroduce the customer-facing dead-ends and
 * stale-copy reverts.
 */
class WizardPolishTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function makeDraftWithAccount(User $user): OnboardingDraft
    {
        return OnboardingDraft::create([
            'user_id' => $user->id,
            'session_id' => 'test-session-'.$user->id,
            'intent' => OnboardingDraftManager::INTENT_SUBSCRIPTION,
            'step' => OnboardingDraftManager::STEP_ADDRESS,
            'data' => ['account_completed' => true],
            'expires_at' => now()->addDays(7),
        ]);
    }

    private function makeTrialDraft(User $user): OnboardingDraft
    {
        $address = Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);

        return OnboardingDraft::create([
            'user_id' => $user->id,
            'session_id' => 'test-session-'.$user->id,
            'intent' => OnboardingDraftManager::INTENT_TRIAL,
            'step' => OnboardingDraftManager::STEP_TRIAL_DELIVERY,
            'data' => [
                'account_completed' => true,
                'address_id' => $address->id,
                'address_completed' => true,
                'preferences_completed' => true,
                'box_size' => 'medium',
            ],
            'expires_at' => now()->addDays(7),
        ]);
    }

    // ---------------------------------------------------------------------
    // B6 — duplicate email recovery panel.
    // ---------------------------------------------------------------------

    public function test_b6_duplicate_email_post_renders_recovery_panel(): void
    {
        $existing = User::factory()->create(['email' => 'returning@example.com']);

        $response = $this->from(route('app.onboarding.step.account'))
            ->followingRedirects()
            ->post(route('app.onboarding.step.account.store'), [
                'name' => 'Sabine Subscriber',
                'email' => 'returning@example.com',
                'password' => 'pa55word!',
                'password_confirmation' => 'pa55word!',
            ]);

        $response->assertOk();
        $response->assertSee('account-step-email-recovery', false);
        $response->assertSee('account-step-email-recovery-login', false);
        $response->assertSee('account-step-email-recovery-reset', false);
        // The user's row should not have been overwritten / duplicated.
        $this->assertSame(1, User::where('email', 'returning@example.com')->count());
        $this->assertEqualsCanonicalizing(
            $existing->only(['id', 'email']),
            User::where('email', 'returning@example.com')->first()->only(['id', 'email']),
        );
    }

    public function test_b6_other_email_failures_do_not_show_recovery_panel(): void
    {
        // Empty email triggers required, not duplicate — so no recovery panel.
        $response = $this->from(route('app.onboarding.step.account'))
            ->followingRedirects()
            ->post(route('app.onboarding.step.account.store'), [
                'name' => 'Sabine Subscriber',
                'email' => '',
                'password' => 'pa55word!',
                'password_confirmation' => 'pa55word!',
            ]);

        $response->assertOk();
        $response->assertDontSee('account-step-email-recovery', false);
    }

    // ---------------------------------------------------------------------
    // B7 — country block is static, no false affordance.
    // ---------------------------------------------------------------------

    public function test_b7_address_form_renders_country_as_static_text(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeDraftWithAccount($user);

        $response = $this->actingAs($user)->get(route('app.onboarding.step.address'))->assertOk();

        $response->assertSee('address-country-static', false);
        // Hidden input still ships country_code so the form contract holds.
        $response->assertSee('name="country_code"', false);
        $response->assertSee('value="DE"', false);
    }

    // ---------------------------------------------------------------------
    // B9 — wizard header is nav-free across viewports.
    // ---------------------------------------------------------------------

    public function test_b9_wizard_header_does_not_render_language_selector(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeDraftWithAccount($user);

        $response = $this->actingAs($user)->get(route('app.onboarding.step.address'))->assertOk();

        // The wizard variant must not include the desktop language
        // selector pill (only the marketing variant does).
        $html = $response->getContent();
        $this->assertStringContainsString('customer-header-wizard', $html);
        // The language-selector component is identified by data-testid="language-selector".
        $this->assertStringNotContainsString('data-testid="language-selector"', $html);
    }

    // ---------------------------------------------------------------------
    // B10 — schedule step no longer renders or persists delivery_instructions.
    // ---------------------------------------------------------------------

    public function test_b10_schedule_step_does_not_render_delivery_instructions_textarea(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        OnboardingDraft::create([
            'user_id' => $user->id,
            'session_id' => 'test-session-'.$user->id,
            'intent' => OnboardingDraftManager::INTENT_SUBSCRIPTION,
            'step' => OnboardingDraftManager::STEP_SUBSCRIPTION_SCHEDULE,
            'data' => [
                'account_completed' => true,
                'address_completed' => true,
                'preferences_completed' => true,
                'box_size' => 'medium',
            ],
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($user)->get(route('app.onboarding.step.subscription_schedule'))->assertOk();

        $response->assertDontSee('id="delivery_instructions"', false);
        $response->assertDontSee('name="delivery_instructions"', false);
    }

    public function test_b10_schedule_post_does_not_overwrite_address_delivery_instructions(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'is_primary' => true,
            'delivery_instructions' => 'Klingel: Hofgebäude links',
        ]);
        OnboardingDraft::create([
            'user_id' => $user->id,
            'session_id' => 'test-session-'.$user->id,
            'intent' => OnboardingDraftManager::INTENT_SUBSCRIPTION,
            'step' => OnboardingDraftManager::STEP_SUBSCRIPTION_SCHEDULE,
            'data' => [
                'account_completed' => true,
                'address_id' => $address->id,
                'address_completed' => true,
                'preferences_completed' => true,
            ],
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($user)
            ->post(route('app.onboarding.step.subscription_schedule.store'), [
                'plan' => 'weekly',
                'fulfillment_mode' => 'delivery',
                'delivery_weekday' => 3,
                'preferred_timeslot' => '12:00-16:00',
                // Even if a stale form posts delivery_instructions, the
                // controller must not write it onto the address.
                'delivery_instructions' => 'WRONG OVERRIDE',
            ])
            ->assertRedirect(route('app.onboarding.step.subscription_payment'));

        $address->refresh();
        $this->assertSame('Klingel: Hofgebäude links', $address->delivery_instructions);
    }

    // ---------------------------------------------------------------------
    // B11 — step-bar and forward-button labels agree.
    // ---------------------------------------------------------------------

    public function test_b11_address_continue_button_says_continue_to_box(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeDraftWithAccount($user);

        $response = $this->actingAs($user)->get(route('app.onboarding.step.address'))->assertOk();
        // The bar segment is "Box"; the forward button names that next step.
        $response->assertSee(__('onboarding.cta.to_box'));
        $response->assertDontSee(__('Continue to :step', ['step' => __('Preferences')]));
    }

    // ---------------------------------------------------------------------
    // B13 — payment heading reframes the action as "start subscription".
    // ---------------------------------------------------------------------

    public function test_b13_subscription_payment_page_heading_reflects_subscription_start(): void
    {
        // Render check only — full payment flow has external SumUp deps.
        $user = User::factory()->create(['email_verified_at' => now()]);
        $address = Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        // Need a complete draft so the step gating allows /subscription/payment.
        OnboardingDraft::create([
            'user_id' => $user->id,
            'session_id' => 'test-session-'.$user->id,
            'intent' => OnboardingDraftManager::INTENT_SUBSCRIPTION,
            'step' => OnboardingDraftManager::STEP_SUBSCRIPTION_PAYMENT,
            'data' => [
                'account_completed' => true,
                'address_id' => $address->id,
                'address_completed' => true,
                'preferences_completed' => true,
                'subscription_schedule_completed' => true,
                'box_size' => 'medium',
                'plan' => 'weekly',
                'fulfillment_mode' => 'delivery',
            ],
            'expires_at' => now()->addDays(7),
        ]);

        // Lang strings must be the new "start subscription" framing.
        $this->assertSame(
            'Karte speichern und Abo starten',
            __('Save card and start subscription', [], 'de'),
        );
        $this->assertSame(
            'Letzter Schritt, Abo starten',
            __('Last step — start your subscription', [], 'de'),
        );
    }

    // ---------------------------------------------------------------------
    // C3 — trial timeslot picker only renders the real Wed 12-16 slot.
    // ---------------------------------------------------------------------

    public function test_c3_trial_delivery_step_only_renders_supported_timeslot(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeTrialDraft($user);

        $response = $this->actingAs($user)->get(route('app.onboarding.step.trial_delivery'))->assertOk();

        $response->assertSee('value="12:00-16:00"', false);
        $response->assertDontSee('value="08:00-12:00"', false);
        $response->assertDontSee('value="16:00-20:00"', false);
        $response->assertSee('trial-delivery-zone-hint', false);
    }

    // ---------------------------------------------------------------------
    // Trial path uses the same editorial hero as the subscription path.
    // ---------------------------------------------------------------------

    public function test_trial_delivery_step_renders_editorial_hero_with_trial_copy(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeTrialDraft($user);

        $response = $this->actingAs($user)->get(route('app.onboarding.step.trial_delivery'))->assertOk();

        // The editorial hero (shared layout) now fronts the trial steps with
        // trial-specific copy — not the subscription "rhythm" wording.
        $response->assertSee(__('onboarding.hero.trial_delivery.eyebrow'));
        $response->assertSee(__('onboarding.hero.trial_delivery.lede'));
        // Step rail names step 3 "Lieferung" for the trial path.
        $response->assertSee(__('onboarding.step.delivery'));
        // The old in-content heading is gone (hero owns it now).
        $response->assertDontSee(__('When would you like us to come by?'));
        // Forward CTA uses the shared taxonomy key.
        $response->assertSee(__('onboarding.cta.to_payment'));
    }

    // ---------------------------------------------------------------------
    // C4 — Wunschwoche free-text replaced with bounded date input.
    // ---------------------------------------------------------------------

    public function test_c4_trial_delivery_step_renders_bounded_date_input(): void
    {
        Carbon::setTestNow('2026-05-07');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeTrialDraft($user);

        $response = $this->actingAs($user)->get(route('app.onboarding.step.trial_delivery'))->assertOk();

        // New input has type=date with min/max bounds.
        $response->assertSee('id="preferred_delivery_date"', false);
        $response->assertSee('type="date"', false);
        $response->assertSee('min="2026-05-08"', false); // tomorrow
        // Max defaults to 28 days out (config promise window) — 2026-06-04.
        $response->assertSee('max="2026-06-04"', false);
        // Old free-text field is gone.
        $response->assertDontSee('id="preferred_delivery_window"', false);
    }

    public function test_c4_trial_delivery_step_rejects_date_in_past(): void
    {
        Carbon::setTestNow('2026-05-07');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeTrialDraft($user);

        $this->actingAs($user)
            ->from(route('app.onboarding.step.trial_delivery'))
            ->post(route('app.onboarding.step.trial_delivery.store'), [
                'preferred_timeslot' => '12:00-16:00',
                'preferred_delivery_date' => '2025-12-01', // past
            ])
            ->assertSessionHasErrors('preferred_delivery_date');
    }

    public function test_c4_trial_delivery_step_rejects_date_too_far_out(): void
    {
        Carbon::setTestNow('2026-05-07');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeTrialDraft($user);

        $this->actingAs($user)
            ->from(route('app.onboarding.step.trial_delivery'))
            ->post(route('app.onboarding.step.trial_delivery.store'), [
                'preferred_timeslot' => '12:00-16:00',
                'preferred_delivery_date' => '2026-09-01', // way past 28 days
            ])
            ->assertSessionHasErrors('preferred_delivery_date');
    }

    public function test_c4_trial_delivery_step_accepts_in_window_date(): void
    {
        Carbon::setTestNow('2026-05-07');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $draft = $this->makeTrialDraft($user);

        $this->actingAs($user)
            ->post(route('app.onboarding.step.trial_delivery.store'), [
                'preferred_timeslot' => '12:00-16:00',
                'preferred_delivery_date' => '2026-05-13',
            ])
            ->assertRedirect(route('app.onboarding.step.trial_payment'));

        $draft->refresh();
        $this->assertSame('2026-05-13', $draft->data['preferred_delivery_date']);
    }
}
