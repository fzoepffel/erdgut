<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMandate extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $hidden = [
        'provider_customer_id',
        'provider_token',
    ];

    protected $fillable = [
        'user_id',
        'provider',
        'provider_customer_id',
        'provider_token',
        'status',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_customer_id' => 'encrypted',
            'provider_token' => 'encrypted',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function revoke(): void
    {
        $this->update([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);
    }
}
