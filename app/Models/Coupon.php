<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An admin-managed shop discount code.
 *
 * Validation, discount math, and the redemption-cap bookkeeping all live in
 * {@see App\Services\Shop\CouponService} — this model stays a thin data +
 * relationship + small-predicate object per the project's "no heavy logic in
 * models" convention.
 *
 * @property int $id
 * @property string $code
 * @property string|null $label
 * @property CouponType $type
 * @property numeric $value
 * @property numeric|null $max_discount_eur
 * @property numeric|null $min_order_eur
 * @property int|null $max_redemptions
 * @property int $per_email_limit
 * @property int $redeemed_count
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property int|null $created_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'type',
        'value',
        'max_discount_eur',
        'min_order_eur',
        'max_redemptions',
        'per_email_limit',
        'redeemed_count',
        'starts_at',
        'expires_at',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'decimal:2',
            'max_discount_eur' => 'decimal:2',
            'min_order_eur' => 'decimal:2',
            'max_redemptions' => 'integer',
            'per_email_limit' => 'integer',
            'redeemed_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Normalize the code to a canonical, trimmed, uppercased form so lookups
     * and the unique constraint are case-insensitive.
     */
    public static function normalizeCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = self::normalizeCode($value);
    }

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * Find an active coupon by its (un-normalized) code.
     *
     * @param  Builder<Coupon>  $query
     * @return Builder<Coupon>
     */
    public function scopeForCode(Builder $query, string $code): Builder
    {
        return $query->where('code', self::normalizeCode($code));
    }

    /**
     * Whether the coupon is within its optional validity window right now.
     */
    public function isWithinWindow(?Carbon $now = null): bool
    {
        $now ??= now();

        if ($this->starts_at !== null && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at !== null && $now->gt($this->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Whether the global redemption cap still has room (null = unlimited).
     */
    public function hasGlobalCapacity(): bool
    {
        return $this->max_redemptions === null
            || $this->redeemed_count < $this->max_redemptions;
    }
}
