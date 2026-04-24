<?php

namespace App\Support;

use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Mail\SubscriptionCancellationMail;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionSkipWeek;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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

            AuditLogger::log(
                entityType: 'subscription',
                entityId: $subscription->id,
                action: 'paused',
                fromState: $previousStatus->value,
                toState: $status->value,
                metadata: [
                    'subscription_user_id' => $subscription->user_id,
                    'pause_from' => $from->toDateString(),
                    'pause_until' => $until->toDateString(),
                ],
                actorUserId: $actorUserId,
            );

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

            AuditLogger::log(
                entityType: 'subscription',
                entityId: $subscription->id,
                action: 'resumed',
                fromState: $previousStatus->value,
                toState: SubscriptionStatus::Active->value,
                metadata: [
                    'subscription_user_id' => $subscription->user_id,
                    'next_charge_date' => $nextChargeDate,
                ],
                actorUserId: $actorUserId,
            );

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

            AuditLogger::log(
                entityType: 'subscription',
                entityId: $subscription->id,
                action: 'cancelled',
                fromState: $previousStatus->value,
                toState: SubscriptionStatus::Cancelled->value,
                reason: $reason,
                metadata: [
                    'subscription_user_id' => $subscription->user_id,
                ],
                actorUserId: $actorUserId,
            );

            $fresh = $subscription->fresh();
            $recipient = $fresh?->user?->email;
            if ($fresh !== null && $recipient !== null && $recipient !== '') {
                Mail::to($recipient)->queue(new SubscriptionCancellationMail($fresh));
            }

            return $fresh;
        });
    }

    public function computeNextChargeDate(Subscription $subscription): string
    {
        $today = CarbonImmutable::today();

        $trialWeekStart = $this->openTrialDeliveryWeekStart($subscription, $today);
        if ($trialWeekStart !== null) {
            return $trialWeekStart->addWeek()->toDateString();
        }

        return match ($subscription->plan) {
            SubscriptionPlan::Weekly => $today->addWeek()->toDateString(),
            SubscriptionPlan::Biweekly => $today->addWeeks(2)->toDateString(),
        };
    }

    private function openTrialDeliveryWeekStart(Subscription $subscription, CarbonImmutable $today): ?CarbonImmutable
    {
        $trial = Order::query()
            ->where('user_id', $subscription->user_id)
            ->where('product_type', OrderProductType::TrialBox)
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Scheduled])
            ->with('box:id,week_start_date')
            ->latest()
            ->first();

        if ($trial === null) {
            return null;
        }

        $source = $trial->box?->week_start_date
            ?? $trial->scheduled_for_date
            ?? null;

        if ($source === null) {
            return null;
        }

        $weekStart = CarbonImmutable::parse($source)->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();

        return $weekStart->greaterThanOrEqualTo($today->startOfWeek(CarbonImmutable::MONDAY))
            ? $weekStart
            : null;
    }
}
