<?php

namespace App\Services\Billing;

use App\Models\Subscription;

/**
 * Backwards-compatibility facade over {@see BoxPriceCalculator}.
 *
 * Pricing logic now lives in {@see BoxPriceCalculator} (single source of
 * truth, database-free, `(size, eggsAddOn)` in / EUR out). This class is
 * kept as a thin wrapper so existing call sites keep working while item 3
 * of W2-16 swaps each surface to call the calculator directly.
 *
 * The trial-flat-first-charge override has been removed (W2-16 item 2):
 * every subscription charge is `size-base + eggs-addon`, period.
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
     * @deprecated W2-16: the trial-flat-first-charge override is dead. The
     *     `box.trial_price_ttc` config still exists but is no longer read
     *     by subscription pricing paths. Standalone trial orders use
     *     `trialOrderTotal()` (size-based). Kept callable for back-compat
     *     in case external scripts referenced it.
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
        return app(BoxPriceCalculator::class)->trialOneOff($boxSize, $eggsAddOn);
    }

    /**
     * Expected recurring charge amount (TTC) for a subscription.
     *
     * Always size-based + optional eggs/dairy add-on, regardless of whether
     * the subscription has had its first recurring charge yet (W2-16 item 2
     * removed the trial-flat override that used to apply here).
     */
    public static function subscriptionRecurringTotal(Subscription $subscription): float
    {
        return app(BoxPriceCalculator::class)->subscriptionPerDelivery(
            (string) $subscription->box_size,
            $subscription->hasEggsDairyAddOn(),
        );
    }

    /**
     * Included value used for box budget / remainder calculations. Matches
     * the recurring charge amount (size-based plus add-ons).
     */
    public static function subscriptionIncludedValueForBudget(Subscription $subscription): float
    {
        return self::subscriptionRecurringTotal($subscription);
    }
}
