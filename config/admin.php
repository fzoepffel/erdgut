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

    /*
    |--------------------------------------------------------------------------
    | Admin box planner horizon (weeks)
    |--------------------------------------------------------------------------
    |
    | Forward weeks shown in the admin weekly box planner (ERD-190+, bumped
    | to 12 in ERD-335 to give operations a quarter of forward visibility).
    | Capped at 12 to keep the compact grid readable.
    |
    */
    'box_planner_weeks_ahead' => min(max((int) env('ADMIN_BOX_PLANNER_WEEKS_AHEAD', 8), 1), 12),

    /*
    |--------------------------------------------------------------------------
    | Admin box line edit override (ERD-192)
    |--------------------------------------------------------------------------
    |
    | When true, admins may add/remove/update box line items even when the box
    | is locked or past modification_deadline_at. Default false matches customer rules.
    |
    */
    'box_line_edit_override' => (bool) env('ADMIN_BOX_LINE_EDIT_OVERRIDE', false),
];
