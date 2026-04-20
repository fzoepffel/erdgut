<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Delivery;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Keeps canonical (non-override) {@see Delivery} rows aligned with weekly {@see Box} weeks.
 *
 * {@see WeeklyBoxPlannerService} creates boxes on a schedule; without this, the dashboard and
 * deliveries timeline stay empty even when boxes exist (common after seed/cron drift on servers).
 */
final class SubscriberWeeklyDeliverySync
{
    /**
     * Canonical ship day: Wednesday of the ISO week that starts Monday (same as {@see Database\Seeders\DemoSubscriptionBoxSeeder}).
     */
    public static function scheduledDateForBoxWeek(Carbon $weekStartMonday): Carbon
    {
        return $weekStartMonday->copy()->startOfDay()->addDays(2);
    }

    public function syncForUserWeek(int $userId, Carbon $weekStartMonday): void
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return;
        }

        $address = $user->primaryAddress()->first();
        if ($address === null) {
            return;
        }

        $weekStartMonday = $weekStartMonday->copy()->startOfDay();
        $scheduledFor = self::scheduledDateForBoxWeek($weekStartMonday);

        $subscription = Subscription::query()
            ->where('user_id', $userId)
            ->where('status', '!=', SubscriptionStatus::Cancelled)
            ->orderByDesc('updated_at')
            ->first();

        $timeslot = $user->deliveryPreference?->preferred_timeslot;
        if ($timeslot === null || ! in_array($timeslot, Delivery::allowedTimeslots(), true)) {
            $timeslot = Delivery::allowedTimeslots()[0];
        }

        Delivery::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'scheduled_for_date' => $scheduledFor->toDateString(),
                'is_weekly_override' => false,
            ],
            [
                'address_id' => $address->id,
                'timeslot' => $timeslot,
                'status' => DeliveryStatus::Scheduled,
                'special_instructions' => null,
                'is_test' => (bool) ($subscription?->is_test ?? $user->is_test),
            ],
        );
    }
}
