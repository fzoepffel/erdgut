<?php

namespace App\Models;

use App\Enums\RestaurantStandingOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recurring weekly delivery arrangement for a restaurant. Activated when the
 * admin confirms the first order; a scheduled command materializes auto-confirmed
 * RestaurantOrders for subsequent weeks (see RestaurantStandingOrderService).
 */
class RestaurantStandingOrder extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'delivery_weekday',
        'delivery_street',
        'delivery_postal_code',
        'delivery_city',
        'delivery_notes',
        'status',
        'locale',
        'confirmed_at',
        'confirmed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => RestaurantStandingOrderStatus::class,
            'delivery_weekday' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return HasMany<RestaurantStandingOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RestaurantStandingOrderItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === RestaurantStandingOrderStatus::Active;
    }

    public function isPending(): bool
    {
        return $this->status === RestaurantStandingOrderStatus::Pending;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', RestaurantStandingOrderStatus::Active->value);
    }
}
