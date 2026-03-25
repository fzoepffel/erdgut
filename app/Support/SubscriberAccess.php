<?php

namespace App\Support;

use App\Enums\SubscriptionStatus;
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

        if ($sub->status === SubscriptionStatus::Cancelled) {
            return false;
        }

        return $sub->sumup_token !== null && $sub->sumup_token !== '';
    }

    /**
     * Show the pre-subscription welcome dashboard (no active path to deliver yet).
     */
    public static function shouldShowWelcomeDashboard(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $sub = Subscription::billingSubscriptionForUser($user);

        return $sub === null || $sub->status === SubscriptionStatus::Cancelled;
    }
}
