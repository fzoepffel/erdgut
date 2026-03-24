<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Upcoming billable charges window (admin operations list)
    |--------------------------------------------------------------------------
    |
    | Number of days from today (inclusive) used for the "Upcoming charges"
    | browser. Must align with dashboard metrics 30d card when set to 30.
    |
    */
    'upcoming_charges_days' => (int) env('ADMIN_UPCOMING_CHARGES_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Stale pending payment alert threshold
    |--------------------------------------------------------------------------
    |
    | Pending payment attempts older than this (hours) trigger an operational
    | alert on the admin dashboard.
    |
    */
    'pending_payment_stale_hours' => (int) env('ADMIN_PENDING_PAYMENT_STALE_HOURS', 48),
];
