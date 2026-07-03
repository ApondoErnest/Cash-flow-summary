<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Export file storage
    |--------------------------------------------------------------------------
    |
    | Generated report exports are stored on a private disk under exports/.
    | See docs/design/data-model.md (export_requests).
    |
    */

    'disk' => env('EXPORTS_DISK', 'local'),

    'directory' => 'exports',

    'ttl_hours' => (int) env('EXPORTS_TTL_HOURS', 6),

    'cleanup_batch_size' => (int) env('EXPORTS_CLEANUP_BATCH_SIZE', 100),

];
