<?php

namespace App\Services;

use App\Console\Commands\SyncDeliveriesFromBoxes;
use App\Enums\DeliveryStatus;
use App\Models\Box;
use App\Models\Delivery;
use Illuminate\Support\Carbon;

/**
 * Backfills canonical {@see Delivery} rows from existing {@see Box} weeks
 * AND prunes scheduled deliveries whose backing Box / Order has gone away.
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

        $this->pruneOrphans($from);

        return $pairs->count();
    }

    /**
     * Drop scheduled canonical deliveries that have nothing physical behind
     * them — both `box_id` and `order_id` are null (or point to a row that
     * no longer exists) and the row isn't a weekly override.
     *
     * Skips in-transit/delivered rows: those are historical and may have
     * lost their FK as the underlying box was edited; we don't rewrite
     * history.
     */
    private function pruneOrphans(Carbon $from): void
    {
        Delivery::query()
            ->whereDate('scheduled_for_date', '>=', $from->toDateString())
            ->where('status', DeliveryStatus::Scheduled)
            ->where('is_weekly_override', false)
            ->where(function ($q): void {
                // No source row at all.
                $q->where(function ($qq): void {
                    $qq->whereNull('box_id')->whereNull('order_id');
                });
            })
            ->delete();

        // Also drop scheduled canonical deliveries whose linked Box has
        // been deleted (FK is set to null by the deleter, leaving an
        // orphan row pointing at a vanished Box). Same for Orders.
        Delivery::query()
            ->whereDate('scheduled_for_date', '>=', $from->toDateString())
            ->where('status', DeliveryStatus::Scheduled)
            ->where('is_weekly_override', false)
            ->whereNotNull('box_id')
            ->whereDoesntHave('box')
            ->update(['box_id' => null]);

        Delivery::query()
            ->whereDate('scheduled_for_date', '>=', $from->toDateString())
            ->where('status', DeliveryStatus::Scheduled)
            ->where('is_weekly_override', false)
            ->whereNotNull('order_id')
            ->whereDoesntHave('order')
            ->update(['order_id' => null]);

        // Re-run the orphan delete in case the previous unlinks left rows
        // with both columns now null.
        Delivery::query()
            ->whereDate('scheduled_for_date', '>=', $from->toDateString())
            ->where('status', DeliveryStatus::Scheduled)
            ->where('is_weekly_override', false)
            ->whereNull('box_id')
            ->whereNull('order_id')
            ->delete();
    }
}
