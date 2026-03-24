<?php

namespace App\Services\Billing;

use App\Console\Commands\ChargeSubscriptions;
use App\Console\Commands\RetryFailedPayments;
use App\Enums\PaymentChargeInitiator;
use App\Models\Box;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\SumUpCheckoutLogger;
use App\Support\SumUpService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Single entry point for subscription recurring SumUp checkouts and payment rows.
 *
 * Used by {@see ChargeSubscriptions},
 * {@see RetryFailedPayments}, and future admin "Run charge".
 */
final class SubscriptionRecurringChargeService
{
    public function __construct(
        private readonly SumUpService $sumUpService
    ) {}

    /**
     * @param  ?int  $previousPaymentId  For retry initiator logging only.
     */
    public function initiate(
        Subscription $subscription,
        float $amount,
        string $currency,
        PaymentChargeInitiator $initiator,
        string $checkoutDescription,
        ?int $initiatedByUserId = null,
        ?int $boxId = null,
        ?Carbon $fulfillmentWeekStart = null,
        ?int $previousPaymentId = null,
    ): void {
        $attemptNumber = (int) $subscription->payments()->max('attempt_number') + 1;

        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'box_id' => $boxId,
            'fulfillment_week_start' => $fulfillmentWeekStart?->toDateString(),
            'charge_initiator' => $initiator,
            'initiated_by_user_id' => $initiatedByUserId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => Payment::STATUS_PENDING,
            'attempt_number' => $attemptNumber,
        ]);

        if ($initiator === PaymentChargeInitiator::Retry) {
            Log::info('payment.retry_created', [
                'payment_id' => $payment->id,
                'previous_payment_id' => $previousPaymentId,
                'subscription_id' => $subscription->id,
                'attempt_number' => $attemptNumber,
                'timestamp' => now()->toIso8601String(),
            ]);
        } else {
            Log::info('payment.created', [
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
                'amount' => $amount,
                'attempt_number' => $attemptNumber,
                'charge_initiator' => $initiator->value,
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        $checkoutReference = "sub-{$subscription->id}-charge-{$payment->id}-".now()->timestamp;

        try {
            $checkout = $this->sumUpService->createRecurringCheckout(
                amount: $amount,
                customerId: (string) $subscription->sumup_customer_id,
                checkoutReference: $checkoutReference,
                description: $checkoutDescription
            );

            $checkoutId = data_get($checkout, 'id');
            if (! is_string($checkoutId) || trim($checkoutId) === '') {
                $payment->markFailed('Checkout created but no ID returned');
                Log::warning('payment.failed', [
                    'payment_id' => $payment->id,
                    'subscription_id' => $subscription->id,
                    'previous_status' => Payment::STATUS_PENDING,
                    'new_status' => Payment::STATUS_FAILED,
                    'reason' => 'Checkout created but no ID returned',
                    'timestamp' => now()->toIso8601String(),
                ]);

                return;
            }

            $payment->update(['sumup_checkout_id' => $checkoutId]);

            $this->sumUpService->processCheckout(
                $checkoutId,
                (string) $subscription->sumup_token,
                (string) $subscription->sumup_customer_id
            );
        } catch (Throwable $e) {
            $payment->markFailed(SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()));
            Log::warning('payment.failed', [
                'payment_id' => $payment->id,
                'subscription_id' => $subscription->id,
                'previous_status' => Payment::STATUS_PENDING,
                'new_status' => Payment::STATUS_FAILED,
                'reason' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
                'timestamp' => now()->toIso8601String(),
            ]);

            throw $e;
        }
    }

    public static function resolveBoxIdForUserWeek(int $userId, Carbon $weekStart): ?int
    {
        return Box::query()
            ->where('user_id', $userId)
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->value('id');
    }
}
