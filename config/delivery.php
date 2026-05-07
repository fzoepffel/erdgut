<?php

use App\Enums\Timeslot;

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery zones
    |--------------------------------------------------------------------------
    |
    | A zone defines which weekdays and timeslots are available to customers.
    | Zones are config-backed until we migrate them into their own model.
    | Weekday values use the ISO convention (0 = Sunday, 1 = Monday, ..., 6 = Saturday).
    |
    */

    'default_zone' => env('DELIVERY_DEFAULT_ZONE', 'berlin_core'),

    'zones' => [
        'berlin_core' => [
            'label' => 'Berlin core zone',

            // D2 (UX notes): we only deliver Wed 12–16 today. Earlier the
            // picker offered Mon–Fri × 3 timeslots and silently dropped
            // the preference because the only honoured slot is the
            // afternoon Wednesday window. Pinning the config to reality
            // means the wizard only collects a preference we can keep.
            // Widen these arrays once the fleet covers more days.
            'allowed_weekdays' => [3], // Wednesday
            'allowed_timeslots' => [
                Timeslot::Afternoon->value,
            ],

            // B5 (UX notes): Berlin-only delivery zone. Customer-facing
            // address forms must validate that the postal code falls in
            // this range (and country is DE). The conservative span
            // 10115–14199 covers the inner Berlin ZIP block; refine into
            // discrete sub-ranges if needed (Berlin PLZ has gaps but
            // the outer envelope stays inside 10000–14999).
            'country_code' => 'DE',
            'postal_code_min' => 10115,
            'postal_code_max' => 14199,
        ],
    ],

];
