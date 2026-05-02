<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;

/**
 * Central rules for “welcome vs subscriber” navigation and gated app areas.
 */
final class SubscriberAccess
{
    /**
     * Active or paused subscription with a saved SumUp token (full customer experience).
     */
    public static function hasFullSubscriberExperience(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $sub = Subscription::billingSubscriptionForUser($user);

        if ($sub === null) {
            return false;
        }

        if ($sub->status === SubscriptionStatus::Cancelled || $sub->status === SubscriptionStatus::PendingPayment) {
            return false;
        }

        return $sub->sumup_token !== null && $sub->sumup_token !== '';
    }

    /**
     * Show the pre-subscription welcome dashboard (no active path to deliver yet).
     *
     * PendingPayment subs are intentionally treated like "no sub" here — the
     * customer reached the wizard's payment step but never stored a card.
     * They need to either complete onboarding or restart, NOT see a regular
     * subscriber dashboard pretending the subscription is real.
     */
    public static function shouldShowWelcomeDashboard(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (self::hasTrialOrderOnly($user)) {
            return false;
        }

        $sub = Subscription::billingSubscriptionForUser($user);

        return $sub === null
            || $sub->status === SubscriptionStatus::Cancelled
            || $sub->status === SubscriptionStatus::PendingPayment;
    }

    /**
     * User has at least one paid/scheduled/delivered trial order but no non-cancelled subscription.
     *
     * Note: PendingPayment orders are intentionally excluded — those represent an
     * in-progress onboarding attempt (possibly abandoned), not a real trial experience
     * the user has committed to. Until payment confirms, we treat the user as still
     * onboarding and show the welcome dashboard.
     */
    public static function hasTrialOrderOnly(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $hasOpenOrder = Order::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereIn('status', [
                OrderStatus::Paid->value,
                OrderStatus::Scheduled->value,
                OrderStatus::Delivered->value,
            ])
            ->exists();

        if (! $hasOpenOrder) {
            return false;
        }

        $sub = Subscription::billingSubscriptionForUser($user);

        return $sub === null
            || $sub->status === SubscriptionStatus::Cancelled
            || $sub->status === SubscriptionStatus::PendingPayment;
    }

    /**
     * User has any kind of purchase history (subscription or paid/scheduled/delivered order).
     *
     * PendingPayment subs are NOT counted — they represent an abandoned
     * onboarding attempt, not a real purchase relationship.
     */
    public static function hasAnyPurchaseHistory(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $sub = Subscription::billingSubscriptionForUser($user);

        if ($sub !== null
            && $sub->status !== SubscriptionStatus::Cancelled
            && $sub->status !== SubscriptionStatus::PendingPayment
        ) {
            return true;
        }

        return Order::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereIn('status', [
                OrderStatus::Paid->value,
                OrderStatus::Scheduled->value,
                OrderStatus::Delivered->value,
            ])
            ->exists();
    }
}
