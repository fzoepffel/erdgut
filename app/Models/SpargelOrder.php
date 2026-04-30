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
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property 'green'|'white' $asparagus_type
 * @property int $quantity_grams
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
        'asparagus_type',
        'quantity_grams',
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
        'status',
        'paid_at',
        'marked_paid_by_admin_at',
        'fulfilled_at',
        'sumup_checkout_id',
        'sumup_payment_id',
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
            'quantity_grams' => 'integer',
            'unit_price_eur' => 'decimal:2',
            'delivery_fee_eur' => 'decimal:2',
            'total_amount_eur' => 'decimal:2',
            'expected_delivery_date' => 'date',
            'paid_at' => 'datetime',
            'marked_paid_by_admin_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    /**
     * Convenience accessor: humanised "500 g grüner Spargel" line for receipts.
     */
    protected function lineDescription(): Attribute
    {
        return Attribute::get(fn (): string => sprintf(
            '%d g %s',
            (int) $this->quantity_grams,
            $this->asparagus_type === 'white' ? 'weißer Spargel' : 'grüner Spargel',
        ));
    }
}
