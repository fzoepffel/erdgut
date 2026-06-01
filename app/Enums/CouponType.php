<?php

namespace App\Enums;

/**
 * The kinds of shop coupon.
 *
 *  - Percent: a rate off the product subtotal (e.g. 10 = 10%), optionally
 *    capped by `max_discount_eur`. Validation enforces the rate stays below
 *    100 so an order can never go free.
 *  - Fixed: a flat EUR amount off the subtotal, usually gated behind a
 *    `min_order_eur` so a small order can't be zeroed out.
 *  - FreeDelivery: waives the delivery fee (the discount equals the delivery
 *    fee). It carries no `value`; on a pickup order — where there is no
 *    delivery fee — it grants nothing and is rejected as not applicable.
 *
 * For Percent/Fixed the discount is clamped so the *charged* total never
 * falls below the SumUp floor (`config('coupon.min_chargeable_eur')`).
 */
enum CouponType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case FreeDelivery = 'free_delivery';
}
