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

    'composition_by_size' => [
        'small' => [
            'vegetables' => 3,
            'fruit' => 2,
            'dairy' => 1,
            'bakery' => 1,
        ],
        'medium' => [
            'vegetables' => 5,
            'fruit' => 3,
            'dairy' => 2,
            'bakery' => 1,
        ],
        'large' => [
            'vegetables' => 7,
            'fruit' => 4,
            'dairy' => 2,
            'bakery' => 2,
        ],
    ],

];
