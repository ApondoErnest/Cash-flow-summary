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

    /*
    |--------------------------------------------------------------------------
    | Synchronous processing
    |--------------------------------------------------------------------------
    |
    | When true, verification runs inline instead of on the queue. Defaults to
    | true in local so CSV verify works without Horizon / queue:work. Set false
    | in production and run a queue worker (see docs/operations/setup.md).
    |
    */

    'process_synchronously' => env('CSV_VERIFICATION_SYNC', env('APP_ENV') === 'local'),

    /*
    |--------------------------------------------------------------------------
    | Queue job limits (large CSV files)
    |--------------------------------------------------------------------------
    */

    'job_timeout_seconds' => (int) env('CSV_VERIFICATION_JOB_TIMEOUT', 600),

    'job_tries' => (int) env('CSV_VERIFICATION_JOB_TRIES', 1),

    'job_memory_mb' => (int) env('CSV_VERIFICATION_JOB_MEMORY', 512),

];
