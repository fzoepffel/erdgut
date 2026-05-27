<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SumUp — Live credentials (Spargel order shop)
    |--------------------------------------------------------------------------
    |
    | Used exclusively for the spargel one-time checkout. Real money moves
    | here. Payment confirmation happens via the return URL (not a webhook).
    |
    */
    'spargel' => [
        'api_key'             => env('SUMUP_SPARGEL_API_KEY'),
        'merchant_code'       => env('SUMUP_SPARGEL_MERCHANT_CODE'),
        'base_url'            => env('SUMUP_BASE_URL', 'https://api.sumup.com'),
        'currency'            => env('SUMUP_CURRENCY', 'EUR'),
        'checkout_return_url' => env('SUMUP_SPARGEL_CHECKOUT_RETURN_URL'),
        // Spargel uses return-URL verification, not server-side webhooks.
        'webhook_secret'      => null,
        'webhook_url'         => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | SumUp — Sandbox credentials (Subscriptions & trial orders)
    |--------------------------------------------------------------------------
    |
    | Used for all subscription/mandate/recurring-charge flows while the
    | subscription product is in development. Switch SUMUP_MODE to "live"
    | and supply live keys when ready to charge real customers.
    |
    */
    'subscription' => [
        'api_key'             => env('SUMUP_API_KEY'),
        'merchant_code'       => env('SUMUP_MERCHANT_CODE'),
        'base_url'            => env('SUMUP_BASE_URL', 'https://api.sumup.com'),
        'currency'            => env('SUMUP_CURRENCY', 'EUR'),
        'webhook_secret'      => env('SUMUP_WEBHOOK_SECRET'),
        'webhook_url'         => env('SUMUP_WEBHOOK_URL'),
        'checkout_return_url' => env('SUMUP_CHECKOUT_RETURN_URL'),
        'mode'                => env('SUMUP_MODE', 'sandbox'),
    ],

];
