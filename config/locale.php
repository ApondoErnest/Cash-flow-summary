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

];
