<?php

namespace App\Models;

use App\Enums\CustomerType;
use App\Enums\OrderSource;
use App\Enums\ShopOrderStatus;
use App\Services\Admin\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Anonymous multi-product shop order.
 *
 * Standalone — no relationship to User / Order / Subscription. Customer
 * data is captured on the row (name + email + phone) so anonymous guests
 * can buy without registering. Cart lines live on {@see ShopOrderItem}.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property 'pickup'|'delivery' $fulfillment_mode
 * @property Carbon $expected_delivery_date
 * @property string|null $delivery_window_from
 * @property string|null $delivery_window_to
 * @property Carbon|null $delivery_window_set_at
 * @property string|null $pickup_location
 * @property string|null $delivery_street
 * @property string|null $delivery_postal_code
 * @property string|null $delivery_city
 * @property string|null $delivery_notes
 * @property numeric $items_total_eur
 * @property numeric $delivery_fee_eur
 * @property int $delivery_fee_vat_rate_percent
 * @property numeric $delivery_fee_vat_amount_eur
 * @property numeric $total_amount_eur
 * @property numeric|null $paid_amount_eur
 * @property numeric|null $outstanding_settled_amount_eur
 * @property Carbon|null $outstanding_settled_at
 * @property int|null $outstanding_settled_by_user_id
 * @property int|null $coupon_id
 * @property string|null $coupon_code
 * @property numeric $discount_eur
 * @property 'online'|'in_person' $payment_method
 * @property ShopOrderStatus $status
 * @property Carbon|null $paid_at
 * @property Carbon|null $marked_paid_by_admin_at
 * @property Carbon|null $fulfilled_at
 * @property int $payment_attempts
 * @property Carbon|null $last_payment_failed_at
 * @property string|null $sumup_checkout_id
 * @property string|null $sumup_payment_id
 * @property array<string,mixed>|null $provider_payload
 * @property bool $is_test
 * @property OrderSource $order_source
 * @property CustomerType $customer_type
 * @property int|null $restaurant_order_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ShopOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'fulfillment_mode',
        'expected_delivery_date',
        'delivery_window_from',
        'delivery_window_to',
        'delivery_window_set_at',
        'pickup_location',
        'delivery_street',
        'delivery_postal_code',
        'delivery_city',
        'delivery_notes',
        'items_total_eur',
        'delivery_fee_eur',
        'delivery_fee_vat_rate_percent',
        'delivery_fee_vat_amount_eur',
        'total_amount_eur',
        'paid_amount_eur',
        'outstanding_settled_amount_eur',
        'outstanding_settled_at',
        'outstanding_settled_by_user_id',
        'receipt_overrides',
        'invoice_number',
        'invoiced_at',
        'invoice_due_date',
        'coupon_id',
        'coupon_code',
        'discount_eur',
        'payment_method',
        'status',
        'paid_at',
        'marked_paid_by_admin_at',
        'fulfilled_at',
        'payment_attempts',
        'last_payment_failed_at',
        'refund_amount',
        'refunded_at',
        'refund_reason',
        'refunded_by_user_id',
        'refund_attempts',
        'last_refund_attempt_at',
        'last_refund_error',
        'sumup_checkout_id',
        'sumup_payment_id',
        'provider_payload',
        'is_test',
        'order_source',
        'customer_type',
        'restaurant_order_id',
        'locale',
    ];

    /**
     * SumUp tokens + raw provider payloads must never round-trip through
     * API responses or array casts. Same convention as {@see SpargelOrder}.
     */
    protected $hidden = [
        'sumup_checkout_id',
        'sumup_payment_id',
        'provider_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShopOrderStatus::class,
            'expected_delivery_date' => 'date',
            'delivery_window_set_at' => 'datetime',
            'items_total_eur' => 'decimal:2',
            'delivery_fee_eur' => 'decimal:2',
            'delivery_fee_vat_rate_percent' => 'integer',
            'delivery_fee_vat_amount_eur' => 'decimal:2',
            'total_amount_eur' => 'decimal:2',
            'paid_amount_eur' => 'decimal:2',
            'outstanding_settled_amount_eur' => 'decimal:2',
            'outstanding_settled_at' => 'datetime',
            'receipt_overrides' => 'array',
            'invoiced_at' => 'datetime',
            'invoice_due_date' => 'date',
            'discount_eur' => 'decimal:2',
            'paid_at' => 'datetime',
            'marked_paid_by_admin_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'payment_attempts' => 'integer',
            'last_payment_failed_at' => 'datetime',
            'refund_amount' => 'decimal:2',
            'refunded_at' => 'datetime',
            'refund_attempts' => 'integer',
            'last_refund_attempt_at' => 'datetime',
            'provider_payload' => 'array',
            'is_test' => 'boolean',
            'order_source' => OrderSource::class,
            'customer_type' => CustomerType::class,
        ];
    }

    /**
     * @return HasMany<ShopOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class);
    }

    /**
     * The restaurant request/order this ShopOrder mirrors, or null when the
     * order originated from the storefront or admin's one-off create form.
     * When set, this ShopOrder is the source of truth for fulfillment and
     * revenue; the RestaurantOrder is the "as-requested" history.
     *
     * @return BelongsTo<RestaurantOrder, $this>
     */
    public function sourceRestaurantOrder(): BelongsTo
    {
        return $this->belongsTo(RestaurantOrder::class, 'restaurant_order_id');
    }

    /**
     * "Finalized" orders — confirmed sales the team acts on: paid and
     * already-fulfilled orders, plus in-person ones still awaiting payment at
     * pickup. Excludes abandoned/failed/cancelled/refunded. Shared by the
     * orders list and the packing screen so both mean the same thing.
     *
     * @param  Builder<ShopOrder>  $query
     */
    public function scopeFinalized(Builder $query): void
    {
        $query->where(function ($inner): void {
            $inner->whereIn('status', [
                ShopOrderStatus::Paid->value,
                ShopOrderStatus::Fulfilled->value,
            ])->orWhere(function ($pickup): void {
                $pickup->where('status', ShopOrderStatus::PendingPayment->value)
                    ->where('payment_method', 'in_person');
            });
        });
    }

    /**
     * Whether the admin has published a concrete delivery / pickup window
     * (both ends set). Drives the tracking page's two LiveStatus states.
     */
    public function hasDeliveryWindow(): bool
    {
        return $this->delivery_window_from !== null && $this->delivery_window_to !== null;
    }

    /**
     * "14:00 – 16:00" for display, or null when no window is set. Time
     * columns round-trip as "HH:MM:SS" strings; trim to HH:MM.
     */
    public function deliveryWindowLabel(): ?string
    {
        if (! $this->hasDeliveryWindow()) {
            return null;
        }

        $from = substr((string) $this->delivery_window_from, 0, 5);
        $to = substr((string) $this->delivery_window_to, 0, 5);

        return $from.' – '.$to;
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * A *complete* order awaiting payment at pickup. The guest finished
     * checkout and chose to pay cash/card on collection — it is a confirmed
     * sale that just hasn't been settled yet. Distinct in the admin from an
     * unfinished online checkout (see {@see isAwaitingOnlinePayment}), even
     * though both sit on {@see ShopOrderStatus::PendingPayment}.
     */
    public function isAwaitingInPersonPayment(): bool
    {
        return $this->status === ShopOrderStatus::PendingPayment
            && $this->payment_method === 'in_person';
    }

    /**
     * An *unfinished* order: the guest was sent to SumUp for online payment
     * but never completed it (closed the tab, abandoned the checkout, …).
     * It is not yet a confirmed sale and may never become one — the admin
     * must be able to tell it apart from a ready in-person order at a glance.
     */
    public function isAwaitingOnlinePayment(): bool
    {
        return $this->status === ShopOrderStatus::PendingPayment
            && $this->payment_method === 'online';
    }

    /**
     * The amount actually collected from the customer, in EUR. Falls back
     * to the order total for paid orders created before paid_amount_eur
     * existed (their first admin edit snapshots it for real). Null when
     * the order was never paid.
     */
    public function effectivePaidAmountEur(): ?float
    {
        if ($this->paid_amount_eur !== null) {
            return (float) $this->paid_amount_eur;
        }

        return $this->paid_at !== null ? (float) $this->total_amount_eur : null;
    }

    /**
     * Difference between the current total and what was already collected:
     * positive = remainder to collect in person, negative = overpayment to
     * refund in person. 0.0 for never-paid orders (a fully unpaid in-person
     * order is "pending", not "open remainder").
     */
    public function outstandingAmountEur(): float
    {
        $paid = $this->effectivePaidAmountEur();
        if ($paid === null) {
            return 0.0;
        }

        return round((float) $this->total_amount_eur - $paid, 2);
    }

    /**
     * Whether an edit left a difference between paid and owed that still
     * needs settling in person (either direction).
     */
    public function hasOpenAmount(): bool
    {
        return $this->paid_at !== null && abs($this->outstandingAmountEur()) >= 0.01;
    }

    /**
     * Whether the admin may edit this order's contents. Confirmed sales
     * (in-person pending, paid, fulfilled) are editable; unfinished online
     * checkouts are not (the SumUp checkout amount would desync), and
     * terminal states (cancelled/failed/refunded) stay frozen.
     */
    public function isEditableByAdmin(): bool
    {
        if ($this->isAwaitingOnlinePayment()) {
            return false;
        }

        return in_array($this->status, [
            ShopOrderStatus::PendingPayment,
            ShopOrderStatus::Paid,
            ShopOrderStatus::Fulfilled,
        ], true);
    }

    /**
     * Whether the availability matrix still counts this order's quantities
     * as committed. Mirrors InventoryService::applyCommittingShopOrderFilter:
     * cancelled/refunded/failed orders released their stock, and pending
     * orders past the abandoned-checkout grace window are released too.
     * Used by the edit flow to decide whether the order's own quantities
     * may be re-used against availability.
     */
    /**
     * A per-receipt display override (recipient/seller block lines, texts)
     * set in the WYSIWYG receipt editor. Null when unset or blank, so
     * callers fall back to the real default.
     */
    public function receiptOverride(string $key): ?string
    {
        $value = trim((string) (($this->receipt_overrides ?? [])[$key] ?? ''));

        return $value !== '' ? $value : null;
    }

    /**
     * Whether a proper Rechnung has been issued for this order. The number
     * comes from the shared yearly invoice ledger and never changes.
     */
    public function isInvoiced(): bool
    {
        return $this->invoice_number !== null;
    }

    public function commitsStockNow(): bool
    {
        if (in_array($this->status, [
            ShopOrderStatus::Cancelled,
            ShopOrderStatus::Refunded,
            ShopOrderStatus::Failed,
        ], true)) {
            return false;
        }

        if ($this->status === ShopOrderStatus::PendingPayment) {
            // In-person orders have no online checkout to abandon: they stay
            // pending until handover and hold their stock the whole time.
            if ($this->payment_method === 'in_person') {
                return true;
            }

            return $this->created_at !== null
                && $this->created_at->gte(now()->subMinutes(InventoryService::ABANDONED_PENDING_PAYMENT_MINUTES));
        }

        return true;
    }

    /**
     * Mark this order as refunded — call AFTER SumUp confirmed the refund
     * (or for a manual external refund). Flips status to Refunded and
     * records who/when/why. Mirrors {@see SpargelOrder::recordRefund()}.
     */
    public function recordRefund(float $amount, int $actorUserId, ?string $reason = null): void
    {
        $this->forceFill([
            'status' => ShopOrderStatus::Refunded,
            'refund_amount' => $amount,
            'refunded_at' => now(),
            'refund_reason' => $reason,
            'refunded_by_user_id' => $actorUserId,
        ])->save();
    }

    /**
     * Record that a refund attempt was made but SumUp rejected it. Status
     * stays Paid so the admin isn't misled about whether money moved.
     */
    public function recordRefundAttemptFailed(string $error): void
    {
        $this->forceFill([
            'refund_attempts' => (int) $this->refund_attempts + 1,
            'last_refund_attempt_at' => now(),
            'last_refund_error' => mb_substr($error, 0, 500),
        ])->save();
    }
}
