<?php

namespace App\Models;

use App\Enums\ProductUnit;
use App\Enums\SpargelOrderStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Trial-shop order for the first spargel sales test.
 *
 * Standalone — no relationship to User, Order, Product, or Producer. Customer
 * data is captured at the row (name + email + phone) so anonymous visitors
 * can place a single purchase without registering an account.
 *
 * One order can mix green AND white asparagus — both quantities live on
 * the row. Either may be 0 but the combined total is enforced in the
 * FormRequest (>= 100 g).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property int $quantity_green_grams
 * @property int $quantity_white_grams
 * @property numeric $unit_price_eur
 * @property 'pickup'|'delivery' $fulfillment_mode
 * @property numeric $delivery_fee_eur
 * @property numeric $total_amount_eur
 * @property 'online'|'in_person' $payment_method
 * @property Carbon|null $expected_delivery_date
 * @property string|null $delivery_street
 * @property string|null $delivery_postal_code
 * @property string|null $delivery_city
 * @property string|null $delivery_notes
 * @property string|null $pickup_location
 * @property SpargelOrderStatus $status
 * @property Carbon|null $paid_at
 * @property Carbon|null $marked_paid_by_admin_at
 * @property Carbon|null $fulfilled_at
 * @property string|null $sumup_checkout_id
 * @property string|null $sumup_payment_id
 * @property array<string,mixed>|null $provider_payload
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SpargelOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'quantity_green_grams',
        'quantity_white_grams',
        'unit_price_eur',
        'fulfillment_mode',
        'delivery_fee_eur',
        'total_amount_eur',
        'payment_method',
        'expected_delivery_date',
        'delivery_street',
        'delivery_postal_code',
        'delivery_city',
        'delivery_notes',
        'pickup_location',
        'status',
        'paid_at',
        'marked_paid_by_admin_at',
        'fulfilled_at',
        'refund_amount',
        'refunded_at',
        'refund_reason',
        'refunded_by_user_id',
        'refund_attempts',
        'last_refund_attempt_at',
        'last_refund_error',
        'sumup_checkout_id',
        'sumup_payment_id',
        'payment_attempts',
        'last_payment_failed_at',
        'provider_payload',
    ];

    /**
     * SumUp tokens + raw provider payloads must never round-trip through
     * API responses or array casts. Same convention as {@see Order}.
     */
    protected $hidden = [
        'sumup_checkout_id',
        'sumup_payment_id',
        'provider_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => SpargelOrderStatus::class,
            'quantity_green_grams' => 'integer',
            'quantity_white_grams' => 'integer',
            'unit_price_eur' => 'decimal:2',
            'delivery_fee_eur' => 'decimal:2',
            'total_amount_eur' => 'decimal:2',
            'expected_delivery_date' => 'date',
            'paid_at' => 'datetime',
            'marked_paid_by_admin_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'refund_amount' => 'decimal:2',
            'refunded_at' => 'datetime',
            'refund_attempts' => 'integer',
            'last_refund_attempt_at' => 'datetime',
            'payment_attempts' => 'integer',
            'last_payment_failed_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    /**
     * Mark this spargel order as refunded — call AFTER SumUp confirmed the
     * refund (or for a manual external refund). Flips status to Refunded
     * and records who/when/why.
     */
    public function recordRefund(float $amount, int $actorUserId, ?string $reason = null): void
    {
        $this->forceFill([
            'status' => SpargelOrderStatus::Refunded,
            'refund_amount' => $amount,
            'refunded_at' => now(),
            'refund_reason' => $reason,
            'refunded_by_user_id' => $actorUserId,
        ])->save();
    }

    /**
     * Record that a refund attempt was made but SumUp rejected it. Status
     * stays Paid — we don't want to lie to the admin about whether the
     * customer's money has actually moved. The admin can retry, and the
     * refund_attempts counter + last_refund_error surface in the UI.
     */
    public function recordRefundAttemptFailed(string $error): void
    {
        $this->forceFill([
            'refund_attempts' => (int) $this->refund_attempts + 1,
            'last_refund_attempt_at' => now(),
            'last_refund_error' => mb_substr($error, 0, 500),
        ])->save();
    }

    /**
     * Combined total grams across both varieties.
     */
    protected function totalGrams(): Attribute
    {
        return Attribute::get(
            fn (): int => (int) $this->quantity_green_grams + (int) $this->quantity_white_grams
        );
    }

    /**
     * Humanised line description for receipts and admin lists. Renders
     * "500 g grüner Spargel + 200 g weißer Spargel" when both varieties
     * are present, or just the present one. Always returns at least one
     * line — defensive against a row where both columns are 0.
     */
    protected function lineDescription(): Attribute
    {
        return Attribute::get(function (): string {
            $parts = [];
            if ($this->quantity_green_grams > 0) {
                $parts[] = ProductUnit::Mass->formatQuantity((int) $this->quantity_green_grams).' grüner Spargel';
            }
            if ($this->quantity_white_grams > 0) {
                $parts[] = ProductUnit::Mass->formatQuantity((int) $this->quantity_white_grams).' weißer Spargel';
            }

            return $parts === [] ? '0 g' : implode(' + ', $parts);
        });
    }
}
