<?php

namespace App\Models;

use Database\Factories\BoxFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Box extends Model
{
    public const STATUS_EDITABLE = 'editable';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_DELIVERED = 'delivered';

    /** @use HasFactory<BoxFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'week_start_date',
        'status',
        'modification_deadline_at',
        'estimated_value',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'modification_deadline_at' => 'datetime',
            'estimated_value' => 'decimal:2',
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
     * Get all items assigned to this box.
     */
    public function items(): HasMany
    {
        return $this->hasMany(BoxItem::class);
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
     * Mark the box as editable.
     */
    public function markEditable(): void
    {
        $this->update(['status' => self::STATUS_EDITABLE]);
    }

    /**
     * Mark the box as locked.
     */
    public function lock(): void
    {
        $this->update(['status' => self::STATUS_LOCKED]);
    }

    /**
     * Mark the box as delivered.
     */
    public function deliver(): void
    {
        $this->update(['status' => self::STATUS_DELIVERED]);
    }
}
