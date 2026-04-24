<?php

namespace App\Models;

use App\Enums\BoxStatus;
use Database\Factories\BoxFactory;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Box extends Model
{
    /** @use HasFactory<BoxFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'order_id',
        'subscription_id',
        'week_start_date',
        'status',
        'modification_deadline_at',
        'next_update_expected_at',
        'estimated_value',
        'preferences_snapshot',
        'allergies_snapshot',
        'box_size_snapshot',
        'fulfillment_mode_snapshot',
        'is_test',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BoxStatus::class,
            'week_start_date' => 'date',
            'modification_deadline_at' => 'datetime',
            'next_update_expected_at' => 'datetime',
            'estimated_value' => 'decimal:2',
            'preferences_snapshot' => 'array',
            'allergies_snapshot' => 'array',
            'is_test' => 'boolean',
        ];
    }

    /**
     * Get the user that owns this box.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Trial order this box belongs to, if any.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Subscription this box belongs to, if any.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Deliveries that fulfill this box (usually one, but overrides create more).
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function isTrial(): bool
    {
        return $this->order_id !== null;
    }

    /**
     * Get all items assigned to this box.
     */
    public function items(): HasMany
    {
        return $this->hasMany(BoxItem::class);
    }

    /**
     * Get all products contained in this box via box items.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'box_items')
            ->withPivot(['quantity', 'unit', 'is_extra'])
            ->withTimestamps();
    }

    /**
     * Filter boxes for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Filter boxes by week start date.
     */
    public function scopeForWeek(Builder $query, string $weekStartDate): Builder
    {
        return $query->whereDate('week_start_date', $weekStartDate);
    }

    /**
     * Whether an admin may add, update, or remove line items (ERD-192).
     */
    public function allowsAdminLineEdits(): bool
    {
        if ((bool) config('admin.box_line_edit_override', false)) {
            return true;
        }

        return $this->status === BoxStatus::Editable
            && $this->modification_deadline_at?->isFuture();
    }

    /**
     * Valid status transitions: editable -> locked -> delivered (forward only).
     * Backward: locked -> editable, delivered -> editable (admin correction).
     */
    public function canTransitionTo(BoxStatus $target): bool
    {
        return match ($this->status) {
            BoxStatus::Editable => $target === BoxStatus::Locked,
            BoxStatus::Locked => $target === BoxStatus::Editable || $target === BoxStatus::Delivered,
            BoxStatus::Delivered => $target === BoxStatus::Editable,
        };
    }

    /**
     * Assert valid transition and throw DomainException if invalid.
     */
    public function assertCanTransitionTo(BoxStatus $target): void
    {
        if ($this->canTransitionTo($target)) {
            return;
        }

        Log::warning('Invalid box status transition attempted', [
            'box_id' => $this->id,
            'from_status' => $this->status->value,
            'to_status' => $target->value,
        ]);

        throw new DomainException(
            "Cannot transition box from {$this->status->value} to {$target->value}."
        );
    }

    /**
     * Mark the box as editable.
     */
    public function markEditable(): void
    {
        $this->assertCanTransitionTo(BoxStatus::Editable);
        $this->update(['status' => BoxStatus::Editable]);
    }

    /**
     * Mark the box as locked.
     */
    public function lock(): void
    {
        $this->assertCanTransitionTo(BoxStatus::Locked);
        $this->update(['status' => BoxStatus::Locked]);
    }

    /**
     * Mark the box as delivered.
     */
    public function deliver(): void
    {
        $this->assertCanTransitionTo(BoxStatus::Delivered);
        $this->update(['status' => BoxStatus::Delivered]);
    }
}
