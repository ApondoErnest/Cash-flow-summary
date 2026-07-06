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
        'imports.errors.download',
        'imports.result',
        'imports.show',
        'imports.index',
        'records.index',
        'daily-versions.index',
        'revisions.index',
        'reports.index',
        'exports.download',
        'verifications.errors.download',
        'anomalies.index',
        'whatsapp-history.index',
    ],

    /*
    |--------------------------------------------------------------------------
    | Center-dependent page filter session keys
    |--------------------------------------------------------------------------
    |
    | Cleared when the Owner switches active center so filters do not leak
    | across centers. Add keys here as operational pages introduce filters.
    |
    */
    'page_filter_session_keys' => [
        'owner.filters.dashboard_period',
        'owner.filters.dashboard_period_from',
        'owner.filters.dashboard_period_to',
        'owner.filters.records_search',
        'owner.filters.imports_status',
    ],

];
