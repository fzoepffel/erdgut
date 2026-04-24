<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Mail\SubscriptionPauseExhaustedMail;
use App\Models\Payment;
use App\Models\PaymentMandate;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PauseExhaustedSubscriptions extends Command
{
    protected $signature = 'subscriptions:pause-exhausted
                            {--dry-run : List subscriptions that would be paused}';

    protected $description = 'Pause subscriptions that have exceeded the payment retry limit';

    public function handle(): int
    {
        $maxRetries = (int) config('payments.max_retries', 3);
        $exhausted = $this->getExhaustedSubscriptions($maxRetries);

        if ($exhausted->isEmpty()) {
            $this->info('No exhausted subscriptions.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$exhausted->count()} subscription(s) would be paused.");
            $this->table(
                ['Subscription ID', 'User', 'Attempt', 'Failure reason'],
                $exhausted->map(fn (array $row) => [
                    $row['subscription']->id,
                    $row['subscription']->user?->email ?? '—',
                    $row['payment']->attempt_number,
                    $row['payment']->failure_reason ?? '—',
                ])->toArray()
            );

            return self::SUCCESS;
        }

        $paused = 0;
        foreach ($exhausted as $row) {
            $subscription = $row['subscription'];
            $subscription->pause();
            $paused++;
            Log::info('PauseExhaustedSubscriptions: paused subscription after max retries.', [
                'subscription_id' => $subscription->id,
            ]);

            $recipient = $subscription->user?->email;
            if ($recipient !== null && $recipient !== '') {
                Mail::to($recipient)->queue(new SubscriptionPauseExhaustedMail($subscription->fresh() ?? $subscription));
            }
        }

        $this->info("Paused {$paused} subscription(s).");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{subscription: Subscription, payment: Payment}>
     */
    private function getExhaustedSubscriptions(int $maxRetries): Collection
    {
        $subscriptions = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->where(function ($q) {
                $q->whereNotNull('sumup_token')
                    ->orWhereHas('paymentMandate', function ($pm) {
                        $pm->where('status', PaymentMandate::STATUS_ACTIVE)
                            ->whereNotNull('provider_token');
                    });
            })
            ->with(['user', 'paymentMandate'])
            ->get();

        $result = collect();
        foreach ($subscriptions as $subscription) {
            $latestPayment = $subscription->payments()->orderByDesc('attempt_number')->first();
            if ($latestPayment === null) {
                continue;
            }
            if ($latestPayment->status !== Payment::STATUS_FAILED) {
                continue;
            }
            if ($latestPayment->attempt_number < $maxRetries) {
                continue;
            }

            $result->push(['subscription' => $subscription, 'payment' => $latestPayment]);
        }

        return $result;
    }
}
