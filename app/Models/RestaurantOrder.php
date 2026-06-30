<?php

namespace App\Models;

use App\Enums\RestaurantOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A restaurant (B2B) order/request. Tied to a verified restaurant user; no
 * payment. In-stock lines are hard-reserved on submit (see RestaurantOrderItem
 * track + InventoryService); an admin confirms + invoices later.
 */
class RestaurantOrder extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'source_standing_order_id',
        'status',
        'expected_delivery_date',
        'original_delivery_date',
        'delivery_street',
        'delivery_postal_code',
        'delivery_city',
        'delivery_notes',
        'items_total_net_eur',
        'items_total_gross_eur',
        'vat_total_eur',
        'invoice_number',
        'invoiced_at',
        'invoice_due_date',
        'locale',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RestaurantOrderStatus::class,
            'expected_delivery_date' => 'date',
            'original_delivery_date' => 'date',
            'items_total_net_eur' => 'decimal:2',
            'items_total_gross_eur' => 'decimal:2',
            'vat_total_eur' => 'decimal:2',
            'invoiced_at' => 'datetime',
            'invoice_due_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return HasMany<RestaurantOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RestaurantOrderItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<RestaurantStandingOrder, $this> */
    public function standingOrder(): BelongsTo
    {
        return $this->belongsTo(RestaurantStandingOrder::class, 'source_standing_order_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === RestaurantOrderStatus::Submitted;
    }

    /** An invoice has been issued — the order's lines/prices are frozen. */
    public function isInvoiced(): bool
    {
        return $this->invoice_number !== null;
    }

    /** Whether an admin may still price/edit + issue (confirmed/fulfilled, not yet invoiced). */
    public function isInvoiceable(): bool
    {
        return ! $this->isInvoiced()
            && in_array($this->status, [RestaurantOrderStatus::Confirmed, RestaurantOrderStatus::Fulfilled], true);
    }

    /** Orders whose reserved lines still hold stock. */
    public function scopeReserving(Builder $query): void
    {
        $query->whereIn('status', RestaurantOrderStatus::reservingValues());
    }
}
