<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\SumUpService;
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
        private readonly SumUpService $sumUpService
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

        $currency = (string) config('sumup.currency', 'EUR');
        $retried = 0;
        $failed = 0;

        foreach ($retryable as $row) {
            try {
                $this->retrySubscription($row['subscription'], $row['payment'], $currency);
                $retried++;
                Log::info('RetryFailedPayments: charged subscription.', [
                    'subscription_id' => $row['subscription']->id,
                ]);
            } catch (Throwable $e) {
                $failed++;
                Log::warning('RetryFailedPayments: retry failed for payment.', [
                    'payment_id' => $row['payment']->id,
                    'subscription_id' => $row['subscription']->id,
                    'error' => $e->getMessage(),
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
            if (empty($subscription->sumup_token)) {
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
        $attemptNumber = (int) $subscription->payments()->max('attempt_number') + 1;

        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => Payment::STATUS_PENDING,
            'attempt_number' => $attemptNumber,
        ]);

        $checkoutReference = "sub-{$subscription->id}-charge-{$payment->id}-".now()->timestamp;

        try {
            $checkout = $this->sumUpService->createRecurringCheckout(
                amount: $amount,
                customerId: (string) $subscription->sumup_customer_id,
                checkoutReference: $checkoutReference,
                description: "Subscription #{$subscription->id} charge (retry)"
            );

            $checkoutId = data_get($checkout, 'id');
            if (! is_string($checkoutId) || trim($checkoutId) === '') {
                $payment->markFailed('Checkout created but no ID returned');

                return;
            }

            $payment->update(['sumup_checkout_id' => $checkoutId]);

            $this->sumUpService->processCheckout(
                $checkoutId,
                (string) $subscription->sumup_token,
                (string) $subscription->sumup_customer_id
            );
        } catch (Throwable $e) {
            $payment->markFailed($e->getMessage());

            throw $e;
        }
    }
}
