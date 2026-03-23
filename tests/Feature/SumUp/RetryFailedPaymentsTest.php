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

class RetryFailedPaymentsTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    public function test_dry_run_lists_retryable_failed_payments(): void
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
            'attempt_number' => 1,
            'failed_at' => now(),
            'failure_reason' => 'Card declined',
        ]);

        $this->artisan('payments:retry', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('1 subscription(s) would be retried');
    }

    public function test_dry_run_excludes_failed_payment_at_max_retries(): void
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

        $this->artisan('payments:retry', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No retryable failed payments');
    }

    public function test_retry_creates_new_payment_and_calls_sumup(): void
    {
        Config::set('payments.max_retries', 3);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_retry_123',
            'sumup_customer_id' => 'cust_retry_123',
            'box_size' => 'medium',
        ]);
        $subscription->payments()->create([
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => 1,
            'failed_at' => now(),
            'failure_reason' => 'Card declined',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('createRecurringCheckout')
            ->once()
            ->withArgs(function ($amount, $customerId, $ref, $desc) {
                return $amount === 35.0
                    && $customerId === 'cust_retry_123'
                    && str_contains($ref, 'sub-')
                    && str_contains($ref, '-charge-')
                    && str_contains($desc, 'retry');
            })
            ->andReturn((object) ['id' => 'checkout_retry_123']);
        $sumUpService->shouldReceive('processCheckout')
            ->once()
            ->with('checkout_retry_123', 'tok_retry_123', 'cust_retry_123')
            ->andReturn((object) ['status' => 'PENDING']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('payments:retry')
            ->assertSuccessful()
            ->expectsOutputToContain('Retried 1 payment(s)');

        $newPayment = Payment::where('subscription_id', $subscription->id)
            ->where('attempt_number', 2)
            ->first();
        $this->assertNotNull($newPayment);
        $this->assertSame(Payment::STATUS_PENDING, $newPayment->status);
        $this->assertSame('checkout_retry_123', $newPayment->sumup_checkout_id);
    }

    public function test_retry_skips_subscription_with_pending_payment(): void
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
            'attempt_number' => 1,
            'failed_at' => now(),
        ]);
        $subscription->payments()->create([
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING,
            'attempt_number' => 2,
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldNotReceive('createRecurringCheckout');
        $sumUpService->shouldNotReceive('processCheckout');
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('payments:retry')
            ->assertSuccessful()
            ->expectsOutputToContain('No retryable failed payments');
    }

    public function test_retry_skips_subscription_without_token(): void
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
            'attempt_number' => 1,
            'failed_at' => now(),
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldNotReceive('createRecurringCheckout');
        $sumUpService->shouldNotReceive('processCheckout');
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('payments:retry')
            ->assertSuccessful()
            ->expectsOutputToContain('No retryable failed payments');
    }

    public function test_retry_marks_new_payment_failed_on_sumup_exception(): void
    {
        Config::set('payments.max_retries', 3);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_fail',
            'sumup_customer_id' => 'cust_fail',
        ]);
        $subscription->payments()->create([
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => 1,
            'failed_at' => now(),
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('createRecurringCheckout')
            ->once()
            ->andThrow(new \RuntimeException('SumUp API error'));
        $sumUpService->shouldNotReceive('processCheckout');
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('payments:retry')
            ->assertSuccessful()
            ->expectsOutputToContain('Failed: 1');

        $newPayment = Payment::where('subscription_id', $subscription->id)
            ->where('attempt_number', 2)
            ->first();
        $this->assertNotNull($newPayment);
        $this->assertSame(Payment::STATUS_FAILED, $newPayment->status);
        $this->assertNotNull($newPayment->failed_at);
    }

    public function test_retry_excludes_cancelled_subscription(): void
    {
        Config::set('payments.max_retries', 3);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Cancelled,
            'sumup_token' => 'tok_123',
            'sumup_customer_id' => 'cust_123',
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

        $this->artisan('payments:retry')
            ->assertSuccessful()
            ->expectsOutputToContain('No retryable failed payments');
    }

    public function test_retry_only_retries_one_payment_per_subscription(): void
    {
        Config::set('payments.max_retries', 5);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_multi',
            'sumup_customer_id' => 'cust_multi',
            'box_size' => 'medium',
        ]);
        $subscription->payments()->create([
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => 1,
            'failed_at' => now()->subHour(),
        ]);
        $subscription->payments()->create([
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => 2,
            'failed_at' => now(),
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('createRecurringCheckout')
            ->once()
            ->andReturn((object) ['id' => 'checkout_retry_multi']);
        $sumUpService->shouldReceive('processCheckout')->once();
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('payments:retry')
            ->assertSuccessful()
            ->expectsOutputToContain('Retried 1 payment(s)');

        $this->assertSame(3, Payment::where('subscription_id', $subscription->id)->count());
    }

    public function test_retry_marks_payment_failed_when_no_checkout_id_returned(): void
    {
        Config::set('payments.max_retries', 3);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_noid',
            'sumup_customer_id' => 'cust_noid',
        ]);
        $subscription->payments()->create([
            'amount' => 35,
            'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => 1,
            'failed_at' => now(),
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('createRecurringCheckout')
            ->once()
            ->andReturn((object) ['id' => null]);
        $sumUpService->shouldNotReceive('processCheckout');
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('payments:retry')
            ->assertSuccessful()
            ->expectsOutputToContain('Retried 1 payment(s)');

        $newPayment = Payment::where('subscription_id', $subscription->id)
            ->where('attempt_number', 2)
            ->first();
        $this->assertNotNull($newPayment);
        $this->assertSame(Payment::STATUS_FAILED, $newPayment->status);
        $this->assertStringContainsString('no ID returned', $newPayment->failure_reason);
    }
}
