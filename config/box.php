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

    /*
    |--------------------------------------------------------------------------
    | Default box composition by size
    |--------------------------------------------------------------------------
    |
    | Number of products to pick per category for each box size.
    | The service selects random available products from each category.
    | Each item gets quantity 1 and unit from product metadata.
    |
    */
    'composition_by_size' => [
        'small' => [
            'vegetables' => 3,
            'fruit' => 2,
            'dairy' => 1,
            'bakery' => 1,
        ],
        'medium' => [
            'vegetables' => 5,
            'fruit' => 3,
            'dairy' => 2,
            'bakery' => 1,
        ],
        'large' => [
            'vegetables' => 7,
            'fruit' => 4,
            'dairy' => 2,
            'bakery' => 2,
        ],
    ],

];
