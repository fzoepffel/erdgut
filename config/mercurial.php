<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sender
    |--------------------------------------------------------------------------
    |
    | The weekly mercurial goes out from the customer-facing kontakt mailbox
    | rather than the default noreply sender, so replies reach a real inbox.
    |
    */

    'from_address' => env('MERCURIAL_FROM_ADDRESS', 'kontakt@erd-gut.de'),
    'from_name' => env('MERCURIAL_FROM_NAME', 'Erdgut'),

    /*
    |--------------------------------------------------------------------------
    | Personalization fallbacks
    |--------------------------------------------------------------------------
    |
    | Values substituted for the {{name}} / {{restaurant}} merge tokens when a
    | recipient has no contact or business name on file (e.g. a hand-added
    | address). Keeps a greeting like "Liebe {{name}}," reading naturally.
    |
    */

    'fallback_name' => env('MERCURIAL_FALLBACK_NAME', 'zusammen'),
    'fallback_restaurant' => env('MERCURIAL_FALLBACK_RESTAURANT', 'Team'),

];
