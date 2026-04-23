<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionSkipWeek extends Model
{
    public const SOURCE_USER = 'user';

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_PAUSE_WINDOW = 'pause_window';

    protected $fillable = [
        'subscription_id',
        'week_start_date',
        'source',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
