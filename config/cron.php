<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP cron trigger key (e.g. IONOS scheduled URL /cron/run?key=...)
    |--------------------------------------------------------------------------
    |
    | Read via config() so it works when configuration is cached
    | (php artisan config:cache). Do not use env('CRON_KEY') outside this file.
    |
    */
    'key' => env('CRON_KEY'),

];
