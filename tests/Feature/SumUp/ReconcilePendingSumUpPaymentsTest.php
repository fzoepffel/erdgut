<?php

namespace Tests\Feature\SumUp;

use App\Enums\SubscriptionPlan;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class ReconcilePendingSumUpPaymentsTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    private function createPendingPayment(string $checkoutId, ?Carbon $updatedAt = null): Payment
    {
        $subscription = Subscription::factory()->create([
            'plan' => SubscriptionPlan::Weekly,
        ]);

        $payment = Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => $checkoutId,
        ]);

        if ($updatedAt !== null) {
            $payment->updated_at = $updatedAt;
            $payment->saveQuietly();
        }

        return $payment->fresh();
    }

    public function test_reconcile_marks_payment_paid_when_sumup_reports_paid(): void
    {
        $payment = $this->createPendingPayment('co_paid_1', Carbon::now()->subMinutes(10));

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')
            ->with('co_paid_1')
            ->andReturn((object) ['status' => 'PAID']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('sumup:reconcile-pending-payments')->assertSuccessful();

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_reconcile_skips_payments_still_pending_at_sumup(): void
    {
        $payment = $this->createPendingPayment('co_still_pending', Carbon::now()->subMinutes(10));

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')
            ->with('co_still_pending')
            ->andReturn((object) ['status' => 'PENDING']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('sumup:reconcile-pending-payments')->assertSuccessful();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_reconcile_marks_payment_failed_when_sumup_reports_failed(): void
    {
        $payment = $this->createPendingPayment('co_failed_1', Carbon::now()->subMinutes(10));

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')
            ->with('co_failed_1')
            ->andReturn((object) ['status' => 'FAILED', 'transactions' => []]);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('sumup:reconcile-pending-payments')->assertSuccessful();

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
    }

    public function test_reconcile_respects_min_age_option(): void
    {
        // Payment updated 30 seconds ago — should be ignored at default min-age=2
        $payment = $this->createPendingPayment('co_recent', Carbon::now()->subSeconds(30));

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldNotReceive('getCheckout');
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('sumup:reconcile-pending-payments')->assertSuccessful();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_dry_run_does_not_mutate_local_state(): void
    {
        $payment = $this->createPendingPayment('co_dryrun', Carbon::now()->subMinutes(10));

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')
            ->with('co_dryrun')
            ->andReturn((object) ['status' => 'PAID']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('sumup:reconcile-pending-payments', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_payment_option_targets_single_payment_and_ignores_min_age(): void
    {
        $recent = $this->createPendingPayment('co_single', Carbon::now()->subSeconds(5));

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('getCheckout')
            ->with('co_single')
            ->andReturn((object) ['status' => 'PAID']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->artisan('sumup:reconcile-pending-payments', ['--payment' => $recent->id])
            ->assertSuccessful();

        $this->assertSame(Payment::STATUS_PAID, $recent->fresh()->status);
    }
}
