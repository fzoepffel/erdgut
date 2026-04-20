<?php

namespace App\Services;

use App\Console\Commands\SyncDeliveriesFromBoxes;
use App\Models\Box;
use App\Models\Delivery;
use Illuminate\Support\Carbon;

/**
 * Backfills canonical {@see Delivery} rows from existing {@see Box} weeks.
 *
 * Used by {@see SyncDeliveriesFromBoxes} and admin “sync from boxes”.
 */
final class DeliveriesFromBoxesSync
{
    public function __construct(
        private readonly SubscriberWeeklyDeliverySync $weeklyDeliverySync,
    ) {}

    /**
     * @param  Carbon|null  $from  Lower bound for box `week_start_date` (inclusive). Defaults to start of current ISO week.
     * @return int Number of distinct user-week pairs processed.
     */
    public function run(?Carbon $from = null): int
    {
        $from = ($from ?? Carbon::now()->startOfWeek())->copy()->startOfDay();

        $pairs = Box::query()
            ->whereDate('week_start_date', '>=', $from->toDateString())
            ->select(['user_id', 'week_start_date'])
            ->distinct()
            ->orderBy('week_start_date')
            ->orderBy('user_id')
            ->get();

        foreach ($pairs as $row) {
            $this->weeklyDeliverySync->syncForUserWeek((int) $row->user_id, Carbon::parse($row->week_start_date));
        }

        return $pairs->count();
    }
}
