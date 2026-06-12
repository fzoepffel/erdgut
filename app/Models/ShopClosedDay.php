<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One future delivery day closed on the public shop storefront.
 */
class ShopClosedDay extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'closed_on',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'closed_on' => 'date:Y-m-d',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
