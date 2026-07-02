<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Owner active-center session (ADR 0011)
    |--------------------------------------------------------------------------
    |
    | Server-side session keys for the Owner's selected operational center.
    | See docs/design/data-model.md § Owner active-center session.
    |
    */

    'session' => [
        'center_id' => 'owner.active_center_id',
        'organization_id' => 'owner.active_organization_id',
        'owner_user_id' => 'owner.active_center_owner_user_id',
        'selected_at' => 'owner.active_center_selected_at',
    ],

    'selection_route_name' => 'center.select',

    'operational_route_names' => [
        'dashboard',
        'imports.create',
        'imports.index',
        'records.index',
        'daily-versions.index',
        'revisions.index',
        'reports.index',
        'anomalies.index',
        'whatsapp-history.index',
    ],

];
