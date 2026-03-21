<?php

namespace App\Support;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            return;
        }

        $idempotencyKey = "{$eventType}:{$checkoutId}";

        $inserted = DB::table('webhook_idempotency_keys')->insertOrIgnore([
            'key' => $idempotencyKey,
            'created_at' => now(),
        ]);

        if ($inserted === 0) {
            return;
        }

        try {
            $checkout = $this->sumUpService->getCheckout($checkoutId);
        } catch (Throwable $e) {
            Log::warning('SumUp webhook: failed to fetch checkout for verification.', [
                'checkout_id' => $checkoutId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $payment = Payment::query()
            ->where('sumup_checkout_id', $checkoutId)
            ->first();

        if ($payment === null) {
            return;
        }

        if ($payment->status !== Payment::STATUS_PENDING) {
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

            return;
        }

        if (in_array($status, ['FAILED', 'EXPIRED'], true)) {
            $reason = $this->extractFailureReason($checkout);
            $payment->markFailed($reason);
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
