<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shop coupon tunables
    |--------------------------------------------------------------------------
    |
    | Coupons reduce the product subtotal of an anonymous shop order (never
    | the delivery fee). Only the cross-cutting scalars live here so they can
    | be tuned without code.
    |
    */

    // Floor (EUR) the *charged* total may never drop below after a discount.
    // SumUp rejects charges under ~EUR 1.00, so a fixed coupon is clamped so
    // the order's total_amount_eur always stays at or above this. There is
    // no 100%-off / free-order path by design — combined with the fixed
    // voucher minimum, every order still settles through SumUp.
    'min_chargeable_eur' => env('COUPON_MIN_CHARGEABLE_EUR', 1.00),

    // Largest percentage a percent coupon may be configured with. Strictly
    // below 100 so a percentage discount can never zero out the subtotal.
    'max_percent' => env('COUPON_MAX_PERCENT', 90),

];
