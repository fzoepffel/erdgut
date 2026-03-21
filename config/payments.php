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

];
