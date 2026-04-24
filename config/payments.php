<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Payment Retry Attempts
    |--------------------------------------------------------------------------
    |
    | The maximum number of charge attempts per billing cycle before giving up.
    | Failed payments below this threshold are retried by the payments:retry
    | command. When exceeded, the subscription may be paused (ERD-093).
    |
    */
    'max_retries' => (int) env('PAYMENT_MAX_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Provider-side refund API
    |--------------------------------------------------------------------------
    |
    | When enabled, admin-initiated refunds also call the SumUp refund API in
    | addition to updating the local ledger. When disabled (default), refunds
    | are book-keeping only — admin coordinates the actual money movement
    | out-of-band and pastes the reference. Separating the flag from the local
    | row lets us ship the refund UI before SumUp credentials are cleared for
    | the refund scope.
    |
    */
    'refund_api_enabled' => (bool) env('PAYMENTS_REFUND_API_ENABLED', false),

];
