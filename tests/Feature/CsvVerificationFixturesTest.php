<?php

declare(strict_types=1);

use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\Normalization\Services\NormalizationService;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    $this->seed(HeaderAliasSeeder::class);
});

/**
 * @param  list<string>  $expectedStatuses
 */
function assertFixturePipeline(string $fixture, VerificationStatus $expectedStatus, array $expectedStatuses = []): ImportVerification
{
    $verification = runVerificationPipelineForContents(loadCsvFixture($fixture));

    expect($verification->status)->toBe($expectedStatus);

    return $verification;
}

test('csv fixture catalogue files exist on disk', function () {
    $expected = [
        'sample_fr_valid.csv',
        'sample_fr_production_footer.csv',
        'sample_en_valid.csv',
        'sample_real_patterns.csv',
        'duplicate_in_file.csv',
        'duplicate_historical.csv',
        'all_duplicate.csv',
        'missing_footer.csv',
        'missing_header.csv',
        'invalid_date.csv',
        'invalid_amount.csv',
        'financial_mismatch.csv',
        'zero_value_rows.csv',
        'mixed_headers.csv',
        'probable_duplicate_customer.csv',
        'multi_day_period.csv',
    ];

    foreach ($expected as $filename) {
        expect(is_readable(csvFixturePath($filename)))->toBeTrue("Missing fixture: {$filename}");
    }
});

test('french and english valid fixtures verify to ready with production footer layout', function (string $fixture) {
    $verification = assertFixturePipeline($fixture, VerificationStatus::Ready);

    expect($verification->footer_summary)->toHaveKeys(['count', 'ht', 'vat', 'ttc']);
    expect($verification->source_language)->toBeIn(['fr', 'en']);
})->with([
    'sample_fr_valid.csv',
    'sample_fr_production_footer.csv',
    'sample_en_valid.csv',
]);

test('sample real patterns fixture verifies with expected row stats and actual period', function () {
    $verification = assertFixturePipeline('sample_real_patterns.csv', VerificationStatus::Ready);

    expect($verification->row_stats)->toBe([
        'completed' => 8,
        'unfinished' => 4,
        'zero' => 3,
        'invalid' => 0,
        'total_rows' => 12,
    ]);
    expect($verification->actual_period_start?->toDateString())->toBe('2024-01-01');
    expect($verification->actual_period_end?->toDateString())->toBe('2026-06-01');
    expect($verification->footer_summary['count'])->toBe(12);
    expect($verification->footer_summary['ttc'])->toBe(106_130);
});

test('multi day period fixture stores min and max registration dates', function () {
    $verification = assertFixturePipeline('multi_day_period.csv', VerificationStatus::Ready);

    expect($verification->actual_period_start?->toDateString())->toBe('2024-01-01');
    expect($verification->actual_period_end?->toDateString())->toBe('2024-12-31');
});

test('duplicate in file fixture reports exact duplicate preview counts', function () {
    $verification = assertFixturePipeline('duplicate_in_file.csv', VerificationStatus::Ready);

    expect($verification->duplicate_summary)->toBe([
        'exact' => 1,
        'probable' => 0,
        'new_unique' => 1,
    ]);
    expect($verification->validation_result['duplicate_preview']['normalized_rows'])->toBe(2);
});

test('duplicate historical fixture reports historical exact duplicate', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);
    $contents = loadCsvFixture('duplicate_historical.csv');
    $verification = startVerificationFor($manager, $center, $contents);

    $fixture = parseCsvFixture($contents);
    $row = app(\App\Modules\CsvVerification\Services\CsvParsingService::class)
        ->streamRows($fixture['path'], $fixture['delimiter'], $fixture['mapping'])
        ->current();
    $canonical = app(NormalizationService::class)->normalizeParsedRow($row);

    seedMasterLedgerExactHash($center->id, $canonical->exactCanonicalHash());

    runProcessVerificationJob($verification->token);

    expect($verification->fresh()->duplicate_summary['exact'])->toBe(1);
    expect($verification->fresh()->duplicate_summary['new_unique'])->toBe(0);
});

test('all duplicate fixture reports in file exact duplicate without ledger seed', function () {
    $verification = assertFixturePipeline('all_duplicate.csv', VerificationStatus::Ready);

    expect($verification->duplicate_summary)->toBe([
        'exact' => 1,
        'probable' => 0,
        'new_unique' => 1,
    ]);
});

test('all duplicate fixture reports historical duplicates when ledger is seeded', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);
    $contents = loadCsvFixture('all_duplicate.csv');
    $verification = startVerificationFor($manager, $center, $contents);

    $fixture = parseCsvFixture($contents);
    $row = app(\App\Modules\CsvVerification\Services\CsvParsingService::class)
        ->streamRows($fixture['path'], $fixture['delimiter'], $fixture['mapping'])
        ->current();
    $canonical = app(NormalizationService::class)->normalizeParsedRow($row);

    seedMasterLedgerExactHash($center->id, $canonical->exactCanonicalHash());

    runProcessVerificationJob($verification->token);

    expect($verification->fresh()->duplicate_summary)->toBe([
        'exact' => 2,
        'probable' => 0,
        'new_unique' => 0,
    ]);
});

