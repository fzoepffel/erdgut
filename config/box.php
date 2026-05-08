<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Included value by box size (EUR)
    |--------------------------------------------------------------------------
    |
    | The subscription's included value per box size. Used to compute the
    | budget remainder (included_value - box estimated_value).
    |
    */
    'included_value_by_size' => [
        'small' => (float) env('BOX_INCLUDED_VALUE_SMALL', 20),
        'medium' => (float) env('BOX_INCLUDED_VALUE_MEDIUM', 35),
        'large' => (float) env('BOX_INCLUDED_VALUE_LARGE', 42),
    ],

    /*
    |--------------------------------------------------------------------------
    | One-time trial billing (first paid recurring charge)
    |--------------------------------------------------------------------------
    |
    | While trial_completed_at is null, expectedChargeAmount() uses trial_price_ttc
    | (TTC) for the first successful recurring payment, regardless of box_size.
    | After the first payment is marked paid (webhook), trial_completed_at is set
    | and normal size-based pricing applies. Disable for legacy behaviour/tests.
    |
    */
    'trial_billing_enabled' => (bool) env('BOX_TRIAL_BILLING_ENABLED', true),

    'trial_price_ttc' => (float) env('BOX_TRIAL_PRICE_TTC', 35),

    /*
    |--------------------------------------------------------------------------
    | Standalone trial order (one-off, no subscription)
    |--------------------------------------------------------------------------
    |
    | Used by the trial-order flow where a user pays for a single box with no
    | recurring mandate. Separate from trial_price_ttc (which governs the
    | first charge of an existing recurring subscription).
    |
    */
    'trial_price_oneoff_ttc' => (float) env('BOX_TRIAL_PRICE_ONEOFF_TTC', 35),

    'trial_scheduling_promise_days' => (int) env('BOX_TRIAL_SCHEDULING_PROMISE_DAYS', 14),

    'trial_description' => (string) env('BOX_TRIAL_DESCRIPTION', 'Erdgut trial box — one-time delivery'),

    /*
    |--------------------------------------------------------------------------
    | Optional eggs / dairy add-on (TTC, same for all box sizes)
    |--------------------------------------------------------------------------
    |
    | When preferences.eggs_dairy_add_on is true, this amount is added to the
    | recurring charge and to the included value used for budget remainder.
    |
    */
    'eggs_dairy_add_on_eur' => (float) env('BOX_EGGS_DAIRY_ADD_ON_EUR', 4.5),

    /*
    |--------------------------------------------------------------------------
    | Eggs add-on product ID (auto-projection)
    |--------------------------------------------------------------------------
    |
    | When set, the box-setup flow automatically appends one of this product
    | as a position to every customer who has preferences.eggs_dairy_add_on
    | enabled. If null (or the configured product can't be resolved), the
    | customer is flagged for admin attention and the admin must add a
    | substitute manually.
    |
    */
    'eggs_dairy_add_on_product_id' => env('BOX_EGGS_DAIRY_ADD_ON_PRODUCT_ID') !== null
        ? (int) env('BOX_EGGS_DAIRY_ADD_ON_PRODUCT_ID')
        : null,

    /*
    |--------------------------------------------------------------------------
    | Default box composition by size
    |--------------------------------------------------------------------------
    |
    | Number of products to pick per category for each box size.
    | The service selects random available products from each category.
    | Each item gets quantity 1 and unit from product metadata.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Admin planner (informational)
    |--------------------------------------------------------------------------
    |
    | Optional margin rate (0–1) for an ops hint on admin screens:
    | included TTC × (1 − rate). Does not block saves or fills.
    |
    */
    'admin_planning_margin_rate' => (float) env('BOX_ADMIN_PLANNING_MARGIN_RATE', 0.30),

    /*
    | D6/D7/F8 (UX-review notes): box composition uses the chapter
    | taxonomy. The older `vegetables` + `fruit` keys merged into
    | `produce` (with the combined count), `dairy` renamed to
    | `eggs_and_dairy`, and `bakery` renamed to `bread`. Per-size
    | totals are unchanged from before:
    |   small  = 7 (3+2+1+1 → 5+1+1)
    |   medium = 11 (5+3+2+1 → 8+2+1)
    |   large  = 15 (7+4+2+2 → 11+2+2)
    */
    'composition_by_size' => [
        'small' => [
            'produce' => 5,
            'eggs_and_dairy' => 1,
            'bread' => 1,
        ],
        'medium' => [
            'produce' => 8,
            'eggs_and_dairy' => 2,
            'bread' => 1,
        ],
        'large' => [
            'produce' => 11,
            'eggs_and_dairy' => 2,
            'bread' => 2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pickup location (mock until launch)
    |--------------------------------------------------------------------------
    |
    | Single mock pickup location used on the schedule + payment + verify-email
    | screens whenever the customer chose `fulfillment_mode = pickup`. Real
    | location TBD; this lets the wizard render coherently in the meantime.
    |
    */
    'pickup_location' => [
        'name' => env('BOX_PICKUP_LOCATION_NAME', 'Erdgut Marktstand'),
        'street' => env('BOX_PICKUP_LOCATION_STREET', 'Kollwitzplatz'),
        'postal_code' => env('BOX_PICKUP_LOCATION_POSTAL_CODE', '10435'),
        'city' => env('BOX_PICKUP_LOCATION_CITY', 'Berlin'),
        'window' => env('BOX_PICKUP_LOCATION_WINDOW', 'Donnerstags 12–18 Uhr'),
    ],

];
