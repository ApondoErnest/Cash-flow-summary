<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Verification token TTL
    |--------------------------------------------------------------------------
    |
    | Abandoned import verifications expire after this many minutes.
    | See docs/operations/setup.md and IMPORT_VERIFICATION_TTL_MINUTES.
    |
    */

    'ttl_minutes' => (int) env('IMPORT_VERIFICATION_TTL_MINUTES', 120),

    /*
    |--------------------------------------------------------------------------
    | Temporary upload storage
    |--------------------------------------------------------------------------
    */

    'temp_disk' => env('CSV_VERIFICATION_TEMP_DISK', 'local'),

    'temp_directory' => 'temp/verifications',

    /*
    |--------------------------------------------------------------------------
    | Scheduled cleanup
    |--------------------------------------------------------------------------
    */

    'cleanup_batch_size' => (int) env('CSV_VERIFICATION_CLEANUP_BATCH', 100),

];
