<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Spargel product IDs
    |--------------------------------------------------------------------------
    |
    | The spargel form lives outside the regular subscription/box flow and
    | doesn't link a customer to a User. To respect weekly stock (Phase 3:
    | 2026-05-09) we still need to know which Product rows in the catalog
    | the white-spargel and green-spargel grams go against.
    |
    | Both products must have `unit = 'g'` — the form's grams math assumes
    | the per-week planned quantity is also grams. The boot-time validator
    | (`App\Support\SpargelConfigValidator`) checks this at app start.
    |
    | Set both in the server's `.env`. Leave null in tests / fresh local
    | env — the spargel form falls back to "no weeks available" if either
    | is unset, so the rest of the site keeps working.
    |
    */

    'white_product_id' => env('SPARGEL_PRODUCT_ID_WHITE'),
    'green_product_id' => env('SPARGEL_PRODUCT_ID_GREEN'),

    /*
    |--------------------------------------------------------------------------
    | Quantity validation
    |--------------------------------------------------------------------------
    |
    | Mirrors what was previously inline-constanted in
    | App\Http\Requests\SpargelOrderRequest. Pulled here so the form's
    | step + per-cart bounds can be tuned without a code change.
    |
    */

    'quantity_step_grams' => env('SPARGEL_QUANTITY_STEP_GRAMS', 50),
    'quantity_combined_min_grams' => env('SPARGEL_QUANTITY_COMBINED_MIN_GRAMS', 100),
    'quantity_combined_max_grams' => env('SPARGEL_QUANTITY_COMBINED_MAX_GRAMS', 3000),
    'delivery_min_grams' => env('SPARGEL_DELIVERY_MIN_GRAMS', 500),

    /*
    |--------------------------------------------------------------------------
    | Customer-facing "low stock" badge threshold
    |--------------------------------------------------------------------------
    |
    | Each week in the picker shows a "nur noch wenig übrig" hint when the
    | smaller of (white_remaining, green_remaining) drops below this. No
    | exact remaining grams are shown to customers — see plan rationale.
    |
    */

    'low_stock_badge_threshold_grams' => env('SPARGEL_LOW_STOCK_BADGE_THRESHOLD_GRAMS', 500),

];
