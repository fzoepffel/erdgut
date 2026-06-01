<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A finalized (or later voided) coupon redemption.
 *
 * Written only when an order becomes a confirmed sale — see
 * {@see App\Services\Shop\CouponService::finalizeRedemption()}. A refund or
 * cancel flips {@see $status} to `void` and releases the slot.
 *
 * @property int $id
 * @property int $coupon_id
 * @property int|null $shop_order_id
 * @property string $email
 * @property numeric $discount_eur
 * @property string $status
 * @property Carbon|null $redeemed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CouponRedemption extends Model
{
    use HasFactory;

    public const STATUS_REDEEMED = 'redeemed';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'coupon_id',
        'shop_order_id',
        'email',
        'discount_eur',
        'status',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_eur' => 'decimal:2',
            'redeemed_at' => 'datetime',
        ];
    }

    /**
     * Normalize an email for case-insensitive per-email-limit counting.
     */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return BelongsTo<ShopOrder, $this>
     */
    public function shopOrder(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class);
    }
}
