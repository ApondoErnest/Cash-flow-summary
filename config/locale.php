<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported UI Locales
    |--------------------------------------------------------------------------
    |
    | Cash Flow Summary UI is fully bilingual. CSV import language is detected
    | separately from file headers (see csv-specification.md).
    |
    */

    'supported' => ['en', 'fr'],

    'session_key' => 'locale',

    'cookie' => 'locale',

    /*
    |--------------------------------------------------------------------------
    | HTML / Intl language tags
    |--------------------------------------------------------------------------
    |
    | Used for <html lang> and browser Intl formatting so dates/times follow
    | day/month order common in Cameroon (not en-US month/day).
    |
    */

    'html' => [
        'en' => 'en-GB',
        'fr' => 'fr-CM',
    ],

];
