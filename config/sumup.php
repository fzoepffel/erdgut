<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SumUp API configuration
    |--------------------------------------------------------------------------
    |
    | These values configure SumUp API access for recurring payment flows.
    | API key and merchant code are validated when SumUpService is resolved.
    |
    */
    'api_key' => env('SUMUP_API_KEY'),
    'merchant_code' => env('SUMUP_MERCHANT_CODE'),
    'base_url' => env('SUMUP_BASE_URL', 'https://api.sumup.com'),
    'currency' => env('SUMUP_CURRENCY', 'EUR'),
    'webhook_secret' => env('SUMUP_WEBHOOK_SECRET'),
    'webhook_url' => env('SUMUP_WEBHOOK_URL'),
    /*
     * Browser redirect after 3DS / hosted steps (not the webhook URL).
     * Leave empty to use route('app.subscriptions.show') with APP_URL.
     */
    'checkout_return_url' => env('SUMUP_CHECKOUT_RETURN_URL'),
    'mode' => env('SUMUP_MODE', 'sandbox'),

];
