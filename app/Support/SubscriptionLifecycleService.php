<?php

namespace App\Support;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionSkipWeek;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionLifecycleService
{
    public function __construct(private SubscriptionSkipCleanupService $skipCleanup) {}

    public function schedulePause(
        Subscription $subscription,
        string $pauseFrom,
        string $pauseUntil,
        int $actorUserId
    ): Subscription {
        if ($subscription->status === SubscriptionStatus::Cancelled) {
            throw new DomainException('Cancelled subscriptions cannot be paused.');
        }

        $from = CarbonImmutable::parse($pauseFrom)->startOfDay();
        $until = CarbonImmutable::parse($pauseUntil)->startOfDay();
        $today = CarbonImmutable::today();

        if ($from->isAfter($until)) {
            throw new DomainException('pause_from must be before or equal to pause_until.');
        }

        if ($from->isBefore($today)) {
            throw new DomainException('pause_from must be today or in the future.');
        }

        $maxPauseWeeks = 12;
        if ($from->diffInWeeks($until) > $maxPauseWeeks) {
            throw new DomainException("Pause duration cannot exceed {$maxPauseWeeks} weeks.");
        }

        return DB::transaction(function () use ($subscription, $from, $until, $actorUserId): Subscription {
            $today = CarbonImmutable::today();
            $status = $today->betweenIncluded($from, $until)
                ? SubscriptionStatus::Paused
                : SubscriptionStatus::Active;

            $previousStatus = $subscription->status;
            $subscription->update([
                'status' => $status,
                'pause_from' => $from->toDateString(),
                'pause_until' => $until->toDateString(),
            ]);

            Log::info('subscription.pause_scheduled', [
                'subscription_id' => $subscription->id,
                'subscription_user_id' => $subscription->user_id,
                'actor_user_id' => $actorUserId,
                'previous_status' => $previousStatus->value,
                'new_status' => $status->value,
                'pause_from' => $from->toDateString(),
                'pause_until' => $until->toDateString(),
                'timestamp' => now()->toIso8601String(),
            ]);

            $this->skipCleanup->cleanupWindow($subscription, $from, $until);

            $subscription->skipWeeks()
                ->where('source', SubscriptionSkipWeek::SOURCE_PAUSE_WINDOW)
                ->delete();

            $cursor = $from->startOfWeek();
            $windowEnd = $until->startOfWeek();
            while ($cursor->lessThanOrEqualTo($windowEnd)) {
                SubscriptionSkipWeek::updateOrCreate(
                    [
                        'subscription_id' => $subscription->id,
                        'week_start_date' => $cursor->toDateString(),
                    ],
                    [
                        'source' => SubscriptionSkipWeek::SOURCE_PAUSE_WINDOW,
                    ]
                );
                $cursor = $cursor->addWeek();
            }

            return $subscription->fresh();
        });
    }

    public function resumeNow(Subscription $subscription, int $actorUserId): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::Paused) {
            throw new DomainException('Only paused subscriptions can be resumed.');
        }

        return DB::transaction(function () use ($subscription, $actorUserId): Subscription {
            $nextChargeDate = $this->computeNextChargeDate($subscription);
            $previousStatus = $subscription->status;

            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'pause_from' => null,
                'pause_until' => null,
                'next_charge_date' => $nextChargeDate,
            ]);

            $subscription->skipWeeks()
                ->where('source', SubscriptionSkipWeek::SOURCE_PAUSE_WINDOW)
                ->delete();

            Log::info('subscription.resumed', [
                'subscription_id' => $subscription->id,
                'subscription_user_id' => $subscription->user_id,
                'actor_user_id' => $actorUserId,
                'previous_status' => $previousStatus->value,
                'new_status' => SubscriptionStatus::Active->value,
                'next_charge_date' => $nextChargeDate,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $subscription->fresh();
        });
    }

    public function cancelNow(Subscription $subscription, ?string $reason, int $actorUserId): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Cancelled) {
            throw new DomainException('Subscription is already cancelled.');
        }

        return DB::transaction(function () use ($subscription, $reason, $actorUserId): Subscription {
            $previousStatus = $subscription->status;
            $subscription->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'pause_from' => null,
                'pause_until' => null,
            ]);

            Log::info('subscription.cancelled', [
                'subscription_id' => $subscription->id,
                'subscription_user_id' => $subscription->user_id,
                'actor_user_id' => $actorUserId,
                'previous_status' => $previousStatus->value,
                'new_status' => SubscriptionStatus::Cancelled->value,
                'cancellation_reason' => $reason,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $subscription->fresh();
        });
    }

    public function computeNextChargeDate(Subscription $subscription): string
    {
        $from = CarbonImmutable::today();

        return match ($subscription->plan) {
            SubscriptionPlan::Weekly => $from->addWeek()->toDateString(),
            SubscriptionPlan::Biweekly => $from->addWeeks(2)->toDateString(),
        };
    }
}
