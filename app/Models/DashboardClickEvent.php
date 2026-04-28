<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Click-destination instrumentation row for the customer dashboard.
 *
 * @property int $id
 * @property ?int $user_id
 * @property string $section
 * @property string $destination
 * @property ?string $locale
 * @property Carbon $occurred_at
 */
class DashboardClickEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'section',
        'destination',
        'locale',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
