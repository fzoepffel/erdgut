<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Database\Factories\DeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'address_id',
        'order_id',
        'box_id',
        'scheduled_for_date',
        'timeslot',
        'status',
        'special_instructions',
        'is_weekly_override',
        'overrides_delivery_id',
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
            'status' => DeliveryStatus::class,
            'scheduled_for_date' => 'date:Y-m-d',
            'is_weekly_override' => 'boolean',
            'is_test' => 'boolean',
        ];
    }

    /**
     * Get the user that owns this delivery.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the address assigned to this delivery.
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * Trial order this delivery fulfills, if any.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Box this delivery fulfills, if any.
     */
    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    /**
     * Get the base delivery that this row overrides.
     */
    public function overridesDelivery(): BelongsTo
    {
        return $this->belongsTo(self::class, 'overrides_delivery_id');
    }

    /**
     * Get all weekly override deliveries for this base row.
     */
    public function overrideDeliveries(): HasMany
    {
        return $this->hasMany(self::class, 'overrides_delivery_id');
    }

    /**
     * @return list<string>
     */
    public static function allowedTimeslots(): array
    {
        return [
            '08:00-12:00',
            '12:00-16:00',
            '16:00-20:00',
        ];
    }
}
