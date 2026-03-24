<?php

namespace Tests\Unit\Services\Billing;

use App\Enums\PaymentChargeInitiator;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\SubscriptionRecurringChargeService;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class SubscriptionRecurringChargeServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    public function test_initiate_creates_scheduled_payment_with_fulfillment_metadata(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create([
            'sumup_token' => 'tok_svc',
            'sumup_customer_id' => 'cust_svc',
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('createRecurringCheckout')
            ->once()
            ->andReturn((object) ['id' => 'chk_svc']);
        $sumUp->shouldReceive('processCheckout')
            ->once()
            ->with('chk_svc', 'tok_svc', 'cust_svc');
        $this->app->instance(SumUpService::class, $sumUp);

        $week = Carbon::parse('2025-07-07');
        $service = app(SubscriptionRecurringChargeService::class);
        $service->initiate(
            subscription: $subscription,
            amount: 35.0,
            currency: 'EUR',
            initiator: PaymentChargeInitiator::Scheduled,
            checkoutDescription: 'Test charge',
            initiatedByUserId: null,
            boxId: null,
            fulfillmentWeekStart: $week,
            previousPaymentId: null,
        );

        $payment = Payment::query()->where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(PaymentChargeInitiator::Scheduled, $payment->charge_initiator);
        $this->assertSame('2025-07-07', $payment->fulfillment_week_start?->toDateString());
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame('chk_svc', $payment->sumup_checkout_id);
    }

    public function test_initiate_records_retry_initiator_without_fulfillment_week(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create([
            'sumup_token' => 'tok_r',
            'sumup_customer_id' => 'cust_r',
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('createRecurringCheckout')->once()->andReturn((object) ['id' => 'chk_r']);
        $sumUp->shouldReceive('processCheckout')->once();
        $this->app->instance(SumUpService::class, $sumUp);

        $service = app(SubscriptionRecurringChargeService::class);
        $service->initiate(
            subscription: $subscription,
            amount: 40.0,
            currency: 'EUR',
            initiator: PaymentChargeInitiator::Retry,
            checkoutDescription: 'Retry',
            previousPaymentId: 99,
        );

        $payment = Payment::query()->where('subscription_id', $subscription->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(PaymentChargeInitiator::Retry, $payment->charge_initiator);
        $this->assertNull($payment->fulfillment_week_start);
    }
}
