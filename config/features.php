<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature flags for gradual rollout
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the home page surfaces the "Try a box" trial CTA and the
     * onboarding wizard exposes the trial commitment branch. When false,
     * the trial flow is disabled end-to-end (wizard forces subscription
     * branch and admin order UI stays reachable but inert for customers).
     */
    'trial_orders_enabled' => (bool) env('FEATURES_TRIAL_ORDERS_ENABLED', true),

];
