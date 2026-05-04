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

    /*
    |--------------------------------------------------------------------------
    | Recurring Charge Schedule Label
    |--------------------------------------------------------------------------
    |
    | Display-only mirror of the time `subscriptions:charge` runs in
    | `bootstrap/app.php`. The actual schedule is the source of truth; this
    | label exists so admin views (e.g. subscriptions index) can render
    | "next_charge_date 06:00" without hard-coding the time in markup. If the
    | schedule changes, update both this and `bootstrap/app.php` together.
    |
    */
    'recurring_charge_time_label' => env('PAYMENTS_RECURRING_CHARGE_TIME_LABEL', '06:00'),

];
