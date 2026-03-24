<?php

namespace App\Support;

use App\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * Billing fulfillment week anchor (Monday) aligned with {@see Box::$week_start_date}.
 *
 * For scheduled charges: Monday of the ISO week containing the subscription's
 * {@see Subscription::$next_charge_date}, or of today when that date is null.
 */
final class BillingFulfillmentWeek
{
    public static function weekStartForScheduledCharge(Subscription $subscription, Carbon $today): Carbon
    {
        $anchor = $subscription->next_charge_date !== null
            ? Carbon::parse($subscription->next_charge_date)->startOfDay()
            : $today->copy()->startOfDay();

        return $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }
}
