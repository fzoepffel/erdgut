<?php

namespace App\Support;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionSkipWeek;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionSkipService
{
    /**
     * Replace the user-sourced skip weeks for a subscription within the next 12 weeks.
     *
     * @param  list<string>  $weekStartDates  ISO-8601 Monday dates (YYYY-MM-DD)
     */
    public function syncUserSkipWeeks(Subscription $subscription, array $weekStartDates, int $actorUserId): Subscription
    {
        $today = CarbonImmutable::today();
        $horizonEnd = $today->copy()->addWeeks(12)->startOfWeek();

        $normalized = collect($weekStartDates)
            ->map(static fn (string $value): string => CarbonImmutable::parse($value)->startOfWeek()->toDateString())
            ->unique()
            ->values()
            ->all();

        return DB::transaction(function () use ($subscription, $normalized, $today, $horizonEnd, $actorUserId): Subscription {
            $subscription->skipWeeks()
                ->where('source', SubscriptionSkipWeek::SOURCE_USER)
                ->whereBetween('week_start_date', [$today->startOfWeek()->toDateString(), $horizonEnd->toDateString()])
                ->delete();

            foreach ($normalized as $monday) {
                SubscriptionSkipWeek::updateOrCreate(
                    [
                        'subscription_id' => $subscription->id,
                        'week_start_date' => $monday,
                    ],
                    [
                        'source' => SubscriptionSkipWeek::SOURCE_USER,
                    ]
                );
            }

            $this->advanceNextChargeDateIfSkipped($subscription);

            Log::info('subscription.skip_weeks_updated', [
                'subscription_id' => $subscription->id,
                'subscription_user_id' => $subscription->user_id,
                'actor_user_id' => $actorUserId,
                'skip_weeks' => $normalized,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $subscription->fresh();
        });
    }

    private function advanceNextChargeDateIfSkipped(Subscription $subscription): void
    {
        if ($subscription->status !== SubscriptionStatus::Active) {
            return;
        }

        $nextCharge = $subscription->next_charge_date;
        if ($nextCharge === null) {
            return;
        }

        $step = $subscription->plan === SubscriptionPlan::Biweekly ? 2 : 1;
        $cursor = CarbonImmutable::parse($nextCharge)->startOfWeek();
        $horizonEnd = CarbonImmutable::today()->addWeeks(12)->startOfWeek();

        while ($cursor->lessThanOrEqualTo($horizonEnd)) {
            $exists = $subscription->skipWeeks()
                ->whereDate('week_start_date', $cursor->toDateString())
                ->exists();

            if (! $exists) {
                break;
            }

            $cursor = $cursor->addWeeks($step);
        }

        if ($cursor->toDateString() !== CarbonImmutable::parse($nextCharge)->startOfWeek()->toDateString()) {
            $subscription->update(['next_charge_date' => $cursor->toDateString()]);
        }
    }
}
