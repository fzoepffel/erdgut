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
            'allowed_weekdays' => [2, 3, 4, 5], // Tue-Fri
            'allowed_timeslots' => [
                Timeslot::Morning->value,
                Timeslot::Afternoon->value,
                Timeslot::Evening->value,
            ],
        ],
    ],

];
