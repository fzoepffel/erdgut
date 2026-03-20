<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\SumUpService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChargeSubscriptions extends Command
{
    protected $signature = 'subscriptions:charge
                            {--dry-run : List due subscriptions without charging}';

    protected $description = 'Charge due active subscriptions';

    public function __construct(
        private readonly SumUpService $sumUpService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = Carbon::today();
        $subscriptions = $this->getDueSubscriptions($today);

        if ($subscriptions->isEmpty()) {
            $this->info('No due subscriptions.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$subscriptions->count()} subscription(s) would be charged.");
            $this->table(
                ['ID', 'User', 'Next charge date'],
                $subscriptions->map(fn (Subscription $s) => [
                    $s->id,
                    $s->user?->email ?? '—',
                    $s->next_charge_date?->toDateString() ?? 'null',
                ])->toArray()
            );

            return self::SUCCESS;
        }

        $currency = (string) config('sumup.currency', 'EUR');
        $charged = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $this->chargeSubscription($subscription, $currency);
                $charged++;
            } catch (Throwable $e) {
                $failed++;
                Log::warning('ChargeSubscriptions: failed to charge subscription.', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Charged {$charged} subscription(s).".($failed > 0 ? " Failed: {$failed}." : ''));

        return self::SUCCESS;
    }

    private function chargeSubscription(Subscription $subscription, string $currency): void
    {
        $amount = (float) config("box.included_value_by_size.{$subscription->box_size}", 35);
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
                description: "Subscription #{$subscription->id} charge"
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

    /**
     * @return Collection<int, Subscription>
     */
    private function getDueSubscriptions(Carbon $today): Collection
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('sumup_token')
            ->where(function ($q) use ($today) {
                $q->whereNull('next_charge_date')
                    ->orWhereDate('next_charge_date', '<=', $today);
            })
            ->with('user')
            ->get()
            ->filter(fn (Subscription $s) => ! $s->isWithinPauseWindow($today));
    }
}
