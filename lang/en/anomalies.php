<?php

return [
    'page_title' => 'Anomalies',
    'title' => 'Anomalies',
    'description' => 'Review open data quality and reconciliation issues for the active center.',
    'center_label' => 'Showing anomalies for',
    'table_title' => 'Anomaly log',
    'table_description' => 'Filter by type, resolution status, or detection date.',
    'detail_title' => 'Anomaly detail',
    'close_detail' => 'Close detail',
    'empty' => 'No anomalies recorded for this center.',
    'view_detail' => 'View',
    'metadata_title' => 'Additional details',
    'view_import' => 'View import: :filename',
    'mark_resolved' => 'Mark as resolved',
    'resolving' => 'Resolving…',
    'types' => [
        'probable_duplicate' => 'Probable duplicate',
        'reconciliation_failure' => 'Reconciliation failure',
    ],
    'resolution' => [
        'open' => 'Open',
        'resolved' => 'Resolved',
    ],
    'filters' => [
        'all_types' => 'All types',
        'all_resolutions' => 'All statuses',
        'open' => 'Open only',
        'resolved' => 'Resolved only',
        'from' => 'From',
        'to' => 'To',
    ],
    'columns' => [
        'type' => 'Type',
        'description' => 'Description',
        'status' => 'Status',
        'detected_at' => 'Detected',
        'import' => 'Import',
        'actions' => 'Actions',
    ],
    'detail' => [
        'detected_at' => 'Detected at',
        'resolved_at' => 'Resolved at',
        'import' => 'Related import',
    ],
    'resolve' => [
        'success' => 'Anomaly marked as resolved.',
        'not_allowed' => 'You are not allowed to resolve this anomaly.',
        'already_resolved' => 'This anomaly is already resolved.',
    ],
];
