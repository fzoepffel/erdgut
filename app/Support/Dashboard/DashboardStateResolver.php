<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class DashboardStateResolver
{
    /**
     * Build the snapshot for a given user. Side-effect-free; safe to call
     * from the controller as the only data-loading call.
     */
    public function resolve(User $user): DashboardState
    {
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->first();

        // Latest open trial order (PendingPayment is intentionally included
        // here so we can detect "trial setup started, not finished").
        $latestTrialOrder = Order::query()
            ->where('user_id', $user->id)
            ->where('product_type', OrderProductType::TrialBox->value)
            ->orderByDesc('updated_at')
            ->first();

        $trialBox = $latestTrialOrder !== null
            ? Box::query()
                ->where('user_id', $user->id)
                ->where('order_id', $latestTrialOrder->id)
                ->with('items.product')
                ->latest()
                ->first()
            : null;

        // Subscription-only boxes: filter on subscription_id so trial boxes
        // (which carry order_id, not subscription_id) never leak in.
        [$currentSubscriptionBox, $upcomingSubscriptionBoxes] = $this->resolveSubscriptionBoxes($user, $subscription);

        $deliveryCards = $this->resolveDeliveryCards($user, $subscription);

        $kind = $this->classify($subscription, $latestTrialOrder);

        return new DashboardState(
            kind: $kind,
            subscription: $subscription,
            latestTrialOrder: $latestTrialOrder,
            trialBox: $trialBox,
            currentSubscriptionBox: $currentSubscriptionBox,
            upcomingSubscriptionBoxes: $upcomingSubscriptionBoxes,
            deliveryCards: $deliveryCards,
        );
    }

    private function classify(?Subscription $subscription, ?Order $trialOrder): DashboardKind
    {
        $hasRealTrial = $trialOrder !== null && in_array($trialOrder->status, [
            OrderStatus::Paid,
            OrderStatus::Scheduled,
            OrderStatus::Delivered,
        ], true);

        $trialIsPending = $trialOrder !== null && $trialOrder->status === OrderStatus::PendingPayment;
        $trialDelivered = $trialOrder !== null && $trialOrder->status === OrderStatus::Delivered;

        // Subscription takes priority over trial when both exist (the user is
        // moving forward; trial info still surfaces via showsTrialBlock()).
        if ($subscription !== null) {
            return match ($subscription->status) {
                SubscriptionStatus::Active => $this->classifyActiveSubscription($subscription),
                SubscriptionStatus::Paused => DashboardKind::SubscriptionPaused,
                SubscriptionStatus::PendingPayment => $hasRealTrial
                    ? DashboardKind::SubscriptionPendingWithTrial
                    : DashboardKind::SubscriptionPendingNoTrial,
                SubscriptionStatus::Cancelled => $hasRealTrial
                    ? ($trialDelivered ? DashboardKind::TrialDelivered : DashboardKind::TrialOnTheWay)
                    : DashboardKind::SubscriptionCancelled,
            };
        }

        // No subscription at all — purely trial states or new visitor.
        if ($trialIsPending) {
            return DashboardKind::TrialPending;
        }

        if ($hasRealTrial) {
            return $trialDelivered ? DashboardKind::TrialDelivered : DashboardKind::TrialOnTheWay;
        }

        return DashboardKind::NewVisitor;
    }

    private function classifyActiveSubscription(Subscription $subscription): DashboardKind
    {
        // Active without a token = legacy data; treat as PendingPayment so
        // we don't claim "subscription is active" in the UI when billing
        // isn't actually wired up. New code creates with PendingPayment
        // explicitly; this branch handles backfill edge cases.
        if (empty($subscription->sumup_token)) {
            return DashboardKind::SubscriptionPendingNoTrial;
        }

        $hasComposedBox = Box::query()
            ->where('user_id', $subscription->user_id)
            ->whereNotNull('subscription_id')
            ->exists();

        return $hasComposedBox
            ? DashboardKind::SubscriptionActive
            : DashboardKind::SubscriptionFirstWeek;
    }

    /**
     * @return array{0: ?Box, 1: Collection<int, Box>}
     */
    private function resolveSubscriptionBoxes(User $user, ?Subscription $subscription): array
    {
        if ($subscription === null
            || $subscription->status === SubscriptionStatus::PendingPayment
            || $subscription->status === SubscriptionStatus::Cancelled
        ) {
            return [null, collect()];
        }

        $today = now()->toDateString();

        $current = Box::query()
            ->where('user_id', $user->id)
            ->whereNotNull('subscription_id')
            ->whereDate('week_start_date', '<=', $today)
            ->whereDate('week_start_date', '>=', now()->subDays(6)->toDateString())
            ->with('items.product')
            ->orderByDesc('week_start_date')
            ->first();

        $upcoming = Box::query()
            ->where('user_id', $user->id)
            ->whereNotNull('subscription_id')
            ->where('week_start_date', '>=', $today)
            ->orderBy('week_start_date')
            ->limit(3)
            ->get();

        return [$current, $upcoming];
    }

    /**
     * @return Collection<int, array{delivery: Delivery, box: ?Box, deadline: ?Carbon, canModifyBox: bool}>
     */
    private function resolveDeliveryCards(User $user, ?Subscription $subscription): Collection
    {
        // Trial-only or pending-payment users don't have subscription deliveries.
        // Trial deliveries go through Order, not Delivery (trial box is a one-shot).
        if ($subscription === null
            || $subscription->status === SubscriptionStatus::PendingPayment
            || $subscription->status === SubscriptionStatus::Cancelled
        ) {
            return collect();
        }

        $today = now()->toDateString();

        $upcomingDeliveries = Delivery::query()
            ->where('user_id', $user->id)
            ->where('is_weekly_override', false)
            ->where('scheduled_for_date', '>=', $today)
            ->with([
                'address',
                'overrideDeliveries' => fn ($query) => $query->latest(),
                'overrideDeliveries.address',
            ])
            ->orderBy('scheduled_for_date')
            ->limit(12)
            ->get();

        return $upcomingDeliveries->map(function (Delivery $delivery) use ($user): ?array {
            $effectiveDelivery = $delivery->overrideDeliveries->first() ?? $delivery;

            // Hide standalone skipped base rows (preserved from the legacy
            // controller logic — overrides marked skipped still show).
            if ($effectiveDelivery->status === DeliveryStatus::Skipped && $delivery->overrideDeliveries->isEmpty()) {
                return null;
            }

            $weekStart = $effectiveDelivery->scheduled_for_date->copy()->startOfWeek()->toDateString();
            $box = Box::query()
                ->where('user_id', $user->id)
                ->whereDate('week_start_date', $weekStart)
                ->first();
            $deadline = $box?->modification_deadline_at;
            $canModify = $box?->status === BoxStatus::Editable && $deadline?->isFuture();

            return [
                'delivery' => $effectiveDelivery,
                'box' => $box,
                'deadline' => $deadline,
                'canModifyBox' => $canModify,
            ];
        })->filter()->values()->take(4);
    }
}
