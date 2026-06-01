<?php

namespace App\Services\Shop;

use App\Models\Coupon;

/**
 * Outcome of {@see CouponService::validateForCheckout()}.
 *
 * Either accepted (carrying the resolved {@see Coupon} and the EUR discount
 * to apply to the product subtotal) or rejected (carrying a machine-readable
 * {@see $reason} plus optional translation `$params`). The reason is mapped
 * to a localized, deliberately-coarse message by the storefront so a probing
 * guest can't enumerate which codes exist.
 */
class CouponValidationResult
{
    /**
     * @param  array<string, scalar>  $params
     */
    private function __construct(
        public readonly bool $accepted,
        public readonly ?Coupon $coupon = null,
        public readonly float $discountEur = 0.0,
        public readonly ?string $reason = null,
        public readonly array $params = [],
    ) {}

    public static function accept(Coupon $coupon, float $discountEur): self
    {
        return new self(accepted: true, coupon: $coupon, discountEur: round($discountEur, 2));
    }

    /**
     * @param  array<string, scalar>  $params
     */
    public static function reject(string $reason, array $params = []): self
    {
        return new self(accepted: false, reason: $reason, params: $params);
    }
}
