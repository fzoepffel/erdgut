<?php

namespace App\Models;

use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'address_id',
        'box_id',
        'product_type',
        'box_size',
        'preferences',
        'allergies',
        'preferred_timeslot',
        'preferred_delivery_window',
        'special_instructions',
        'amount',
        'currency',
        'status',
        'scheduled_for_date',
        'paid_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
        'is_test',
    ];

    protected function casts(): array
    {
        return [
            'product_type' => OrderProductType::class,
            'status' => OrderStatus::class,
            'preferences' => 'array',
            'allergies' => 'array',
            'amount' => 'decimal:2',
            'scheduled_for_date' => 'date',
            'paid_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'is_test' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function isTrial(): bool
    {
        return $this->product_type === OrderProductType::TrialBox;
    }

    public function markPaid(): void
    {
        $this->forceFill([
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ])->save();
    }

    public function markScheduled(\DateTimeInterface $date, string $timeslot): void
    {
        $this->forceFill([
            'status' => OrderStatus::Scheduled,
            'scheduled_for_date' => $date,
            'preferred_timeslot' => $timeslot,
        ])->save();
    }

    public function markDelivered(): void
    {
        $this->forceFill([
            'status' => OrderStatus::Delivered,
            'delivered_at' => now(),
        ])->save();
    }

    public function markCancelled(?string $reason = null): void
    {
        $this->forceFill([
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();
    }
}
