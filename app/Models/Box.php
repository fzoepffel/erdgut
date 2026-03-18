<?php

namespace App\Models;

use App\Enums\BoxStatus;
use Database\Factories\BoxFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'week_start_date',
        'status',
        'modification_deadline_at',
        'estimated_value',
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
            'estimated_value' => 'decimal:2',
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
     * Mark the box as editable.
     */
    public function markEditable(): void
    {
        $this->update(['status' => BoxStatus::Editable]);
    }

    /**
     * Mark the box as locked.
     */
    public function lock(): void
    {
        $this->update(['status' => BoxStatus::Locked]);
    }

    /**
     * Mark the box as delivered.
     */
    public function deliver(): void
    {
        $this->update(['status' => BoxStatus::Delivered]);
    }
}
