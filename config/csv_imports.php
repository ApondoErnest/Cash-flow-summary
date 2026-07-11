<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Permanent import file storage
    |--------------------------------------------------------------------------
    |
    | Committed CSV files are stored on a private disk under imports/{center_id}/.
    | See docs/architecture/backend-services.md and CSV_IMPORTS_PERMANENT_DISK.
    |
    */

    'permanent_disk' => env('CSV_IMPORTS_PERMANENT_DISK', 'local'),

    'permanent_directory' => 'imports',

    /*
    |--------------------------------------------------------------------------
    | Large-file processing
    |--------------------------------------------------------------------------
    |
    | Commit heavy work runs on ProcessImportJob (queue). With QUEUE_CONNECTION=sync
    | (tests / simple local), the job still runs inline before the HTTP response.
    |
    */

    'row_insert_chunk_size' => (int) env('CSV_IMPORTS_ROW_CHUNK', 500),

    'ledger_chunk_size' => (int) env('CSV_IMPORTS_LEDGER_CHUNK', 500),

    'job_timeout_seconds' => (int) env('CSV_IMPORTS_JOB_TIMEOUT', 600),

    'job_tries' => (int) env('CSV_IMPORTS_JOB_TRIES', 1),

    'job_memory_mb' => (int) env('CSV_IMPORTS_JOB_MEMORY', 512),

    /*
    |--------------------------------------------------------------------------
    | Synchronous commit finalization
    |--------------------------------------------------------------------------
    |
    | When true, ProcessImportJob work runs inline after creating the import.
    | Defaults to true in local/testing so imports complete without a worker.
    | Set CSV_IMPORTS_SYNC=false in production and run queue workers.
    |
    */

    'process_synchronously' => filter_var(
        env('CSV_IMPORTS_SYNC', env('APP_ENV') === 'local' || env('APP_ENV') === 'testing'),
        FILTER_VALIDATE_BOOLEAN,
    ),

];
