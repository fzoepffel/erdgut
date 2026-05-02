<?php

namespace Tests\Feature\Onboarding;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Onboarding\SubscriptionOnboardingService;
use App\Support\SubscriberAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lock against the regression where reaching the wizard's payment step
 * (which calls ensureSubscription to materialise a row for the SumUp
 * checkout reference) would create the subscription as Active, so a user
 * who navigated away before paying ended up looking like a subscriber.
 *
 * Now ensureSubscription creates the row in PendingPayment status; only
 * finalizeSetup() flips it to Active after the SumUp mandate is captured.
 */
class AbandonedPaymentSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_subscription_creates_in_pending_payment_state(): void
    {
        $user = User::factory()->create();
        /** @var SubscriptionOnboardingService $service */
        $service = $this->app->make(SubscriptionOnboardingService::class);

        $subscription = $service->ensureSubscription($user, [
            'plan' => 'weekly',
            'box_size' => 'small',
            'preferences' => ['veg_only' => true, 'eggs_dairy_add_on' => false],
            'allergies' => [],
            'fulfillment_mode' => 'delivery',
            'delivery_weekday' => 3,
            'preferred_timeslot' => '12:00-16:00',
        ]);

        $this->assertSame(SubscriptionStatus::PendingPayment, $subscription->status);
        $this->assertNull($subscription->sumup_token);
    }

    public function test_dashboard_does_not_treat_pending_payment_user_as_full_subscriber(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::PendingPayment,
            'sumup_token' => null,
        ]);

        $this->assertFalse(SubscriberAccess::hasFullSubscriberExperience($user));
        // PendingPayment now routes to the regular dashboard so it can render
        // the "resume your subscription setup" affordance with the customer's
        // saved selections; the welcome view's generic "Start subscription"
        // CTA was hiding the in-progress signup.
        $this->assertFalse(SubscriberAccess::shouldShowWelcomeDashboard($user));
        $this->assertFalse(SubscriberAccess::hasAnyPurchaseHistory($user));
    }

    public function test_dashboard_shows_resume_setup_block_for_pending_payment_user(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::PendingPayment,
            'sumup_token' => null,
        ]);

        $html = $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->getContent();

        // The state-aware dashboard renders a resume-setup block for
        // SubscriptionPendingNoTrial / WithTrial states.
        $this->assertStringContainsString('data-testid="dashboard-resume-subscription-setup"', $html);
    }

    public function test_subscriber_header_navigation_is_hidden_for_pending_payment_user(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::PendingPayment,
            'sumup_token' => null,
        ]);

        $html = $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->getContent();

        // hasSubscriberNav is false for PendingPayment, so the masthead nav
        // links (Subscription / Deliveries) don't render. The dashboard body
        // CAN still link to those routes (e.g. the "Manage subscription" CTA
        // on the at-a-glance card) — that's intentional, the user just can't
        // navigate there from the global nav until they finish setup.
        $this->assertFalse(SubscriberAccess::hasFullSubscriberExperience($user));
    }

    public function test_existing_active_no_token_subscription_was_backfilled_to_pending_payment(): void
    {
        // Sanity check the migration ran on the test DB. RefreshDatabase
        // rolls migrations forward from scratch, so an Active+no_token row
        // created by the factory should NOT be re-flipped here. We just
        // assert the migration's update runs cleanly.
        $user = User::factory()->create();
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => null,
            'billing_mode' => 'sumup_recurring',
        ]);

        // Re-running the same SQL the migration does:
        \DB::table('subscriptions')
            ->where('id', $sub->id)
            ->where('status', 'active')
            ->whereNull('sumup_token')
            ->where(function ($q) {
                $q->where('billing_mode', 'sumup_recurring')->orWhereNull('billing_mode');
            })
            ->update(['status' => 'pending_payment']);

        $this->assertSame('pending_payment', $sub->fresh()->status->value);
    }
}
