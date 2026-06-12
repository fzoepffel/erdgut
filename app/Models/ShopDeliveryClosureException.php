<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single day within a closure range that stays open for delivery.
 */
class ShopDeliveryClosureException extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_delivery_closure_id',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    public function closure(): BelongsTo
    {
        return $this->belongsTo(ShopDeliveryClosure::class, 'shop_delivery_closure_id');
    }
}
