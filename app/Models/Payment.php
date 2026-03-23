<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

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
            'amount' => 'decimal:2',
            'provider_payload' => 'encrypted:array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * Get the subscription that owns this payment attempt.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
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
