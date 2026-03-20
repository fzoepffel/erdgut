<?php

namespace App\Support;

use App\Models\Box;

class BoxValueRecalculationService
{
    /**
     * Recalculate box estimated value from items and persist.
     * Formula: sum of (quantity × product.unit_price) for each item.
     *
     * @return float The new estimated value.
     */
    public function recalculate(Box $box): float
    {
        $box->loadMissing('items.product');
        $estimatedValue = $box->items->sum(fn ($item) => (float) $item->quantity * (float) $item->product->unit_price);
        $box->update(['estimated_value' => $estimatedValue]);

        return (float) $box->fresh()->estimated_value;
    }
}
