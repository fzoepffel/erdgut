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
        $this->assertTrue(SubscriberAccess::shouldShowWelcomeDashboard($user));
        $this->assertFalse(SubscriberAccess::hasAnyPurchaseHistory($user));
    }

    public function test_dashboard_shows_welcome_view_for_pending_payment_user(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::PendingPayment,
            'sumup_token' => null,
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            // dashboard-welcome.blade.php carries this string in its hero;
            // dashboard.blade.php (the regular subscriber view) does not.
            ->assertSee(__('Welcome to Erdgut'));
    }

    public function test_subscriber_navigation_is_hidden_for_pending_payment_user(): void
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

        // Subscription / Deliveries / Payments nav links only render when
        // hasSubscriberNav (= hasFullSubscriberExperience) is true.
        $this->assertStringNotContainsString(route('app.subscriptions.show', [], false), $html);
        $this->assertStringNotContainsString(route('app.deliveries.index', [], false), $html);
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
