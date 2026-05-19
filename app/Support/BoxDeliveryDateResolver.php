<?php

namespace App\Support;

use App\Models\Box;
use App\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * Resolves a box's *actual* delivery date.
 *
 * Today's `DeliveryProjector` hardcodes Monday + 2 = Wednesday. Daily
 * inventory needs to know which calendar date each box ships on so we
 * can ask "is there active stock on that date". This helper centralises
 * the resolution so we don't sprinkle weekday arithmetic across the
 * service layer.
 *
 * Resolution order:
 *   1. The subscription's `delivery_weekday` if set.
 *   2. The first entry in `config('delivery.zones.<default>.allowed_weekdays')`.
 *   3. Hardcoded fallback 3 (Wednesday).
 *
 * Weekday integers use Carbon's `dayOfWeek` convention (0 = Sunday,
 * 1 = Monday, …, 6 = Saturday).
 */
class BoxDeliveryDateResolver
{
    /**
     * Resolve the delivery `Carbon` for a given box.
     */
    public static function deliveryDate(Box $box): Carbon
    {
        $monday = Carbon::parse($box->week_start_date)->startOfDay();
        $weekday = self::weekdayForBox($box);

        return self::shiftMondayToWeekday($monday, $weekday);
    }

    /**
     * Resolve the delivery `Carbon` for a given week + optional
     * subscription. Used by the box-setup admin surface which needs
     * to project a default delivery date for the upcoming week before
     * any concrete `Box` row exists.
     */
    public static function deliveryDateForWeek(Carbon $weekStart, ?Subscription $subscription = null): Carbon
    {
        $monday = $weekStart->copy()->startOfDay();
        $weekday = self::weekdayFor($subscription);

        return self::shiftMondayToWeekday($monday, $weekday);
    }

    /**
     * The numeric weekday this box ships on. Public so callers that
     * want the integer (not a Carbon date) can grab it without
     * recomputing.
     */
    public static function weekdayForBox(Box $box): int
    {
        $subscription = $box->relationLoaded('subscription')
            ? $box->getRelation('subscription')
            : $box->subscription;

        return self::weekdayFor($subscription);
    }

    private static function weekdayFor(?Subscription $subscription): int
    {
        if ($subscription !== null && $subscription->delivery_weekday !== null) {
            return (int) $subscription->delivery_weekday;
        }

        return self::defaultWeekday();
    }

    private static function defaultWeekday(): int
    {
        $zone = config('delivery.default_zone', 'berlin_core');
        $allowed = config('delivery.zones.'.$zone.'.allowed_weekdays', []);

        if (is_array($allowed) && isset($allowed[0]) && is_int($allowed[0])) {
            return $allowed[0];
        }

        // Last-resort fallback — every customer currently delivers on
        // Wednesday so this matches today's behaviour even if config
        // gets misshaped.
        return 3;
    }

    /**
     * Carbon's `dayOfWeek` is 0=Sun..6=Sat. Mondays are `dayOfWeek=1`.
     * Shift the Monday forward to the target weekday within the same
     * ISO week. If the target weekday is Sunday (0) we land 6 days
     * after Monday.
     */
    private static function shiftMondayToWeekday(Carbon $monday, int $targetWeekday): Carbon
    {
        $mondayDow = 1; // Carbon dayOfWeek for Monday
        $delta = ($targetWeekday - $mondayDow + 7) % 7;

        return $monday->copy()->addDays($delta);
    }
}
