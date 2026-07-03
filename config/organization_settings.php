<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Encrypted organization setting keys
    |--------------------------------------------------------------------------
    |
    | Values for these keys are encrypted at rest using Laravel Crypt.
    | See docs/design/data-model.md (organization_settings).
    |
    */

    'encrypted_keys' => [
        'whatsapp.access_token',
        'whatsapp.webhook_verify_token',
    ],

];
