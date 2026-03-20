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
        'small' => 25,
        'medium' => 35,
        'large' => 45,
    ],

];
