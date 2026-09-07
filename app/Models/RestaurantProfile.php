<?php

namespace App\Models;

use App\Enums\RestaurantVerificationStatus;
use Database\Factories\RestaurantProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Business details + verification state for a restaurant (B2B) account.
 * Created pending on signup; an admin verifies (opens the account) or
 * rejects (the user + this profile are hard-deleted).
 */
class RestaurantProfile extends Model
{
    /** @use HasFactory<RestaurantProfileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'business_name',
        'contact_name',
        'mercurial_locale',
        'handelsregisternummer',
        'ust_id_nr',
        'billing_street',
        'billing_postal_code',
        'billing_city',
        'verification_status',
        'verified_at',
        'verified_by_user_id',
        'rejected_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'verification_status' => RestaurantVerificationStatus::class,
            'verified_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function isVerified(): bool
    {
        return $this->verification_status === RestaurantVerificationStatus::Verified;
    }

    public function isPending(): bool
    {
        return $this->verification_status === RestaurantVerificationStatus::Pending;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
