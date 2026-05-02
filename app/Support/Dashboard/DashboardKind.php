<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

/**
 * Discrete states that drive what the customer dashboard shows.
 *
 * The dashboard previously rendered the same five-panel grid (deliveries,
 * current box, almanach, etc.) regardless of the customer's situation,
 * which made trial-only customers see a "your weekly subscription box"
 * panel that pulled the trial box, and made not-yet-paid customers see
 * an "active subscription" badge. Each kind below maps to a deliberate
 * UI variant.
 */
enum DashboardKind: string
{
    /** No purchase, no subscription attempt. */
    case NewVisitor = 'new_visitor';

    /** Trial Order in PendingPayment — wizard reached payment, never finished. */
    case TrialPending = 'trial_pending';

    /** Trial Order paid or scheduled, not yet delivered. The trial box may exist. */
    case TrialOnTheWay = 'trial_on_the_way';

    /** Trial Order delivered (no subscription yet). */
    case TrialDelivered = 'trial_delivered';

    /** Subscription in PendingPayment, no real trial purchase. */
    case SubscriptionPendingNoTrial = 'subscription_pending_no_trial';

    /** Subscription in PendingPayment AND a paid trial exists. */
    case SubscriptionPendingWithTrial = 'subscription_pending_with_trial';

    /** Subscription Active with token, but no Box rows composed yet. */
    case SubscriptionFirstWeek = 'subscription_first_week';

    /** Subscription Active with token and at least one Box row composed. */
    case SubscriptionActive = 'subscription_active';

    /** Subscription Paused (regardless of pause window). */
    case SubscriptionPaused = 'subscription_paused';

    /** Subscription Cancelled (and no other active activity). */
    case SubscriptionCancelled = 'subscription_cancelled';

    /**
     * Is this state one where it makes sense to render subscription-box
     * panels (Current week, Almanach upcoming weeks)?
     */
    public function showsSubscriptionBoxes(): bool
    {
        return in_array($this, [
            self::SubscriptionFirstWeek,
            self::SubscriptionActive,
            self::SubscriptionPaused,
        ], true);
    }

    /**
     * Is this state one where the trial box / trial order belongs in the UI?
     */
    public function showsTrialBlock(): bool
    {
        return in_array($this, [
            self::TrialOnTheWay,
            self::TrialDelivered,
            self::SubscriptionPendingWithTrial,
        ], true);
    }

    /**
     * Should we prompt the customer to resume a half-finished subscription setup?
     */
    public function showsResumeSubscriptionSetup(): bool
    {
        return in_array($this, [
            self::SubscriptionPendingNoTrial,
            self::SubscriptionPendingWithTrial,
        ], true);
    }

    /**
     * Should we prompt the customer to resume a half-finished trial setup?
     */
    public function showsResumeTrialSetup(): bool
    {
        return $this === self::TrialPending;
    }
}
