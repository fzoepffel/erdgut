<?php

namespace Tests\Unit\Services\Onboarding;

use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\PaymentChargeInitiator;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Onboarding\TrialOrderService;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

/**
 * Verifies the server-side check used by the trial return-url. This is the
 * regression guard for a bug where the browser-supplied `?paid=1` marked the
 * order paid even though SumUp still reported the checkout as PENDING.
 */
class TrialOrderServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    public function test_confirm_paid_from_provider_marks_order_paid_when_sumup_reports_paid(): void
    {
        Mail::fake();

        [$order, $payment] = $this->makePendingOrderWithCheckout('chk_paid');

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('getCheckout')
            ->once()
            ->with('chk_paid')
            ->andReturn((object) ['status' => 'PAID']);
        $this->app->instance(SumUpService::class, $sumUp);

        $result = $this->app->make(TrialOrderService::class)->confirmPaidFromProvider($order);

        $this->assertTrue($result);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
    }

    public function test_confirm_paid_from_provider_does_not_mark_paid_when_sumup_reports_pending(): void
    {
        // Regression: this was the live bug — client said success, but SumUp
        // still had status PENDING and no transactions. We must not mark paid.
        Mail::fake();

        [$order, $payment] = $this->makePendingOrderWithCheckout('chk_pending');

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('getCheckout')
            ->once()
            ->with('chk_pending')
            ->andReturn((object) ['status' => 'PENDING', 'transactions' => []]);
        $this->app->instance(SumUpService::class, $sumUp);

        $result = $this->app->make(TrialOrderService::class)->confirmPaidFromProvider($order);

        $this->assertFalse($result);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_confirm_paid_from_provider_is_idempotent_when_already_paid(): void
    {
        [$order] = $this->makePendingOrderWithCheckout('chk_already');
        $order->forceFill(['status' => OrderStatus::Paid->value])->save();

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldNotReceive('getCheckout');
        $this->app->instance(SumUpService::class, $sumUp);

        $result = $this->app->make(TrialOrderService::class)->confirmPaidFromProvider($order->fresh());

        $this->assertTrue($result);
    }

    public function test_confirm_paid_from_provider_returns_false_when_no_payment_with_checkout_id(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox->value,
            'box_size' => 'medium',
            'amount' => 35,
            'currency' => 'EUR',
            'status' => OrderStatus::PendingPayment->value,
            'is_test' => true,
        ]);

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldNotReceive('getCheckout');
        $this->app->instance(SumUpService::class, $sumUp);

        $result = $this->app->make(TrialOrderService::class)->confirmPaidFromProvider($order);

        $this->assertFalse($result);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_confirm_paid_from_provider_returns_false_when_sumup_throws(): void
    {
        [$order, $payment] = $this->makePendingOrderWithCheckout('chk_error');

        $sumUp = Mockery::mock(SumUpService::class);
        $sumUp->shouldReceive('getCheckout')
            ->once()
            ->with('chk_error')
            ->andThrow(new \RuntimeException('network exploded'));
        $this->app->instance(SumUpService::class, $sumUp);

        $result = $this->app->make(TrialOrderService::class)->confirmPaidFromProvider($order);

        $this->assertFalse($result);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_create_order_uses_size_based_pricing(): void
    {
        config()->set('box.included_value_by_size', [
            'small' => 20.0,
            'medium' => 35.0,
            'large' => 42.0,
        ]);

        $user = User::factory()->create();
        $service = $this->app->make(TrialOrderService::class);

        $order = $service->createOrder($user, [
            'box_size' => 'large',
            'preferences' => [],
            'allergies' => [],
        ]);

        $this->assertSame('42.00', (string) $order->amount);
    }

    public function test_create_order_adds_eggs_dairy_add_on(): void
    {
        config()->set('box.included_value_by_size', [
            'small' => 20.0,
            'medium' => 35.0,
            'large' => 42.0,
        ]);
        config()->set('box.eggs_dairy_add_on_eur', 4.5);

        $user = User::factory()->create();
        $service = $this->app->make(TrialOrderService::class);

        $order = $service->createOrder($user, [
            'box_size' => 'small',
            'preferences' => ['eggs_dairy_add_on' => true],
            'allergies' => [],
        ]);

        $this->assertSame('24.50', (string) $order->amount);
    }

    /**
     * @return array{0: Order, 1: Payment}
     */
    private function makePendingOrderWithCheckout(string $checkoutId): array
    {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox->value,
            'box_size' => 'medium',
            'amount' => 35,
            'currency' => 'EUR',
            'status' => OrderStatus::PendingPayment->value,
            'is_test' => true,
        ]);

        $payment = new Payment([
            'order_id' => $order->id,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'status' => Payment::STATUS_PENDING,
            'attempt_number' => 1,
            'charge_initiator' => PaymentChargeInitiator::TrialCustomer->value,
            'initiated_by_user_id' => $user->id,
        ]);
        $payment->save();
        $payment->forceFill(['sumup_checkout_id' => $checkoutId])->save();

        return [$order, $payment];
    }
}
