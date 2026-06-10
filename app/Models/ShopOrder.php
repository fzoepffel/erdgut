<?php

namespace App\Models;

use App\Enums\ShopOrderStatus;
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
