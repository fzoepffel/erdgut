<?php

namespace App\Services\Shop;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\ShopOrder;
use Illuminate\Support\Facades\DB;

/**
 * Coupon validation, discount math, and redemption-cap bookkeeping for the
 * anonymous shop.
 *
 * Coupons reduce the *product subtotal only* — never the delivery fee. The
 * discount is clamped so the post-discount subtotal never falls below the
 * SumUp floor (`config('coupon.min_chargeable_eur')`); together with the
 * "percent < 100" and fixed-voucher-minimum rules this guarantees every
 * order still settles through SumUp (there is no free-order path).
 *
 * Anti-abuse caps are counted on *finalized* redemptions only
 * ({@see finalizeRedemption()} — written when an order becomes a confirmed
 * sale), so abandoned online checkouts never consume a slot. A refund/cancel
 * releases the slot via {@see voidRedemption()}.
 */
class CouponService
{
    // Rejection reasons (mapped to localized, coarse messages by the UI).
    public const REASON_INVALID = 'invalid';

    public const REASON_MIN_ORDER = 'min_order';

    public const REASON_LIMIT_REACHED = 'limit_reached';

    /**
     * Validate a typed code for a checkout and compute its discount.
     *
     * `$subtotal` is the gross product subtotal in EUR (delivery fee
     * excluded); `$deliveryFee` is the EUR delivery fee (0 for pickup) and is
     * only used by free-delivery coupons. `$email` keys the per-email limit.
     * Returns an accepted result with the EUR discount, or a rejection with a
     * coarse reason.
     */
    public function validateForCheckout(string $code, string $email, float $subtotal, float $deliveryFee = 0.0): CouponValidationResult
    {
        $code = trim($code);
        if ($code === '') {
            return CouponValidationResult::reject(self::REASON_INVALID);
        }

        $coupon = Coupon::query()->forCode($code)->first();

        // Collapse "doesn't exist", "inactive", and "outside window" into a
        // single coarse reason so a guest can't enumerate valid codes.
        if ($coupon === null || ! $coupon->is_active || ! $coupon->isWithinWindow()) {
            return CouponValidationResult::reject(self::REASON_INVALID);
        }

        $minOrder = $coupon->min_order_eur !== null ? (float) $coupon->min_order_eur : null;
        if ($minOrder !== null && $subtotal + 1e-6 < $minOrder) {
            return CouponValidationResult::reject(self::REASON_MIN_ORDER, [
                'min' => number_format($minOrder, 2, ',', '.'),
            ]);
        }

        // Global cap: total finalized redemptions across all customers.
        if (! $coupon->hasGlobalCapacity()) {
            return CouponValidationResult::reject(self::REASON_LIMIT_REACHED);
        }

        // Per-email cap: finalized redemptions by this email.
        $normalizedEmail = CouponRedemption::normalizeEmail($email);
        if ($normalizedEmail !== '') {
            $used = $coupon->redemptions()
                ->where('status', CouponRedemption::STATUS_REDEEMED)
                ->where('email', $normalizedEmail)
                ->count();

            if ($used >= $coupon->per_email_limit) {
                return CouponValidationResult::reject(self::REASON_LIMIT_REACHED);
            }
        }

        $discount = $this->computeDiscount($coupon, $subtotal, $deliveryFee);
        if ($discount <= 0.0) {
            // Nothing to grant (e.g. subtotal already at the floor, or a
            // free-delivery coupon on a pickup order with no fee) — treat as
            // invalid rather than show a EUR 0.00 discount line.
            return CouponValidationResult::reject(self::REASON_INVALID);
        }

        return CouponValidationResult::accept($coupon, $discount);
    }

    /**
     * EUR discount a coupon grants.
     *
     * Percent: `value`% of the subtotal, optionally capped by
     * `max_discount_eur`. Fixed: the flat `value`. Both reduce the product
     * subtotal and are clamped so the post-discount subtotal stays at or above
     * the SumUp floor — the order can never go free. FreeDelivery: waives the
     * delivery fee (the discount equals `$deliveryFee`, 0 on pickup), leaving
     * the product subtotal charged in full.
     */
    public function computeDiscount(Coupon $coupon, float $subtotal, float $deliveryFee = 0.0): float
    {
        // Free delivery is the only type that touches the delivery fee rather
        // than the subtotal — it simply cancels the fee (nothing on pickup).
        if ($coupon->type === CouponType::FreeDelivery) {
            return round(max(0.0, $deliveryFee), 2);
        }

        if ($subtotal <= 0.0) {
            return 0.0;
        }

        $raw = match ($coupon->type) {
            CouponType::Percent => $subtotal * ((float) $coupon->value / 100.0),
            CouponType::Fixed => (float) $coupon->value,
            CouponType::FreeDelivery => 0.0,
        };

        if ($coupon->type === CouponType::Percent && $coupon->max_discount_eur !== null) {
            $raw = min($raw, (float) $coupon->max_discount_eur);
        }

        // Never drop the charged subtotal below the SumUp floor.
        $floor = (float) config('coupon.min_chargeable_eur', 1.00);
        $maxAllowed = max(0.0, $subtotal - $floor);

        return round(max(0.0, min($raw, $maxAllowed)), 2);
    }

    /**
     * Record a finalized redemption for a confirmed-sale order, under a
     * coupon row lock so concurrent paid orders can't both slip past the
     * global cap. Idempotent: a second call for the same order is a no-op.
     */
    public function finalizeRedemption(ShopOrder $order): void
    {
        if ($order->coupon_id === null || (float) $order->discount_eur <= 0.0) {
            return;
        }

        DB::transaction(function () use ($order): void {
            /** @var Coupon|null $coupon */
            $coupon = Coupon::query()->lockForUpdate()->find($order->coupon_id);
            if ($coupon === null) {
                return;
            }

            // Idempotency — never double-count one order.
            $exists = CouponRedemption::query()
                ->where('shop_order_id', $order->id)
                ->where('status', CouponRedemption::STATUS_REDEEMED)
                ->exists();
            if ($exists) {
                return;
            }

            CouponRedemption::create([
                'coupon_id' => $coupon->id,
                'shop_order_id' => $order->id,
                'email' => CouponRedemption::normalizeEmail($order->email),
                'discount_eur' => (float) $order->discount_eur,
                'status' => CouponRedemption::STATUS_REDEEMED,
                'redeemed_at' => now(),
            ]);

            $coupon->increment('redeemed_count');
        });
    }

    /**
     * Void a finalized redemption (refund/cancel) and release its slot back
     * to the coupon's caps. Idempotent: no redeemed row ⇒ no-op.
     */
    public function voidRedemption(ShopOrder $order): void
    {
        if ($order->coupon_id === null) {
            return;
        }

        DB::transaction(function () use ($order): void {
            /** @var Coupon|null $coupon */
            $coupon = Coupon::query()->lockForUpdate()->find($order->coupon_id);
            if ($coupon === null) {
                return;
            }

            $redemption = CouponRedemption::query()
                ->where('shop_order_id', $order->id)
                ->where('status', CouponRedemption::STATUS_REDEEMED)
                ->first();
            if ($redemption === null) {
                return;
            }

            $redemption->forceFill(['status' => CouponRedemption::STATUS_VOID])->save();

            if ($coupon->redeemed_count > 0) {
                $coupon->decrement('redeemed_count');
            }
        });
    }
}
