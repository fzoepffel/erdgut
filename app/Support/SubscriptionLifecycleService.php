<?php

namespace App\Support;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionLifecycleService
{
    public function schedulePause(
        Subscription $subscription,
        string $pauseFrom,
        string $pauseUntil,
        int $actorUserId
    ): Subscription {
        if ($subscription->status === SubscriptionStatus::Cancelled) {
            throw new DomainException('Cancelled subscriptions cannot be paused.');
        }

        return DB::transaction(function () use ($subscription, $pauseFrom, $pauseUntil, $actorUserId): Subscription {
            $from = CarbonImmutable::parse($pauseFrom)->startOfDay();
            $until = CarbonImmutable::parse($pauseUntil)->startOfDay();
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

            return $subscription->fresh();
        });
    }

    public function resumeNow(Subscription $subscription, int $actorUserId): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Cancelled) {
            throw new DomainException('Cancelled subscriptions cannot be resumed.');
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
