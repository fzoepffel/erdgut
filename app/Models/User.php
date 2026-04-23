<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'profile_preferences',
        'is_test',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'profile_preferences' => 'array',
            'is_test' => 'boolean',
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Whether the user may access admin-only routes and actions.
     * Not mass-assignable; set only via trusted code (e.g. artisan command).
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Get all addresses assigned to the user.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get the primary address for the user.
     */
    public function primaryAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('is_primary', true);
    }

    /**
     * Get permanent delivery preferences for the user.
     */
    public function deliveryPreference(): HasOne
    {
        return $this->hasOne(DeliveryPreference::class);
    }

    /**
     * Get all subscriptions assigned to the user.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get all payments linked to this user through subscriptions.
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Subscription::class);
    }

    /**
     * Get all deliveries assigned to the user.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * Get all boxes assigned to the user.
     */
    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class);
    }

    /**
     * Get all payment mandates registered for this user (one per provider).
     */
    public function paymentMandates(): HasMany
    {
        return $this->hasMany(PaymentMandate::class);
    }

    /**
     * Current active SumUp mandate, if any.
     */
    public function activeSumupMandate(): ?PaymentMandate
    {
        return $this->paymentMandates()
            ->where('provider', 'sumup')
            ->where('status', PaymentMandate::STATUS_ACTIVE)
            ->latest('id')
            ->first();
    }
}
