<?php

namespace App\Services\Billing;

use App\Models\Subscription;

/**
 * Single source of truth for the prices customers see.
 *
 * Surfaces pricing facts that don't depend on a database row — `(size, eggsAddOn)`
 * is enough — so any view, controller or API endpoint can render a price
 * without first looking up a {@see Subscription}. Subscription-
 * driven flows still go through {@see BoxPricing} for now (which delegates
 * to this service after W2-16 item 2 lands).
 *
 * Tracks ticket **W2-16** in `docs/walkthrough-2-design-alignment.md`. The
 * trial-flat-first-charge override has been removed: every subscription
 * charge is `size-base + eggs-addon`, period.
 */
final class BoxPriceCalculator
{
    /**
     * Total TTC charged per delivery for a subscription with the given size
     * and eggs add-on flag.
     *
     * Example: `subscriptionPerDelivery('small', true)` → 24.50
     */
    public function subscriptionPerDelivery(string $size, bool $eggsAddOn = false): float
    {
        return $this->totalForSize($size, $eggsAddOn);
    }

    /**
     * Itemised breakdown of a subscription's per-delivery price. Useful for
     * payment summary screens and emails — the caller decides how to render
     * each line. Each entry carries:
     *   - `key`: stable identifier (`box_size` | `eggs_addon`)
     *   - `size`: only on `box_size` rows, echoes the requested size
     *   - `amount`: TTC for that line
     *
     * @return list<array{key: string, size?: string, amount: float}>
     */
    public function subscriptionLineItems(string $size, bool $eggsAddOn = false): array
    {
        $items = [[
            'key' => 'box_size',
            'size' => $size,
            'amount' => BoxPricing::sizeBaseTtc($size),
        ]];

        if ($eggsAddOn) {
            $items[] = [
                'key' => 'eggs_addon',
                'amount' => BoxPricing::eggsDairyAddOnTtc(),
            ];
        }

        return $items;
    }

    /**
     * Total TTC charged for a one-off trial-box order. Trial customers pick
     * a size up front, so `$size` is normally provided; for early steps in
     * the trial wizard that don't yet know the size, the medium size is the
     * canonical default to render against.
     */
    public function trialOneOff(?string $size = null, bool $eggsAddOn = false): float
    {
        return $this->totalForSize($size ?? 'medium', $eggsAddOn);
    }

    private function totalForSize(string $size, bool $eggsAddOn): float
    {
        $total = BoxPricing::sizeBaseTtc($size);

        if ($eggsAddOn) {
            $total += BoxPricing::eggsDairyAddOnTtc();
        }

        return round($total, 2);
    }
}
