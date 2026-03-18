<?php

namespace App\Models;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

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
        'sumup_token',
        'sumup_customer_id',
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
}
