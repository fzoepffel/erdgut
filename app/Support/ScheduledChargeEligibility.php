<?php

namespace App\Support;

use App\Console\Commands\ChargeSubscriptions;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * Whether an active subscription would be selected by {@see ChargeSubscriptions}
 * for a charge on {@see $today} (scheduled job semantics).
 */
final class ScheduledChargeEligibility
{
    public static function isDue(Subscription $subscription, Carbon $today): bool
    {
        if ($subscription->status !== SubscriptionStatus::Active) {
            return false;
        }

        $token = $subscription->sumup_token;
        if ($token === null || $token === '') {
            return false;
        }

        $nextCharge = $subscription->next_charge_date;
        if ($nextCharge !== null && $nextCharge->toDateString() > $today->toDateString()) {
            return false;
        }

        if ($subscription->isWithinPauseWindow($today)) {
            return false;
        }

        $latestPayment = $subscription->payments()->orderByDesc('attempt_number')->first();
        if ($latestPayment !== null) {
            if ($latestPayment->status === Payment::STATUS_PENDING || $latestPayment->status === Payment::STATUS_FAILED) {
                return false;
            }
        }

        $weekStart = BillingFulfillmentWeek::weekStartForScheduledCharge($subscription, $today);

        return ! Payment::hasPaidForFulfillmentWeek($subscription->id, $weekStart);
    }
}
