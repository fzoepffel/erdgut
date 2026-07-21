<?php

namespace App\Models;

use App\Services\Shop\ShopReducedOfferService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recurring weekly "reduced offer" window: the shop shows the reduced-offer
 * banner from {@see $starts_weekday} at {@see $starts_time} until the next
 * {@see $reopens_weekday} at {@see $reopens_time}. State is derived on read in
 * {@see ShopReducedOfferService}; nothing is materialised for display.
 */
class ShopReducedOfferRule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'starts_weekday',
        'starts_time',
        'reopens_weekday',
        'reopens_time',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_weekday' => 'integer',
            'reopens_weekday' => 'integer',
            'is_active' => 'boolean',
        ];
        // starts_time / reopens_time stay raw "H:i:s" wall-clock strings.
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
