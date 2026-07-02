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

];
