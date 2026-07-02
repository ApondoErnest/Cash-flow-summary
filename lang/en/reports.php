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
        'coming_soon_title' => 'Exports coming soon',
        'coming_soon_description' => 'CSV, Excel, and PDF export will be available in a future update.',
    ],
    'page' => [
        'manager' => [
            'center_label' => 'Center',
            'subtitle' => 'Review daily totals and missing submission days for :center.',
        ],
    ],
];
