<?php

namespace Tests\Feature\SumUp;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'amount' => 35,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'checkout_abc123',
        ]);
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
}
