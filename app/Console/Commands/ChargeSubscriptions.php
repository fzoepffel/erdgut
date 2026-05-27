<?php

namespace App\Console\Commands;

use App\Enums\BillingMode;
use App\Enums\PaymentChargeInitiator;
use App\Enums\SubscriptionStatus;
use App\Models\PaymentMandate;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionRecurringChargeService;
use App\Support\BillingFulfillmentWeek;
use App\Support\ScheduledChargeEligibility;
use App\Support\SumUpCheckoutLogger;
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
        private readonly SubscriptionRecurringChargeService $recurringChargeService
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

        $currency = (string) config('sumup.subscription.currency', 'EUR');
        $charged = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $this->chargeSubscription($subscription, $currency, $today);
                $charged++;
            } catch (Throwable $e) {
                $failed++;
                Log::warning('payment.charge_failed', [
                    'subscription_id' => $subscription->id,
                    'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
                    'timestamp' => now()->toIso8601String(),
                ]);
            }
        }

        $this->info("Charged {$charged} subscription(s).".($failed > 0 ? " Failed: {$failed}." : ''));

        return self::SUCCESS;
    }

    private function chargeSubscription(Subscription $subscription, string $currency, Carbon $today): void
    {
        $weekStart = BillingFulfillmentWeek::weekStartForScheduledCharge($subscription, $today);
        $boxId = SubscriptionRecurringChargeService::resolveBoxIdForUserWeek(
            (int) $subscription->user_id,
            $weekStart
        );

        $this->recurringChargeService->initiate(
            subscription: $subscription,
            amount: $subscription->expectedChargeAmount(),
            currency: $currency,
            initiator: PaymentChargeInitiator::Scheduled,
            checkoutDescription: "Subscription #{$subscription->id} charge",
            initiatedByUserId: null,
            boxId: $boxId,
            fulfillmentWeekStart: $weekStart,
            previousPaymentId: null,
        );
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function getDueSubscriptions(Carbon $today): Collection
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->where('billing_mode', BillingMode::SumupRecurring)
            ->where(function ($q) {
                // Either a linked active mandate, or the legacy per-subscription token
                // (during the mandate cutover). Both paths are resolved by
                // Subscription::activeSumupToken() at charge time.
                $q->whereNotNull('sumup_token')
                    ->orWhereHas('paymentMandate', function ($pm) {
                        $pm->where('status', PaymentMandate::STATUS_ACTIVE)
                            ->whereNotNull('provider_token');
                    });
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('next_charge_date')
                    ->orWhereDate('next_charge_date', '<=', $today);
            })
            ->with(['user', 'paymentMandate'])
            ->get()
            ->filter(fn (Subscription $s) => ScheduledChargeEligibility::isDue($s, $today));
    }
}
