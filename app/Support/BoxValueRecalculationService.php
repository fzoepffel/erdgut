<?php

namespace App\Support;

use App\Models\Box;

class BoxValueRecalculationService
{
    /**
     * Recalculate box estimated value from items and persist.
     *
     * Storage of `Product.unit_price` is per *canonical* unit (€/g for
     * mass, €/ml for volume, €/piece, €/bunch). `BoxItem.quantity` is
     * a snapshot in whatever display unit the admin / box-setup
     * recorded — typically the product's `price_display_unit` (kg, L,
     * piece, bunch). Multiplying naively would understate mass/volume
     * lines by 1000×, so we scale the price by the product's display
     * factor first.
     *
     * @return float The new estimated value.
     */
    public function recalculate(Box $box): float
    {
        $box->loadMissing('items.product');
        $estimatedValue = $box->items->sum(function ($item): float {
            $product = $item->product;
            if ($product === null) {
                return 0.0;
            }

            $factor = 1;
            if ($product->unit !== null) {
                $displayUnit = $product->price_display_unit ?? $product->unit->defaultDisplayUnit();
                $allowed = $product->unit->allowedDisplayUnits();
                if (! in_array($displayUnit, $allowed, true)) {
                    $displayUnit = $product->unit->defaultDisplayUnit();
                }
                $factor = $product->unit->canonicalUnitsPerDisplay($displayUnit);
            }

            return (float) $item->quantity * (float) $product->unit_price * $factor;
        });

        $box->update(['estimated_value' => $estimatedValue]);

        return (float) $box->fresh()->estimated_value;
    }
}
