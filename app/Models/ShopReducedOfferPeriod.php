<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A concrete reduced-offer window. Manual ('manual') windows are created by an
 * admin; recurring ('recurring') windows are materialised lazily by the reopen
 * command so the waitlist email fires exactly once per cycle.
 *
 * - opened_at:   when the window actually reopened (auto at reopens_at, or
 *                earlier via admin "open now"). Null while still reduced.
 * - notified_at: when the waitlist email was sent for this window (idempotency).
 */
class ShopReducedOfferPeriod extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'starts_at',
        'reopens_at',
        'opened_at',
        'notified_at',
        'source',
        'rule_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'reopens_at' => 'datetime',
            'opened_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ShopReducedOfferRule::class, 'rule_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
