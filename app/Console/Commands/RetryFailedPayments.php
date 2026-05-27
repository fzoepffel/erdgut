<?php

namespace App\Console\Commands;

use App\Enums\PaymentChargeInitiator;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionRecurringChargeService;
use App\Support\SumUpCheckoutLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetryFailedPayments extends Command
{
    protected $signature = 'payments:retry
                            {--dry-run : List retryable payments without charging}';

    protected $description = 'Retry failed payment attempts below threshold';

    public function __construct(
        private readonly SubscriptionRecurringChargeService $recurringChargeService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $maxRetries = (int) config('payments.max_retries', 3);
        $retryable = $this->getRetryableSubscriptions($maxRetries);

        if ($retryable->isEmpty()) {
            $this->info('No retryable failed payments.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$retryable->count()} subscription(s) would be retried.");
            $this->table(
                ['Subscription ID', 'User', 'Attempt', 'Failure reason'],
                $retryable->map(fn (array $row) => [
                    $row['subscription']->id,
                    $row['subscription']->user?->email ?? '—',
                    $row['payment']->attempt_number,
                    $row['payment']->failure_reason ?? '—',
                ])->toArray()
            );

            return self::SUCCESS;
        }

        $currency = (string) config('sumup.subscription.currency', 'EUR');
        $retried = 0;
        $failed = 0;

        foreach ($retryable as $row) {
            try {
                $this->retrySubscription($row['subscription'], $row['payment'], $currency);
                $retried++;
                Log::info('payment.retry_initiated', [
                    'subscription_id' => $row['subscription']->id,
                    'timestamp' => now()->toIso8601String(),
                ]);
            } catch (Throwable $e) {
                $failed++;
                Log::warning('payment.retry_failed', [
                    'payment_id' => $row['payment']->id,
                    'subscription_id' => $row['subscription']->id,
                    'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
                    'timestamp' => now()->toIso8601String(),
                ]);
            }
        }

        $this->info("Retried {$retried} payment(s).".($failed > 0 ? " Failed: {$failed}." : ''));

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{subscription: Subscription, payment: Payment}>
     */
    private function getRetryableSubscriptions(int $maxRetries): Collection
    {
        $failedPayments = Payment::query()
            ->where('status', Payment::STATUS_FAILED)
            ->whereColumn('attempt_number', '<', $maxRetries)
            ->with(['subscription.user'])
            ->orderByDesc('attempt_number')
            ->get();

        $result = collect();
        $seenSubscriptionIds = [];

        foreach ($failedPayments as $payment) {
            $subscription = $payment->subscription;
            if ($subscription === null) {
                continue;
            }
            if (isset($seenSubscriptionIds[$subscription->id])) {
                continue;
            }
            if ($subscription->status !== SubscriptionStatus::Active) {
                continue;
            }
            if (! $subscription->hasActiveSumupCredentials()) {
                continue;
            }
            $hasPending = $subscription->payments()->where('status', Payment::STATUS_PENDING)->exists();
            if ($hasPending) {
                continue;
            }

            $seenSubscriptionIds[$subscription->id] = true;
            $result->push(['subscription' => $subscription, 'payment' => $payment]);
        }

        return $result;
    }

    private function retrySubscription(Subscription $subscription, Payment $previousPayment, string $currency): void
    {
        $amount = (float) $previousPayment->amount;

        $this->recurringChargeService->initiate(
            subscription: $subscription,
            amount: $amount,
            currency: $currency,
            initiator: PaymentChargeInitiator::Retry,
            checkoutDescription: "Subscription #{$subscription->id} charge (retry)",
            initiatedByUserId: null,
            boxId: null,
            fulfillmentWeekStart: null,
            previousPaymentId: $previousPayment->id,
        );
    }
}
