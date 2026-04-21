<?php

namespace App\Models;

use App\Enums\PaymentChargeInitiator;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Payment $payment): void {
            $hasSubscription = ! empty($payment->subscription_id);
            $hasOrder = ! empty($payment->order_id);

            if ($hasSubscription === $hasOrder) {
                throw new \DomainException(
                    'Payment must belong to exactly one of subscription_id or order_id.'
                );
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'sumup_checkout_id',
        'sumup_payment_id',
        'provider_payload',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'subscription_id',
        'order_id',
        'box_id',
        'fulfillment_week_start',
        'charge_initiator',
        'initiated_by_user_id',
        'amount',
        'currency',
        'status',
        'attempt_number',
        'sumup_checkout_id',
        'sumup_payment_id',
        'failure_reason',
        'provider_payload',
        'paid_at',
        'failed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'charge_initiator' => PaymentChargeInitiator::class,
            'fulfillment_week_start' => 'date',
            'amount' => 'decimal:2',
            'provider_payload' => 'encrypted:array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * Get the subscription that owns this payment attempt (null for one-off trial orders).
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the one-off order this payment attempt belongs to (null for subscription charges).
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public static function hasPaidForFulfillmentWeek(int $subscriptionId, Carbon $weekStart): bool
    {
        return static::query()
            ->where('subscription_id', $subscriptionId)
            ->where('status', self::STATUS_PAID)
            ->whereDate('fulfillment_week_start', $weekStart->toDateString())
            ->exists();
    }

    /**
     * Mark this payment attempt as paid.
     */
    public function markPaid(): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'failed_at' => null,
            'failure_reason' => null,
        ]);
    }

    /**
     * Mark this payment attempt as failed.
     */
    public function markFailed(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'failure_reason' => $reason,
            'paid_at' => null,
        ]);
    }
}
