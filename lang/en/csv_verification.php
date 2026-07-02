<?php

return [
    'inspection' => [
        'unreadable_file' => 'The CSV file could not be read.',
        'invalid_encoding' => 'The file must be UTF-8 encoded.',
        'missing_bom' => 'The file must start with a UTF-8 byte order mark (BOM).',
        'missing_header_row' => 'The file does not contain a header row.',
        'invalid_delimiter' => 'The file must use a semicolon (;) as the column delimiter.',
        'invalid_column_count' => 'The header row must contain exactly :count columns.',
    ],
    'mapping' => [
        'inspection_required' => 'CSV inspection must pass before headers can be mapped.',
        'mixed_language' => 'The file mixes French and English headers. Use one language consistently.',
        'language_undetermined' => 'The file language could not be determined from the header row.',
        'unknown_headers' => 'Unknown header(s): :headers.',
        'missing_required_field' => 'Required column missing: :field.',
        'duplicate_canonical_field' => 'Duplicate mapped column for :field.',
    ],
    'parsing' => [
        'invalid_registration_date' => 'Registration date is missing or invalid.',
        'invalid_date' => 'Invalid date in :field.',
        'invalid_time' => 'Invalid time in :field.',
        'invalid_amount' => 'Invalid amount in :field.',
        'negative_amount' => 'Negative amount in :field.',
        'amount_mismatch' => 'Row amounts do not satisfy HT + VAT = TTC.',
    ],
    'footer' => [
        'missing' => 'The file does not contain a footer summary row.',
        'invalid' => 'The footer summary row could not be parsed.',
    ],
    'reconciliation' => [
        'count_mismatch' => 'Record count does not match the footer (footer: :footer, parsed: :parsed).',
        'ht_mismatch' => 'Amount excluding VAT does not match the footer (footer: :footer, parsed: :parsed).',
        'vat_mismatch' => 'VAT amount does not match the footer (footer: :footer, parsed: :parsed).',
        'ttc_mismatch' => 'Total including VAT does not match the footer (footer: :footer, parsed: :parsed).',
    ],
    'verification' => [
        'expired' => 'This verification has expired. Upload the file again.',
        'reject_not_allowed' => 'This verification cannot be rejected in its current state.',
    ],
];
