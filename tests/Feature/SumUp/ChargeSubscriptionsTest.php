<?php

namespace Tests\Feature\SumUp;

use App\Enums\PaymentChargeInitiator;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\BillingFulfillmentWeek;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class ChargeSubscriptionsTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    public function test_dry_run_lists_due_subscription(): void
    {
        $user = User::factory()->create(['email' => 'due@example.com']);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_123',
            'sumup_customer_id' => 'cust_123',
            'next_charge_date' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:charge', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('1 subscription(s) would be charged');
    }

    public function test_dry_run_excludes_paused_subscription_within_window(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_123',
            'sumup_customer_id' => 'cust_123',
            'next_charge_date' => now()->subDay(),
            'pause_from' => now()->subDays(2),
            'pause_until' => now()->addDays(2),
        ]);

        $this->artisan('subscriptions:charge', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No due subscriptions');
    }

    public function test_dry_run_excludes_subscription_without_token(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => null,
            'sumup_customer_id' => null,
            'next_charge_date' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:charge', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No due subscriptions');
    }

    public function test_dry_run_excludes_subscription_with_future_next_charge_date(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_123',
            'sumup_customer_id' => 'cust_123',
            'next_charge_date' => now()->addWeek(),
        ]);

        $this->artisan('subscriptions:charge', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No due subscriptions');
    }

    public function test_charge_creates_payment_and_calls_sumup(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_charge_123',
            'sumup_customer_id' => 'cust_charge_123',
            'box_size' => 'medium',
            'next_charge_date' => now()->subDay(),
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('createRecurringCheckout')
            ->once()
            ->withArgs(function ($amount, $customerId, $ref, $desc) {
                return $amount === 35.0
                    && $customerId === 'cust_charge_123'
                    && str_contains($ref, 'sub-')
                    && str_contains($ref, '-charge-');
            })
            ->andReturn((object) ['id' => 'checkout_abc123']);
        $sumUpService->shouldReceive('processCheckout')
            ->once()
            ->with('checkout_abc123', 'tok_charge_123', 'cust_charge_123')
            ->andReturn((object) ['status' => 'PENDING']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('subscriptions:charge')
            ->assertSuccessful()
            ->expectsOutputToContain('Charged 1 subscription(s)');

        $subscription->refresh();
        $weekStart = BillingFulfillmentWeek::weekStartForScheduledCharge($subscription, Carbon::today());

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'amount' => 35,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_abc123',
            'charge_initiator' => PaymentChargeInitiator::Scheduled->value,
        ]);

        $payment = Payment::query()->where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame($weekStart->toDateString(), $payment->fulfillment_week_start?->toDateString());
    }

    public function test_charge_uses_size_price_even_during_first_billing_period(): void
    {
        // W2-16 item 2: the trial-flat-first-charge override is gone. A
        // small box with the trial billing still pending used to charge
        // 35 EUR (the flat trial price); now it charges 20 EUR (the
        // size-based price), same as every other charge for this size.
        $user = User::factory()->create();
        $subscription = Subscription::factory()->withPendingTrialBilling()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_trial_small',
            'sumup_customer_id' => 'cust_trial_small',
            'box_size' => 'small',
            'next_charge_date' => now()->subDay(),
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('createRecurringCheckout')
            ->once()
            ->withArgs(function ($amount, $customerId, $ref, $desc) {
                return $amount === 20.0
                    && $customerId === 'cust_trial_small';
            })
            ->andReturn((object) ['id' => 'checkout_trial_small']);
        $sumUpService->shouldReceive('processCheckout')
            ->once()
            ->with('checkout_trial_small', 'tok_trial_small', 'cust_trial_small')
            ->andReturn((object) ['status' => 'PENDING']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('subscriptions:charge')
            ->assertSuccessful()
            ->expectsOutputToContain('Charged 1 subscription(s)');

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'amount' => 20,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_trial_small',
        ]);
    }

    public function test_dry_run_excludes_subscription_already_paid_for_fulfillment_week(): void
    {
        $this->travelTo(Carbon::parse('2025-06-18 12:00:00', 'UTC'));

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_123',
            'sumup_customer_id' => 'cust_123',
            'next_charge_date' => Carbon::parse('2025-06-17'),
        ]);

        $subscription->payments()->create([
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PAID,
            'attempt_number' => 1,
            'paid_at' => now(),
            'fulfillment_week_start' => '2025-06-16',
            'charge_initiator' => PaymentChargeInitiator::Scheduled,
        ]);

        $this->artisan('subscriptions:charge', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No due subscriptions');
    }

    public function test_charge_excludes_subscription_with_retryable_failed_payment(): void
    {
        Config::set('payments.max_retries', 3);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_123',
            'sumup_customer_id' => 'cust_123',
            'next_charge_date' => now()->subDay(),
        ]);
        $subscription->payments()->create([
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => 1,
            'failed_at' => now(),
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldNotReceive('createRecurringCheckout');
        $sumUpService->shouldNotReceive('processCheckout');
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('subscriptions:charge', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No due subscriptions');
    }

    public function test_charge_excludes_subscription_with_exhausted_failed_payment(): void
    {
        Config::set('payments.max_retries', 3);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_123',
            'sumup_customer_id' => 'cust_123',
            'next_charge_date' => now()->subDay(),
        ]);
        $subscription->payments()->create([
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => 3,
            'failed_at' => now(),
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldNotReceive('createRecurringCheckout');
        $sumUpService->shouldNotReceive('processCheckout');
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('subscriptions:charge', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No due subscriptions');
    }

    public function test_charge_marks_payment_failed_on_sumup_exception(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_fail',
            'sumup_customer_id' => 'cust_fail',
            'next_charge_date' => now()->subDay(),
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('createRecurringCheckout')
            ->once()
            ->andThrow(new \RuntimeException('SumUp API error'));
        $sumUpService->shouldNotReceive('processCheckout');
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('subscriptions:charge')
            ->assertSuccessful()
            ->expectsOutputToContain('Failed: 1');

        $payment = Payment::where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $this->assertNotNull($payment->failed_at);
    }

    public function test_charge_excludes_cancelled_subscription(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Cancelled,
            'sumup_token' => 'tok_123',
            'sumup_customer_id' => 'cust_123',
            'next_charge_date' => now()->subDay(),
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldNotReceive('createRecurringCheckout');
        $sumUpService->shouldNotReceive('processCheckout');
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('subscriptions:charge')
            ->assertSuccessful()
            ->expectsOutputToContain('No due subscriptions');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_charge_excludes_paused_subscription_live(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_123',
            'sumup_customer_id' => 'cust_123',
            'next_charge_date' => now()->subDay(),
            'pause_from' => now()->subDays(2),
            'pause_until' => now()->addDays(2),
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldNotReceive('createRecurringCheckout');
        $sumUpService->shouldNotReceive('processCheckout');
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('subscriptions:charge')
            ->assertSuccessful()
            ->expectsOutputToContain('No due subscriptions');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_charge_marks_payment_failed_when_no_checkout_id_returned(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_noid',
            'sumup_customer_id' => 'cust_noid',
            'box_size' => 'medium',
            'next_charge_date' => now()->subDay(),
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('createRecurringCheckout')
            ->once()
            ->andReturn((object) ['id' => null]);
        $sumUpService->shouldNotReceive('processCheckout');
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('subscriptions:charge')
            ->assertSuccessful()
            ->expectsOutputToContain('Charged 1 subscription(s)');

        $payment = Payment::where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $this->assertStringContainsString('no ID returned', $payment->failure_reason);
    }

    public function test_charge_handles_multiple_due_subscriptions(): void
    {
        $user1 = User::factory()->create();
        $sub1 = Subscription::factory()->create([
            'user_id' => $user1->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_multi_1',
            'sumup_customer_id' => 'cust_multi_1',
            'box_size' => 'medium',
            'next_charge_date' => now()->subDay(),
        ]);

        $user2 = User::factory()->create();
        $sub2 = Subscription::factory()->create([
            'user_id' => $user2->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_multi_2',
            'sumup_customer_id' => 'cust_multi_2',
            'box_size' => 'small',
            'next_charge_date' => now()->subDay(),
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('createRecurringCheckout')
            ->twice()
            ->andReturn((object) ['id' => 'checkout_m1'], (object) ['id' => 'checkout_m2']);
        $sumUpService->shouldReceive('processCheckout')->twice();
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('subscriptions:charge')
            ->assertSuccessful()
            ->expectsOutputToContain('Charged 2 subscription(s)');

        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('payments', ['subscription_id' => $sub1->id, 'status' => Payment::STATUS_PENDING]);
        $this->assertDatabaseHas('payments', ['subscription_id' => $sub2->id, 'status' => Payment::STATUS_PENDING]);
    }
}
