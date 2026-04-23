<?php

namespace App\Services\Billing;

use App\Models\Subscription;

/**
 * Single source of truth for box pricing across trial checkout, recurring
 * charges, and admin preview screens.
 *
 * All amounts are TTC (tax-included, EUR). Methods return rounded floats
 * to 2 decimal places.
 */
class BoxPricing
{
    /**
     * Base included value (TTC) for a given box size from config.
     * Falls back to the medium size value if the size is unknown.
     */
    public static function sizeBaseTtc(string $boxSize): float
    {
        $raw = config('box.included_value_by_size.'.$boxSize);

        if (! is_numeric($raw)) {
            $raw = config('box.included_value_by_size.medium', 35);
        }

        return round((float) $raw, 2);
    }

    /**
     * Flat one-off trial-marketing price used for the first recurring charge
     * of a new subscription while trial_completed_at is null.
     */
    public static function subscriptionTrialFlatTtc(): float
    {
        return round((float) config('box.trial_price_ttc', 35), 2);
    }

    /**
     * Eggs/dairy add-on (TTC) when the preference is enabled.
     */
    public static function eggsDairyAddOnTtc(): float
    {
        $raw = config('box.eggs_dairy_add_on_eur');

        return is_numeric($raw) ? round((float) $raw, 2) : 0.0;
    }

    /**
     * Total (TTC) charged for a standalone trial order: size-based price plus
     * optional eggs/dairy add-on. Replaces the legacy flat
     * `box.trial_price_oneoff_ttc` so size + add-ons are reflected correctly.
     */
    public static function trialOrderTotal(string $boxSize, bool $eggsAddOn = false): float
    {
        $total = self::sizeBaseTtc($boxSize);

        if ($eggsAddOn) {
            $total += self::eggsDairyAddOnTtc();
        }

        return round($total, 2);
    }

    /**
     * Expected recurring charge amount (TTC) for a subscription.
     *
     * While the trial window is pending (first recurring payment not yet
     * settled), returns the flat marketing price. Otherwise returns the
     * size-based price plus optional eggs/dairy add-on.
     */
    public static function subscriptionRecurringTotal(Subscription $subscription): float
    {
        $base = $subscription->isTrialBillingPending()
            ? self::subscriptionTrialFlatTtc()
            : self::sizeBaseTtc((string) $subscription->box_size);

        $addOn = $subscription->hasEggsDairyAddOn() ? self::eggsDairyAddOnTtc() : 0.0;

        return round($base + $addOn, 2);
    }

    /**
     * Included value used for box budget / remainder calculations. Matches
     * the recurring charge amount (trial flat or size-based plus add-ons).
     */
    public static function subscriptionIncludedValueForBudget(Subscription $subscription): float
    {
        return self::subscriptionRecurringTotal($subscription);
    }
}
