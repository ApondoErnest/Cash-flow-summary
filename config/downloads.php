<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Signed download URLs
    |--------------------------------------------------------------------------
    |
    | All private file downloads use temporary signed routes plus policy checks.
    | See docs/architecture/security-privacy.md § CSV and file security.
    |
    */

    'signed_url_ttl_minutes' => (int) env('DOWNLOAD_SIGNED_URL_TTL_MINUTES', 30),

];
