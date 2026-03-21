<?php

namespace Tests\Feature\SumUp;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PauseExhaustedSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_exhausted_subscriptions(): void
    {
        Config::set('payments.max_retries', 3);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_123',
            'sumup_customer_id' => 'cust_123',
        ]);
        $subscription->payments()->create([
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => 3,
            'failed_at' => now(),
            'failure_reason' => 'Card declined',
        ]);

        $this->artisan('subscriptions:pause-exhausted', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('1 subscription(s) would be paused');
    }

    public function test_pause_exhausted_subscription(): void
    {
        Config::set('payments.max_retries', 3);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_123',
            'sumup_customer_id' => 'cust_123',
        ]);
        $subscription->payments()->create([
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => 3,
            'failed_at' => now(),
            'failure_reason' => 'Card declined',
        ]);

        $this->artisan('subscriptions:pause-exhausted')
            ->assertSuccessful()
            ->expectsOutputToContain('Paused 1 subscription(s)');

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Paused, $subscription->status);
    }

    public function test_does_not_pause_subscription_below_threshold(): void
    {
        Config::set('payments.max_retries', 3);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_123',
            'sumup_customer_id' => 'cust_123',
        ]);
        $subscription->payments()->create([
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => 2,
            'failed_at' => now(),
        ]);

        $this->artisan('subscriptions:pause-exhausted')
            ->assertSuccessful()
            ->expectsOutputToContain('No exhausted subscriptions');

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
    }

    public function test_skips_subscription_without_token(): void
    {
        Config::set('payments.max_retries', 3);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => null,
            'sumup_customer_id' => null,
        ]);
        $subscription->payments()->create([
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => 3,
            'failed_at' => now(),
        ]);

        $this->artisan('subscriptions:pause-exhausted')
            ->assertSuccessful()
            ->expectsOutputToContain('No exhausted subscriptions');

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
    }
}
