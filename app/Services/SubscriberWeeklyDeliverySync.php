<?php

namespace App\Services;

use App\Models\Box;
use App\Models\Delivery;
use App\Services\Admin\WeeklyBoxPlannerService;
use App\Support\DeliveryProjector;
use Illuminate\Support\Carbon;

/**
 * Keeps canonical (non-override) {@see Delivery} rows aligned with weekly {@see Box} weeks.
 *
 * {@see WeeklyBoxPlannerService} creates boxes on a schedule; without
 * this, the dashboard and deliveries timeline stay empty even when boxes exist (common
 * after seed/cron drift on servers). Delegates the actual row write to
 * {@see DeliveryProjector} so box_id FKs and upsert semantics stay consistent.
 */
final class SubscriberWeeklyDeliverySync
{
    public function __construct(
        private readonly DeliveryProjector $projector,
    ) {}

    /**
     * Canonical ship day: Wednesday of the ISO week that starts Monday.
     *
     * Kept for backwards compatibility; internally delegates to {@see DeliveryProjector}.
     */
    public static function scheduledDateForBoxWeek(Carbon $weekStartMonday): Carbon
    {
        return DeliveryProjector::scheduledDateForBoxWeek($weekStartMonday);
    }

    public function syncForUserWeek(int $userId, Carbon $weekStartMonday): void
    {
        $weekStartMonday = $weekStartMonday->copy()->startOfDay();

        $box = Box::query()
            ->where('user_id', $userId)
            ->whereDate('week_start_date', $weekStartMonday->toDateString())
            ->orderByDesc('id')
            ->first();

        if ($box === null) {
            return;
        }

        $this->projector->projectForBox($box);
    }
}
