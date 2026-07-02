<?php

return [
    'page_title' => 'Records',
    'title' => 'Cash-flow records',
    'description' => 'Browse master ledger records for the active center.',
    'center_label' => 'Showing records for',
    'table_title' => 'Master ledger',
    'table_description' => 'Search by customer or licence plate, filter by status or registration date.',
    'detail_title' => 'Record detail',
    'close_detail' => 'Close detail',
    'search_placeholder' => 'Search customer or licence plate…',
    'empty' => 'No records yet — import a CSV to populate the ledger.',
    'view_detail' => 'View',
    'view_first_import' => 'View first import: :filename',
    'filters' => [
        'all_completion' => 'All completion statuses',
        'all_financial' => 'All financial statuses',
        'from' => 'From',
        'to' => 'To',
    ],
    'columns' => [
        'registration_date' => 'Registration date',
        'completion_date' => 'Completion date',
        'customer' => 'Customer',
        'licence_plate' => 'Licence plate',
        'category' => 'Category',
        'inspection_type' => 'Inspection',
        'ht' => 'Amount excluding VAT',
        'vat' => 'VAT amount',
        'ttc' => 'Total including VAT',
        'completion_status' => 'Completion',
        'financial_status' => 'Financial status',
        'first_seen_at' => 'First seen',
        'actions' => 'Actions',
    ],
    'status' => [
        'completion' => [
            'completed' => 'Completed',
            'unfinished' => 'Unfinished',
        ],
        'financial' => [
            'revenue' => 'Revenue-generating',
            'zero_value' => 'Zero-value',
        ],
    ],
    'page' => [
        'manager' => [
            'center_label' => 'Center',
            'subtitle' => 'Search master ledger records for :center.',
        ],
    ],
];
