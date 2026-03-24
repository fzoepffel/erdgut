<?php

namespace App\Services\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Subscriptions that would be considered billable in a date window: active,
 * non-empty payment token, next_charge_date in range, not paused on charge date.
 *
 * Shared by {@see AdminDashboardMetrics} and the admin upcoming-charges list.
 */
final class UpcomingBillableSubscriptions
{
    /**
     * @return Collection<int, Subscription>
     */
    public function billableBetween(Carbon $startInclusive, Carbon $endInclusive): Collection
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('sumup_token')
            ->whereNotNull('next_charge_date')
            ->whereDate('next_charge_date', '>=', $startInclusive)
            ->whereDate('next_charge_date', '<=', $endInclusive)
            ->get()
            ->filter(fn (Subscription $s): bool => filled($s->sumup_token))
            ->filter(fn (Subscription $s): bool => ! $s->isWithinPauseWindow(Carbon::parse($s->next_charge_date)))
            ->values();
    }
}
