<?php

namespace App\Services\Admin;

use App\Enums\PaymentChargeInitiator;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\BillingFulfillmentWeek;
use App\Support\ScheduledChargeEligibility;
use Illuminate\Support\Carbon;

/**
 * Weekly billing coverage matrix for admin (ERD-193).
 *
 * @phpstan-type CoverageCell array{state: string, payment_id: ?int}
 */
final class BillingCoverageService
{
    public function __construct(
        private readonly WeeklyBoxPlannerService $weeklyBoxPlanner
    ) {}

    /**
     * @return array{
     *     weeks: list<Carbon>,
     *     rows: list<array{subscription: Subscription, cells: list<CoverageCell>}>,
     * }
     */
    public function buildMatrix(Carbon $today): array
    {
        $firstMonday = WeeklyBoxPlannerService::defaultUpcomingWeekMonday($today);
        $weeks = $this->weeklyBoxPlanner->horizonWeekStarts($firstMonday);

        $subscriptions = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->with('user:id,email')
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($subscriptions as $subscription) {
            $cells = [];
            foreach ($weeks as $week) {
                $cells[] = $this->cellFor($subscription, $week, $today);
            }
            $rows[] = [
                'subscription' => $subscription,
                'cells' => $cells,
            ];
        }

        return [
            'weeks' => $weeks,
            'rows' => $rows,
        ];
    }

    /**
     * @return CoverageCell
     */
    public function cellFor(Subscription $subscription, Carbon $weekStart, Carbon $today): array
    {
        $weekStart = $weekStart->copy()->startOfDay();

        if (Payment::hasPaidForFulfillmentWeek($subscription->id, $weekStart)) {
            $paymentId = Payment::query()
                ->where('subscription_id', $subscription->id)
                ->where('status', Payment::STATUS_PAID)
                ->whereDate('fulfillment_week_start', $weekStart->toDateString())
                ->orderByDesc('id')
                ->value('id');

            return ['state' => 'paid', 'payment_id' => $paymentId !== null ? (int) $paymentId : null];
        }

        $pending = Payment::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', Payment::STATUS_PENDING)
            ->whereDate('fulfillment_week_start', $weekStart->toDateString())
            ->orderByDesc('id')
            ->first();

        // Any pending row for this week (not paid): classify by initiator.
        if ($pending !== null) {
            if ($pending->charge_initiator === PaymentChargeInitiator::AdminManual) {
                return ['state' => 'pending_manual', 'payment_id' => null];
            }

            return ['state' => 'pending_scheduled', 'payment_id' => null];
        }

        $scheduledWeek = BillingFulfillmentWeek::weekStartForScheduledCharge($subscription, $today);
        if ($weekStart->toDateString() === $scheduledWeek->toDateString()
            && ScheduledChargeEligibility::isDue($subscription, $today)) {
            return ['state' => 'scheduled', 'payment_id' => null];
        }

        return ['state' => 'empty', 'payment_id' => null];
    }
}