test('probable duplicate customer fixture counts probable but not exact duplicates', function () {
    $verification = assertFixturePipeline('probable_duplicate_customer.csv', VerificationStatus::Ready);

    expect($verification->duplicate_summary['exact'])->toBe(0);
    expect($verification->duplicate_summary['probable'])->toBe(1);
    expect($verification->duplicate_summary['new_unique'])->toBe(2);
});

test('zero value rows fixture accepts completed and unfinished zero rows', function () {
    $verification = assertFixturePipeline('zero_value_rows.csv', VerificationStatus::Ready);

    expect($verification->row_stats['zero'])->toBe(1);
    expect($verification->row_stats['unfinished'])->toBe(1);
});

test('invalid amount fixture hard-fails verification', function () {
    $verification = assertFixturePipeline('invalid_amount.csv', VerificationStatus::Failed);

    expect($verification->row_stats['invalid'])->toBe(1)
        ->and($verification->row_stats['completed'])->toBe(1)
        ->and($verification->error_message)->toContain('1');
});

test('failure fixtures reject verification pipeline', function (string $fixture) {
    $verification = runVerificationPipelineForContents(loadCsvFixture($fixture));

    expect($verification->status)->toBe(VerificationStatus::Failed);
})->with([
    'missing_footer.csv',
    'missing_header.csv',
    'mixed_headers.csv',
    'financial_mismatch.csv',
    'invalid_amount.csv',
    'invalid_date.csv',
]);

test('invalid date fixture hard-fails verification', function () {
    $verification = runVerificationPipelineForContents(loadCsvFixture('invalid_date.csv'));

    expect($verification->status)->toBe(VerificationStatus::Failed)
        ->and($verification->row_stats['invalid'])->toBe(1)
        ->and($verification->row_stats['completed'])->toBe(1);
});

test('csv verification streaming handles five hundred row file within reasonable time', function () {
    $rows = [];

    for ($index = 0; $index < 500; $index++) {
        $day = str_pad((string) (($index % 28) + 1), 2, '0', STR_PAD_LEFT);
        $rows[] = completedFrenchDataRow(
            registrationDate: "{$day}/06/2026",
            completionDate: "{$day}/06/2026",
        );
    }

    $startedAt = microtime(true);
    $verification = runVerificationPipelineForContents(reconciledFrenchCsv($rows));
    $elapsedMs = (microtime(true) - $startedAt) * 1000;

    expect($verification->status)->toBe(VerificationStatus::Ready);
    expect($verification->footer_summary['count'])->toBe(500);
    expect($elapsedMs)->toBeLessThan(5000);
});

test('catalogue fixtures verify with documented status', function (string $fixture, VerificationStatus $expectedStatus) {
    $verification = runVerificationPipelineForContents(loadCsvFixture($fixture));

    expect($verification->status)->toBe($expectedStatus);
})->with([
    'sample_fr_valid.csv' => ['sample_fr_valid.csv', VerificationStatus::Ready],
    'sample_fr_production_footer.csv' => ['sample_fr_production_footer.csv', VerificationStatus::Ready],
    'sample_en_valid.csv' => ['sample_en_valid.csv', VerificationStatus::Ready],
    'sample_real_patterns.csv' => ['sample_real_patterns.csv', VerificationStatus::Ready],
    'duplicate_in_file.csv' => ['duplicate_in_file.csv', VerificationStatus::Ready],
    'all_duplicate.csv' => ['all_duplicate.csv', VerificationStatus::Ready],
    'invalid_date.csv' => ['invalid_date.csv', VerificationStatus::Failed],
    'invalid_amount.csv' => ['invalid_amount.csv', VerificationStatus::Failed],
    'zero_value_rows.csv' => ['zero_value_rows.csv', VerificationStatus::Ready],
    'probable_duplicate_customer.csv' => ['probable_duplicate_customer.csv', VerificationStatus::Ready],
    'multi_day_period.csv' => ['multi_day_period.csv', VerificationStatus::Ready],
    'missing_footer.csv' => ['missing_footer.csv', VerificationStatus::Failed],
    'missing_header.csv' => ['missing_header.csv', VerificationStatus::Failed],
    'mixed_headers.csv' => ['mixed_headers.csv', VerificationStatus::Failed],
    'financial_mismatch.csv' => ['financial_mismatch.csv', VerificationStatus::Failed],
]);

test('production french footer line matches export shape', function () {
    expect(frenchFooterLine(8_560, 126_786_275, 24_408_050, 151_194_325))
        ->toBe(";Nombre total d'inspections :;8 560;;;;Total :;126 786 275;24 408 050;151 194 325");
});
