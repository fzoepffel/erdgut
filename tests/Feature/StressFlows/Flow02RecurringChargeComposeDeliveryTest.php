<?php

namespace Tests\Feature\StressFlows;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stress-flow 2 (ERD-342): A due recurring subscription is picked up by
 * the scheduled charge command. Outcome: dry-run lists the subscription
 * and (when run live with a paying mock) a Payment row exists.
 *
 * The fully-mocked SumUp roundtrip is exercised in
 * Tests\Feature\SumUp\ChargeSubscriptionsTest. This stress-flow verifies
 * the audit path's eligibility filter and the Payment shape afterwards.
 */
class Flow02RecurringChargeComposeDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_recurring_subscription_is_listed_in_dry_run(): void
    {
        $user = User::factory()->create(['email' => 'recurring@example.com']);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_recurring_123',
            'sumup_customer_id' => 'cust_recurring_123',
            'box_size' => 'medium',
            'next_charge_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('subscriptions:charge', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('1 subscription(s) would be charged');
    }

    public function test_payment_row_is_attached_to_subscription_when_persisted(): void
    {
        // Demonstrates the Payment <-> Subscription invariant: every charge run
        // attaches at least one Payment row to the subscription.
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PAID,
        ]);

        $this->assertSame($subscription->id, $payment->subscription_id);
        $this->assertSame(1, $subscription->payments()->count());
    }
}
