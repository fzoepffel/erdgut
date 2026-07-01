<?php

namespace App\Models;

use App\Enums\ProductUnit;
use App\Enums\RestaurantOrderItemTrack;
use App\Enums\RestaurantOrderLineStatus;
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
        'name_snapshot',
        'quantity',
        'unit',
        'track',
        'line_status',
        'recurrence',
        'recurrence_until',
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
            'line_status' => RestaurantOrderLineStatus::class,
            'recurrence_until' => 'date',
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

    /** Awaiting an admin accept/decline decision (on-request / special line). */
    public function isPending(): bool
    {
        return $this->line_status === RestaurantOrderLineStatus::Requested;
    }

    public function isDeclined(): bool
    {
        return $this->line_status === RestaurantOrderLineStatus::Declined;
    }

    /** A firm order line (in-stock, or an admin-accepted request). */
    public function isConfirmedLine(): bool
    {
        return $this->line_status === RestaurantOrderLineStatus::Confirmed;
    }

    public function isWeekly(): bool
    {
        return $this->recurrence === 'weekly';
    }

    /** A free-form "special request" line — no catalog product. */
    public function isCustom(): bool
    {
        return $this->product_id === null;
    }

    public function displayName(): string
    {
        return $this->product?->name ?? $this->name_snapshot ?? '';
    }

    /**
     * Human quantity label. Catalog lines use the product's unit formatting;
     * custom lines render the entered amount + the chosen unit symbol.
     */
    public function displayQuantityLabel(?string $locale = null): string
    {
        if ($this->product !== null) {
            return $this->product->displayQuantity((float) $this->quantity, $locale);
        }

        $qty = rtrim(rtrim(number_format((float) $this->quantity, 4, '.', ''), '0'), '.');
        $unit = (string) ($this->unit ?? '');

        return trim($qty.' '.($unit !== '' ? ProductUnit::displaySymbol($unit) : ''));
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
