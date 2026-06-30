<?php

namespace App\Models;

use App\Enums\RestaurantOrderItemTrack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a restaurant (B2B) order. Price + unit are snapshotted at submit
 * (gross per canonical unit). `track` decides whether the line reserves stock.
 */
class RestaurantOrderItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'restaurant_order_id',
        'product_id',
        'variant_id',
        'variant_name_snapshot',
        'quantity',
        'unit',
        'track',
        'unit_price_snapshot_eur',
        'vat_rate_snapshot_percent',
        'line_total_eur',
        'vat_amount_eur',
        'price_rule_id',
    ];

    protected function casts(): array
    {
        return [
            'track' => RestaurantOrderItemTrack::class,
            'quantity' => 'decimal:4',
            'unit_price_snapshot_eur' => 'decimal:4',
            'vat_rate_snapshot_percent' => 'integer',
            'line_total_eur' => 'decimal:2',
            'vat_amount_eur' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<RestaurantOrder, $this> */
    public function restaurantOrder(): BelongsTo
    {
        return $this->belongsTo(RestaurantOrder::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function isReserved(): bool
    {
        return $this->track === RestaurantOrderItemTrack::Reserved;
    }

    public function displayName(): string
    {
        return $this->product?->name ?? '';
    }

    /** Human quantity label using the product's unit formatting. */
    public function displayQuantityLabel(?string $locale = null): string
    {
        return $this->product?->displayQuantity((float) $this->quantity, $locale)
            ?? rtrim(rtrim(number_format((float) $this->quantity, 4, '.', ''), '0'), '.');
    }

    /** Net (excl. VAT) line total derived from the gross snapshot. */
    public function lineNetEur(): float
    {
        return round((float) $this->line_total_eur - (float) $this->vat_amount_eur, 2);
    }

    public function scopeReserved(Builder $query): void
    {
        $query->where('track', RestaurantOrderItemTrack::Reserved->value);
    }
}
