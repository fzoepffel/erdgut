<?php

namespace App\Models;

use App\Services\Shop\ShopClosureService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recurring auto-closure rule: delivery days on {@see $weekday} close
 * automatically {@see $lead_days} days before, at {@see $cutoff_time}.
 * One active rule per weekday. Closures are derived on read in
 * {@see ShopClosureService}; nothing is materialized.
 */
class ShopClosureRule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'weekday',
        'lead_days',
        'cutoff_time',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'lead_days' => 'integer',
            'is_active' => 'boolean',
        ];
        // cutoff_time stays a raw "H:i:s" string — it's a wall-clock time, not
        // a datetime; the service reads it via setTimeFromTimeString().
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
