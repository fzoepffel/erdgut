<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual "keep this day open" override. Takes precedence over a recurring
 * closure rule for that date (manual always wins). The force-CLOSED
 * counterpart is {@see ShopClosedDay}.
 */
class ShopForcedOpenDay extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'forced_open_on',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'forced_open_on' => 'date:Y-m-d',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
