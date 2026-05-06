<?php

namespace App\Support;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionSkipCleanupService
{
    public function cleanupWindow(Subscription $subscription, CarbonImmutable $from, CarbonImmutable $until): void
    {
        DB::transaction(function () use ($subscription, $from, $until): void {
            $now = now();
            $windowStart = $from->startOfWeek()->toDateString();
            $windowEnd = $until->endOfWeek()->toDateString();

            // K7 (UX notes): a Box should only be removed by skip-cleanup if
            // it is genuinely "in flight" still (Editable, or Locked but
            // before the modification deadline). We additionally guard
            // against the case where the operator has already moved the
            // linked Delivery out of `scheduled` (in_transit, delivered,
            // skipped, failed) — that's a sign the box has been physically
            // actioned and deleting it would lose history. The Delivery
            // model is the source of truth for that "did anything happen
            // for this week?" question; the Box.status field can lag
            // (because the I4 propagation hop isn't built yet).
            $boxes = Box::query()
                ->where('user_id', $subscription->user_id)
                ->whereBetween('week_start_date', [$from->toDateString(), $until->toDateString()])
                ->where(function ($query) use ($now): void {
                    $query->where('status', BoxStatus::Editable)
                        ->orWhere(function ($sub) use ($now): void {
                            $sub->where('status', BoxStatus::Locked)
                                ->where('modification_deadline_at', '>', $now);
                        });
                })
                ->whereDoesntHave('deliveries', function ($q): void {
                    $q->whereIn('status', [
                        DeliveryStatus::InTransit,
                        DeliveryStatus::Delivered,
                        DeliveryStatus::Skipped,
                        DeliveryStatus::Failed,
                    ]);
                })
                ->get();

            $deletedBoxIds = [];

            foreach ($boxes as $box) {
                $box->items()->delete();
                $box->delete();
                $deletedBoxIds[] = $box->id;
            }

            $cancelledDeliveries = Delivery::query()
                ->where('user_id', $subscription->user_id)
                ->whereBetween('scheduled_for_date', [$windowStart, $windowEnd])
                ->where('status', DeliveryStatus::Scheduled)
                ->delete();

            Log::info('subscription.skip_cleanup', [
                'subscription_id' => $subscription->id,
                'subscription_user_id' => $subscription->user_id,
                'pause_from' => $from->toDateString(),
                'pause_until' => $until->toDateString(),
                'deleted_box_ids' => $deletedBoxIds,
                'cancelled_deliveries_count' => $cancelledDeliveries,
                'timestamp' => now()->toIso8601String(),
            ]);
        });
    }
}
