<?php

namespace App\Models;

use App\Enums\RestaurantOrderKind;
use App\Enums\RestaurantOrderLineStatus;
use App\Enums\RestaurantOrderStatus;
use App\Services\Shop\ShopClosureService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'delivery_fee_net_eur',
        'delivery_fee_vat_rate_percent',
        'invoice_number',
        'invoiced_at',
        'invoice_due_date',
        'invoice_paid_at',
        'invoice_overrides',
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
            'delivery_fee_net_eur' => 'decimal:2',
            'delivery_fee_vat_rate_percent' => 'integer',
            'invoiced_at' => 'datetime',
            'invoice_due_date' => 'date',
            'invoice_paid_at' => 'datetime',
            'invoice_overrides' => 'array',
            'adjusted_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return HasMany<RestaurantOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RestaurantOrderItem::class);
    }

    /**
     * Optional delivery fee, entered NET at the pricing step and billed as
     * its own line on the invoice. Net entry mirrors how line pricing works.
     */
    public function deliveryFeeNetEur(): float
    {
        return (float) ($this->delivery_fee_net_eur ?? 0.0);
    }

    public function deliveryFeeVatEur(): float
    {
        $net = $this->deliveryFeeNetEur();
        if ($net <= 0) {
            return 0.0;
        }

        return round($net * (int) ($this->delivery_fee_vat_rate_percent ?? 0) / 100, 2);
    }

    public function deliveryFeeGrossEur(): float
    {
        return round($this->deliveryFeeNetEur() + $this->deliveryFeeVatEur(), 2);
    }

    /**
     * A per-invoice display override (recipient/seller/bank block lines,
     * terms text, ...) set in the WYSIWYG invoice editor. Null when unset or
     * blank, so callers can fall back to the real default.
     */
    public function invoiceOverride(string $key): ?string
    {
        $value = trim((string) (($this->invoice_overrides ?? [])[$key] ?? ''));

        return $value !== '' ? $value : null;
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

    /**
     * The mirror ShopOrder created when this restaurant order becomes firm
     * (Confirmed / Fulfilled). The mirror is the surface the admin edits and
     * the record that feeds revenue; this RestaurantOrder is the "as-requested"
     * history. Null on Submitted / Declined / Cancelled orders and on legacy
     * firm orders that predate the mirror rollout.
     *
     * @return HasOne<ShopOrder, $this>
     */
    public function mirroredShopOrder(): HasOne
    {
        return $this->hasOne(ShopOrder::class, 'restaurant_order_id');
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

    /**
     * Owner-side changes (edit lines, skip, cancel) close when ordering for
     * the delivery day closes: once the shop's cutoff for that day has
     * passed, the goods are being sourced/packed. Invoiced or terminal
     * orders are always locked.
     */
    public function isLockedForChanges(ShopClosureService $closures): bool
    {
        if ($this->isInvoiced()) {
            return true;
        }
        if (! in_array($this->status, [RestaurantOrderStatus::Submitted, RestaurantOrderStatus::Confirmed], true)) {
            return true;
        }

        return $closures->isClosed($this->expected_delivery_date->copy());
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
