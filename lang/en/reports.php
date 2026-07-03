<?php

return [
    'page_title' => 'Reports',
    'title' => 'Center reports',
    'description' => 'Daily totals from active snapshots for the selected center and period.',
    'center_label' => 'Showing reports for',
    'empty' => 'No report data for this period — import CSV files to build daily summaries.',
    'stats' => [
        'total_ttc' => 'Total including VAT',
        'total_ht' => 'Amount excluding VAT',
        'total_vat' => 'VAT amount',
        'record_count' => 'Records',
        'days_with_data' => '{0} No days with data|{1} :count day with data|[2,*] :count days with data',
    ],
    'table' => [
        'title' => 'Daily breakdown',
        'description' => 'Active snapshot totals for :period.',
    ],
    'columns' => [
        'business_date' => 'Business date',
        'record_count' => 'Records',
        'ht' => 'Amount excluding VAT',
        'vat' => 'VAT amount',
        'ttc' => 'Total including VAT',
    ],
    'missing_submissions' => [
        'title' => '{1} :count missing submission day|[2,*] :count missing submission days',
        'description' => 'Expected operating days in :period without an active daily snapshot.',
        'and_more' => '…and :count more',
    ],
    'export' => [
        'title' => 'Export report',
        'description' => 'Generate a file for the selected period. Downloads remain available until they expire.',
        'recent_title' => 'Recent exports',
        'queued' => ':format export queued — your file will be ready shortly.',
        'invalid_period' => 'Choose a valid custom date range before exporting.',
        'formats' => [
            'csv' => 'CSV',
            'xlsx' => 'Excel',
            'pdf' => 'PDF',
        ],
        'statuses' => [
            'pending' => 'Queued',
            'processing' => 'Generating',
            'completed' => 'Ready',
            'failed' => 'Failed',
            'expired' => 'Expired',
        ],
        'columns' => [
            'requested_at' => 'Requested',
            'format' => 'Format',
            'period' => 'Period',
            'status' => 'Status',
            'expires_at' => 'Expires',
            'actions' => 'Actions',
        ],
        'actions' => [
            'download' => 'Download',
        ],
        'file' => [
            'title' => 'Center report',
            'center' => 'Center',
            'period' => 'Period',
            'totals' => 'Totals',
            'generated_at' => 'Generated on :datetime',
        ],
    ],
    'page' => [
        'manager' => [
            'center_label' => 'Center',
            'subtitle' => 'Review daily totals and missing submission days for :center.',
        ],
    ],
];
