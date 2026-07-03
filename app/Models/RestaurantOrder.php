<?php

namespace App\Models;

use App\Enums\RestaurantOrderKind;
use App\Enums\RestaurantOrderLineStatus;
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
        'kind',
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
        'invoice_paid_at',
        'locale',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RestaurantOrderStatus::class,
            'kind' => RestaurantOrderKind::class,
            'expected_delivery_date' => 'date',
            'original_delivery_date' => 'date',
            'items_total_net_eur' => 'decimal:2',
            'items_total_gross_eur' => 'decimal:2',
            'vat_total_eur' => 'decimal:2',
            'invoiced_at' => 'datetime',
            'invoice_due_date' => 'date',
            'invoice_paid_at' => 'datetime',
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

    /**
     * At least one line is still awaiting an admin accept/decline (on-request /
     * special). An order is confirmable once none remain. Uses the loaded items
     * collection when available, else a scoped exists() query.
     */
    public function hasPendingLines(): bool
    {
        if ($this->relationLoaded('items')) {
            return $this->items->contains(fn (RestaurantOrderItem $i): bool => $i->isPending());
        }

        return $this->items()->where('line_status', RestaurantOrderLineStatus::Requested->value)->exists();
    }

    /** An invoice has been issued — the order's lines/prices are frozen. */
    public function isInvoiced(): bool
    {
        return $this->invoice_number !== null;
    }

    public function isInvoicePaid(): bool
    {
        return $this->invoice_paid_at !== null;
    }

    /** Invoiced, unpaid, and past its due date. */
    public function isInvoiceOverdue(): bool
    {
        return $this->isInvoiced()
            && ! $this->isInvoicePaid()
            && $this->invoice_due_date !== null
            && $this->invoice_due_date->isPast();
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

    public function isRequest(): bool
    {
        return $this->kind === RestaurantOrderKind::Request;
    }

    /** Shop orders (in-stock, instantly confirmed). */
    public function scopeOrders(Builder $query): void
    {
        $query->where('kind', RestaurantOrderKind::Order->value);
    }

    /** Anfragen (season / over-stock / free-form, admin-confirmed). */
    public function scopeRequests(Builder $query): void
    {
        $query->where('kind', RestaurantOrderKind::Request->value);
    }

    /**
     * Restaurant-facing lists: a weekly standing order materializes EVERY
     * horizon week as a real order — showing them all drowns the list. This
     * keeps history, one-time orders, skipped/cancelled weeks, and only the
     * NEXT upcoming auto-generated week per standing order; the further
     * future weeks are summarized separately (see the orders index).
     */
    public function scopeCollapseFutureRecurring(Builder $query): void
    {
        $query->where(function (Builder $w): void {
            $w->whereNull('source_standing_order_id')
                ->orWhereDate('expected_delivery_date', '<', today()->toDateString())
                ->orWhere('status', '!=', RestaurantOrderStatus::Confirmed->value)
                ->orWhereRaw(
                    'expected_delivery_date = (select min(o2.expected_delivery_date) from restaurant_orders o2'
                    .' where o2.source_standing_order_id = restaurant_orders.source_standing_order_id'
                    .' and o2.status = ? and o2.expected_delivery_date >= ?)',
                    [RestaurantOrderStatus::Confirmed->value, today()->toDateString()],
                );
        });
    }
}
