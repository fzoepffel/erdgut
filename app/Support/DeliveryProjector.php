<?php

namespace App\Support;

use App\Enums\DeliveryStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Single write path for Delivery rows.
 *
 * All trial-order, subscription-weekly, and admin-reschedule flows funnel through
 * this service so box_id linkage, upsert semantics, and defaults stay consistent.
 */
class DeliveryProjector
{
    /**
     * Canonical ship day: Wednesday of the ISO week that starts Monday.
     */
    public static function scheduledDateForBoxWeek(Carbon $weekStartMonday): Carbon
    {
        return $weekStartMonday->copy()->startOfDay()->addDays(2);
    }

    /**
     * Upsert the canonical (non-override) delivery for a subscription week's box.
     * Returns null when the user has no primary address (nothing to ship to yet).
     */
    public function projectForBox(Box $box): ?Delivery
    {
        $user = $box->user;
        if ($user === null) {
            \Log::warning('DeliveryProjector: skipped box without user.', [
                'box_id' => $box->id,
                'week_start_date' => $box->week_start_date?->toDateString(),
            ]);

            return null;
        }

        $address = $user->primaryAddress()->first();
        if ($address === null) {
            \Log::warning('DeliveryProjector: skipped box because user has no primary address.', [
                'box_id' => $box->id,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'week_start_date' => $box->week_start_date?->toDateString(),
            ]);

            return null;
        }

        $weekStart = Carbon::parse($box->week_start_date)->startOfDay();
        $scheduledFor = self::scheduledDateForBoxWeek($weekStart);

        $subscription = $box->subscription
            ?? Subscription::query()
                ->where('user_id', $user->id)
                ->where('status', '!=', SubscriptionStatus::Cancelled)
                ->orderByDesc('updated_at')
                ->first();

        $timeslot = $this->resolveTimeslot($user);

        return Delivery::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'scheduled_for_date' => $scheduledFor->toDateString(),
                'is_weekly_override' => false,
            ],
            [
                'address_id' => $address->id,
                'box_id' => $box->id,
                'timeslot' => $timeslot,
                'status' => DeliveryStatus::Scheduled,
                'special_instructions' => null,
                'is_test' => (bool) ($subscription?->is_test ?? $user->is_test ?? false),
            ],
        );
    }

    /**
     * Create or update the single delivery row for a trial order.
     *
     * Trials may have their box composed after scheduling, so box_id is only
     * linked when it exists at call time.
     */
    public function projectForTrialOrder(Order $order, DateTimeInterface $scheduledFor, string $timeslot): Delivery
    {
        $existing = $order->deliveries()->first();
        $scheduledForDate = Carbon::parse($scheduledFor)->toDateString();

        if ($existing !== null) {
            $existing->update([
                'scheduled_for_date' => $scheduledForDate,
                'timeslot' => $timeslot,
                'address_id' => $order->address_id,
                'box_id' => $order->box_id,
            ]);

            return $existing->fresh();
        }

        return Delivery::create([
            'user_id' => $order->user_id,
            'address_id' => $order->address_id,
            'order_id' => $order->id,
            'box_id' => $order->box_id,
            'scheduled_for_date' => $scheduledForDate,
            'timeslot' => $timeslot,
            'status' => DeliveryStatus::Scheduled,
            'special_instructions' => $order->special_instructions,
            'is_weekly_override' => false,
            'is_test' => (bool) $order->is_test,
        ]);
    }

    private function resolveTimeslot(User $user): string
    {
        $preferred = $user->deliveryPreference?->preferred_timeslot;

        if ($preferred !== null && in_array($preferred, Delivery::allowedTimeslots(), true)) {
            return $preferred;
        }

        return Delivery::allowedTimeslots()[0];
    }
}
