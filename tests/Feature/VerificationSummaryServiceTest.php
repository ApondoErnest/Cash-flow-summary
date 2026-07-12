<?php

declare(strict_types=1);

use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\CsvVerification\Services\VerificationSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('verification summary service formats footer totals and row stats', function () {
    $center = createTestCenter(attributes: ['name' => 'Summary Center']);
    $verification = ImportVerification::query()->create([
        'token' => (string) str()->uuid(),
        'user_id' => actingAsManager($center)->id,
        'center_id' => $center->id,
        'import_mode' => 'operational',
        'notify_owner' => false,
        'original_filename' => 'cashflow-june.csv',
        'temp_storage_path' => 'temp/verifications/example.csv',
        'file_size' => 100,
        'file_hash' => hash('sha256', 'summary-test'),
        'source_language' => 'fr',
        'footer_summary' => ['count' => 1, 'ht' => 10_000, 'vat' => 1_925, 'ttc' => 11_925],
        'row_stats' => ['completed' => 1, 'unfinished' => 0, 'zero' => 0, 'invalid' => 0, 'total_rows' => 1],
        'duplicate_summary' => ['exact' => 0, 'probable' => 0, 'new_unique' => 1],
        'validation_result' => [
            'inspection' => ['valid' => true],
            'header_mapping' => ['valid' => true],
            'reconciliation' => [
                'count' => ['passed' => true],
                'ht' => ['passed' => true],
                'vat' => ['passed' => true],
                'ttc' => ['passed' => true],
            ],
        ],
        'actual_period_start' => '2026-06-01',
        'actual_period_end' => '2026-06-01',
        'status' => 'ready',
        'expires_at' => now()->addHour(),
    ]);

    $summary = app(VerificationSummaryService::class)->build($verification);

    expect($summary->centerName)->toBe('Summary Center');
    expect($summary->footerTtc)->toBe('11,925.00');
    expect($summary->revenueGenerating)->toBe(1);
    expect($summary->canImport)->toBeTrue();
    expect($summary->checks)->toHaveCount(5);
});
