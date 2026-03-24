<?php

namespace App\Models;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'sumup_token',
        'sumup_customer_id',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'plan',
        'box_size',
        'preferences',
        'allergies',
        'status',
        'next_charge_date',
        'pause_from',
        'pause_until',
        'cancelled_at',
        'cancellation_reason',
        'sumup_token',
        'sumup_customer_id',
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
            'plan' => SubscriptionPlan::class,
            'status' => SubscriptionStatus::class,
            'preferences' => 'array',
            'allergies' => 'array',
            'next_charge_date' => 'date',
            'pause_from' => 'date',
            'pause_until' => 'date',
            'cancelled_at' => 'datetime',
            'is_test' => 'boolean',
            'sumup_token' => 'encrypted',
            'sumup_customer_id' => 'encrypted',
        ];
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all payment attempts for this subscription.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Set subscription state to active.
     */
    public function activate(): void
    {
        $this->update(['status' => SubscriptionStatus::Active]);
    }

    /**
     * Set subscription state to paused.
     */
    public function pause(): void
    {
        $this->update(['status' => SubscriptionStatus::Paused]);
    }

    /**
     * Set subscription state to cancelled.
     */
    public function cancel(): void
    {
        $this->update(['status' => SubscriptionStatus::Cancelled]);
    }

    /**
     * Subscription row used for billing and payment flows (prefers active status, then latest).
     */
    public static function billingSubscriptionForUser(Authenticatable $user): ?self
    {
        return static::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->orderByRaw('status = ? desc', [SubscriptionStatus::Active->value])
            ->latest()
            ->first();
    }

    public function hasPauseWindow(): bool
    {
        return $this->pause_from !== null && $this->pause_until !== null;
    }

    public function isWithinPauseWindow(?Carbon $date = null): bool
    {
        if (! $this->hasPauseWindow()) {
            return false;
        }

        $targetDate = ($date ?? now())->toDateString();

        return $targetDate >= $this->pause_from->toDateString()
            && $targetDate <= $this->pause_until->toDateString();
    }
}
