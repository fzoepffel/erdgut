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
