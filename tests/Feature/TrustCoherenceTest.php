<?php

namespace Tests\Feature;

use App\Enums\Allergen;
use App\Enums\BillingMode;
use App\Enums\FulfillmentMode;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Address;
use App\Models\OnboardingDraft;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Locks the trust-coherence batch fixes (A3, B3, B5, B12, D1, D2, D3) so
 * regressions don't reintroduce the customer-facing data drift that
 * powered the UX review's High-priority findings.
 */
class TrustCoherenceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function makeOnboardingDraftWithAccount(User $user): OnboardingDraft
    {
        return OnboardingDraft::create([
            'user_id' => $user->id,
            'session_id' => 'test-session',
            'intent' => OnboardingDraftManager::INTENT_SUBSCRIPTION,
            'step' => OnboardingDraftManager::STEP_ADDRESS,
            'data' => ['account_completed' => true],
            'expires_at' => now()->addDays(7),
        ]);
    }

    // ---------------------------------------------------------------------
    // A3 — /home trial copy auto-tracks the smallest box price.
    // ---------------------------------------------------------------------

    public function test_a3_home_trial_copy_uses_smallest_box_price_not_hardcoded_35(): void
    {
        config()->set('box.included_value_by_size.small', 22);

        $response = $this->withHeaders(['Accept-Language' => 'de'])
            ->get(route('public.marketing-home'));

        $response->assertOk();
        // German copy: "Ab EUR 22 für eine einzelne Erdgut-Box".
        $response->assertSee('Ab EUR 22');
        $response->assertDontSee('EUR 35 für eine einzelne Erdgut-Box');
    }

    // ---------------------------------------------------------------------
    // B12 — payment consent gate.
    // ---------------------------------------------------------------------

    public function test_b12_terms_route_resolves(): void
    {
        $this->get(route('public.terms'))->assertOk();
        $this->get(route('public.privacy'))->assertOk();
    }

    // ---------------------------------------------------------------------
    // B5 — Berlin PLZ + zone validation.
    // ---------------------------------------------------------------------

    public function test_b5_address_step_rejects_non_berlin_postal_code(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeOnboardingDraftWithAccount($user);

        $response = $this->actingAs($user)
            ->from(route('app.onboarding.step.address'))
            ->post(route('app.onboarding.step.address.store'), [
                'recipient_name' => 'Sabine Subscriber',
                'street_line_1' => 'Hauptstr 1',
                'postal_code' => '20095', // Hamburg
                'city' => 'Hamburg',
                'country_code' => 'DE',
            ]);

        $response->assertSessionHasErrors('postal_code');
        $this->assertDatabaseMissing('addresses', ['postal_code' => '20095']);
    }

    public function test_b5_address_step_rejects_garbage_postal_code(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeOnboardingDraftWithAccount($user);

        $response = $this->actingAs($user)
            ->from(route('app.onboarding.step.address'))
            ->post(route('app.onboarding.step.address.store'), [
                'recipient_name' => 'Sabine Subscriber',
                'street_line_1' => 'Hauptstr 1',
                'postal_code' => 'ABC123', // not a German PLZ
                'city' => 'Pjöngjang',
                'country_code' => 'DE',
            ]);

        $response->assertSessionHasErrors('postal_code');
    }

    public function test_b5_address_step_accepts_in_zone_postal_code(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeOnboardingDraftWithAccount($user);

        $this->actingAs($user)
            ->from(route('app.onboarding.step.address'))
            ->post(route('app.onboarding.step.address.store'), [
                'recipient_name' => 'Sabine Subscriber',
                'street_line_1' => 'Kollwitzstr 1',
                'postal_code' => '10435', // Berlin Prenzlauer Berg
                'city' => 'Berlin',
                'country_code' => 'DE',
            ])
            ->assertSessionDoesntHaveErrors('postal_code');
    }

    public function test_b5_address_form_renders_zone_hint(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->makeOnboardingDraftWithAccount($user);

        $response = $this->actingAs($user)
            ->get(route('app.onboarding.step.address'))
            ->assertOk();

        $response->assertSee('address-delivery-zone-hint', false);
    }

    // ---------------------------------------------------------------------
    // B3 — login redirect for in-flight draft sends user back into wizard.
    // ---------------------------------------------------------------------

    public function test_b3_login_with_inflight_draft_routes_to_onboarding_not_dashboard(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null, // unverified — would normally hit verified gate
            'password' => bcrypt('secret-pw'),
        ]);
        $this->makeOnboardingDraftWithAccount($user);

        $this->post(route('auth.login.store'), [
            'email' => $user->email,
            'password' => 'secret-pw',
        ])->assertRedirect(route('app.onboarding.start'));
    }

    public function test_b3_login_without_draft_keeps_dashboard_default(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('secret-pw'),
        ]);

        $this->post(route('auth.login.store'), [
            'email' => $user->email,
            'password' => 'secret-pw',
        ])->assertRedirect(route('app.dashboard'));
    }

    public function test_b3_verify_email_page_offers_continue_setup_for_inflight_draft(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->makeOnboardingDraftWithAccount($user);

        $response = $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk();

        $response->assertSee('verify-email-continue-onboarding', false);
    }

    // ---------------------------------------------------------------------
    // D2 — schedule step picker only shows real (Wed × 12-16) slot.
    // ---------------------------------------------------------------------

    public function test_d2_zone_config_is_pinned_to_wednesday_afternoon(): void
    {
        $weekdays = config('delivery.zones.berlin_core.allowed_weekdays');
        $timeslots = config('delivery.zones.berlin_core.allowed_timeslots');

        $this->assertSame([3], $weekdays, 'Only Wednesday is bookable today.');
        $this->assertSame(['12:00-16:00'], $timeslots, 'Only the afternoon slot is bookable today.');
    }

    // ---------------------------------------------------------------------
    // D1 — dashboard hero hides ETA until a real Delivery row exists.
    // ---------------------------------------------------------------------

    public function test_d1_first_week_hero_renders_pending_when_no_delivery_row(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        Subscription::factory()->withPaymentToken()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'medium',
            'fulfillment_mode' => FulfillmentMode::Delivery,
            'billing_mode' => BillingMode::SumupRecurring,
        ]);

        // No Delivery row at all → hero must not promise a date.
        $response = $this->actingAs($user)->get(route('app.dashboard'));
        $response->assertOk();
        $response->assertSee('dashboard-first-box-pending', false);
        $response->assertDontSee('Your first box arrives');
    }

    // ---------------------------------------------------------------------
    // D3 — eggs add-on / eggs allergy hard block.
    // ---------------------------------------------------------------------

    public function test_d3_preferences_step_rejects_eggs_addon_with_eggs_allergy(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        OnboardingDraft::create([
            'user_id' => $user->id,
            'session_id' => 'test-session',
            'intent' => OnboardingDraftManager::INTENT_SUBSCRIPTION,
            'step' => OnboardingDraftManager::STEP_PREFERENCES,
            'data' => [
                'account_completed' => true,
                'address_id' => Address::factory()->create([
                    'user_id' => $user->id,
                    'is_primary' => true,
                ])->id,
                'address_completed' => true,
            ],
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($user)
            ->from(route('app.onboarding.step.preferences'))
            ->post(route('app.onboarding.step.preferences.store'), [
                'box_size' => 'medium',
                'preferences' => [
                    'veg_only' => 1,
                    'meat_add_on' => 0,
                    'eggs_dairy_add_on' => 1,
                ],
                'allergies' => [Allergen::Eggs->value, Allergen::Nuts->value],
            ])
            ->assertSessionHasErrors('preferences.eggs_dairy_add_on');
    }

    public function test_d3_subscription_update_rejects_eggs_addon_with_eggs_allergy(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        $sub = Subscription::factory()->withPaymentToken()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'medium',
            'allergies' => [],
            'preferences' => ['veg_only' => true, 'meat_add_on' => false, 'eggs_dairy_add_on' => false],
        ]);

        $this->actingAs($user)
            ->from(route('app.subscriptions.edit'))
            ->patch(route('app.subscriptions.update'), [
                'plan' => SubscriptionPlan::Weekly->value,
                'fulfillment_mode' => FulfillmentMode::Delivery->value,
                'box_size' => 'medium',
                'preferences' => [
                    'veg_only' => 1,
                    'meat_add_on' => 0,
                    'eggs_dairy_add_on' => 1,
                ],
                'allergies' => [Allergen::Eggs->value],
            ])
            ->assertSessionHasErrors('preferences.eggs_dairy_add_on');

        // The subscription stays unchanged (still no eggs add-on).
        $sub->refresh();
        $this->assertFalse((bool) data_get($sub->preferences, 'eggs_dairy_add_on'));
    }

    public function test_d3_preferences_step_accepts_eggs_addon_without_eggs_allergy(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'is_primary' => true,
        ]);
        OnboardingDraft::create([
            'user_id' => $user->id,
            'session_id' => 'test-session',
            'intent' => OnboardingDraftManager::INTENT_SUBSCRIPTION,
            'step' => OnboardingDraftManager::STEP_PREFERENCES,
            'data' => [
                'account_completed' => true,
                'address_id' => $address->id,
                'address_completed' => true,
            ],
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($user)
            ->from(route('app.onboarding.step.preferences'))
            ->post(route('app.onboarding.step.preferences.store'), [
                'box_size' => 'medium',
                'preferences' => [
                    'veg_only' => 1,
                    'meat_add_on' => 0,
                    'eggs_dairy_add_on' => 1,
                ],
                'allergies' => [Allergen::Nuts->value], // eggs NOT in list
            ])
            ->assertSessionDoesntHaveErrors('preferences.eggs_dairy_add_on');
    }
}
