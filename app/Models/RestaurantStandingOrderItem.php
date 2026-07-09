<?php

namespace App\Models;

use App\Enums\ProductUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A recurring line of a standing order. Active for a delivery date D when
 * starts_on <= D and (ends_on is null or D <= ends_on).
 */
class RestaurantStandingOrderItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'restaurant_standing_order_id',
        'product_id',
        'variant_id',
        'variant_name_snapshot',
        'name_snapshot',
        'unit',
        'quantity',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** @return BelongsTo<RestaurantStandingOrder, $this> */
    public function standingOrder(): BelongsTo
    {
        return $this->belongsTo(RestaurantStandingOrder::class, 'restaurant_standing_order_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isCustom(): bool
    {
        return $this->product_id === null;
    }

    public function activeOn(Carbon $date): bool
    {
        return $this->starts_on->lte($date) && ($this->ends_on === null || $this->ends_on->gte($date));
    }

    /** Rebuild the submit() payload line shape for this template line. */
    public function toPayloadItem(): array
    {
        if ($this->isCustom()) {
            return [
                'name' => $this->name_snapshot,
                'unit' => $this->unit,
                'quantity' => (float) $this->quantity,
            ];
        }

        return [
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'quantity' => (float) $this->quantity,
        ];
    }

    public function displayName(): string
    {
        return $this->product?->name ?? $this->name_snapshot ?? '';
    }

    public function displayQuantityLabel(?string $locale = null): string
    {
        if ($this->product !== null) {
            return $this->product->displayQuantity((float) $this->quantity, $locale);
        }

        $qty = rtrim(rtrim(number_format((float) $this->quantity, 4, '.', ''), '0'), '.');
        $unit = (string) ($this->unit ?? '');

        return trim($qty.' '.($unit !== '' ? ProductUnit::displaySymbol($unit) : ''));
    }
}
