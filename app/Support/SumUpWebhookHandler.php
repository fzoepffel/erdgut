<?php

namespace App\Support;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\PaymentSuccessNotification;
use Illuminate\Support\Facades\DB;
use Throwable;

class SumUpWebhookHandler
{
    public function __construct(
        private readonly SumUpService $sumUpService,
        private readonly SubscriptionLifecycleService $subscriptionLifecycleService
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleCheckoutStatusChanged(array $payload): void
    {
        $eventType = (string) data_get($payload, 'event_type', 'CHECKOUT_STATUS_CHANGED');
        $checkoutId = data_get($payload, 'id');

        if (! is_string($checkoutId) || trim($checkoutId) === '') {
            SumUpCheckoutLogger::warning('SumUp webhook: CHECKOUT_STATUS_CHANGED missing checkout id.', [
                'event_type' => $eventType,
            ]);

            return;
        }

        $idempotencyKey = "{$eventType}:{$checkoutId}";

        $inserted = DB::table('webhook_idempotency_keys')->insertOrIgnore([
            'key' => $idempotencyKey,
            'created_at' => now(),
        ]);

        if ($inserted === 0) {
            SumUpCheckoutLogger::info('SumUp webhook: duplicate event ignored (idempotency).', [
                'checkout_id' => $checkoutId,
                'event_type' => $eventType,
            ]);

            return;
        }

        SumUpCheckoutLogger::info('SumUp webhook: processing CHECKOUT_STATUS_CHANGED.', [
            'checkout_id' => $checkoutId,
            'event_type' => $eventType,
        ]);

        try {
            $checkout = $this->sumUpService->getCheckout($checkoutId);
        } catch (Throwable $e) {
            SumUpCheckoutLogger::warning('SumUp webhook: failed to fetch checkout for verification.', [
                'checkout_id' => $checkoutId,
                'exception_class' => $e::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
            ]);

            return;
        }

        $snapshot = SumUpCheckoutLogger::checkoutSnapshot($checkout);

        $payment = Payment::query()
            ->where('sumup_checkout_id', $checkoutId)
            ->first();

        if ($payment === null) {
            SumUpCheckoutLogger::info('SumUp webhook: no local payment row for checkout (expected for card setup / other flows).', [
                'checkout_id' => $checkoutId,
                'checkout' => $snapshot,
            ]);

            return;
        }

        if ($payment->status !== Payment::STATUS_PENDING) {
            SumUpCheckoutLogger::info('SumUp webhook: payment not pending; skipping state change.', [
                'checkout_id' => $checkoutId,
                'payment_id' => $payment->id,
                'payment_status' => $payment->status,
                'checkout' => $snapshot,
            ]);

            return;
        }

        $status = strtoupper((string) data_get($checkout, 'status', ''));

        if ($status === 'PAID') {
            $subscription = $payment->subscription;
            $shouldAdvanceNextChargeDate = $subscription !== null
                && $subscription->status === SubscriptionStatus::Active;

            DB::transaction(function () use ($payment, $subscription, $shouldAdvanceNextChargeDate): void {
                $payment->markPaid();

                if ($shouldAdvanceNextChargeDate) {
                    $nextChargeDate = $this->subscriptionLifecycleService->computeNextChargeDate($subscription);
                    $subscription->update(['next_charge_date' => $nextChargeDate]);
                }
            });

            SumUpCheckoutLogger::info('SumUp webhook: payment marked paid.', [
                'checkout_id' => $checkoutId,
                'payment_id' => $payment->id,
                'subscription_id' => $subscription?->id,
                'previous_status' => Payment::STATUS_PENDING,
                'new_status' => Payment::STATUS_PAID,
                'advanced_next_charge_date' => $shouldAdvanceNextChargeDate,
                'checkout' => $snapshot,
                'timestamp' => now()->toIso8601String(),
            ]);

            $user = $subscription?->user;
            if ($user !== null) {
                $user->notify(new PaymentSuccessNotification($payment));
            }

            return;
        }

        if (in_array($status, ['FAILED', 'EXPIRED'], true)) {
            $reason = $this->extractFailureReason($checkout);
            $payment->markFailed($reason);

            SumUpCheckoutLogger::warning('SumUp webhook: payment marked failed.', [
                'checkout_id' => $checkoutId,
                'payment_id' => $payment->id,
                'checkout_status' => $status,
                'previous_status' => Payment::STATUS_PENDING,
                'new_status' => Payment::STATUS_FAILED,
                'reason' => $reason,
                'checkout' => $snapshot,
                'timestamp' => now()->toIso8601String(),
            ]);

            $user = $payment->subscription?->user;
            if ($user !== null) {
                $user->notify(new PaymentFailedNotification($payment));
            }
        }
    }

    private function extractFailureReason(object $checkout): ?string
    {
        $transactions = data_get($checkout, 'transactions');

        if (! is_array($transactions) || empty($transactions)) {
            return null;
        }

        $first = $transactions[0];

        return data_get($first, 'status') ?? data_get($first, 'message');
    }
}
