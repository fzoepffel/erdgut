<?php

namespace App\Models;

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
            'payment_attempts' => 'integer',
            'last_payment_failed_at' => 'datetime',
            'provider_payload' => 'array',
        ];
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
                $parts[] = sprintf('%d g grüner Spargel', $this->quantity_green_grams);
            }
            if ($this->quantity_white_grams > 0) {
                $parts[] = sprintf('%d g weißer Spargel', $this->quantity_white_grams);
            }

            return $parts === [] ? '0 g' : implode(' + ', $parts);
        });
    }
}
