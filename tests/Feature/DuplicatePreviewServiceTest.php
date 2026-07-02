<?php

declare(strict_types=1);

use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Services\CsvInspectionService;
use App\Modules\CsvVerification\Services\CsvParsingService;
use App\Modules\CsvVerification\Services\DuplicatePreviewService;
use App\Modules\CsvVerification\Services\HeaderMappingService;
use App\Modules\Normalization\Services\NormalizationService;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->seed(HeaderAliasSeeder::class);
});

/**
 * @return array{mapping: array<string, int>, delimiter: string, path: string, center: \App\Modules\Centers\Models\Center}
 */
function duplicatePreviewFixture(string $contents): array
{
    $center = createTestCenter();
    $path = storeInspectionFixture('temp/verifications/duplicate-preview.csv', $contents);
    $inspection = app(CsvInspectionService::class)->inspect($path);
    $mapping = app(HeaderMappingService::class)->map($inspection);

    return [
        'mapping' => $mapping->mapping,
        'delimiter' => $inspection->delimiter,
        'path' => $path,
        'center' => $center,
    ];
}

test('duplicate preview counts in file exact duplicates', function () {
    $contents = verificationReadyFrenchCsv([
        completedFrenchDataRow(),
        completedFrenchDataRow(),
    ], frenchFooterLine(2, 20_000, 3_850, 23_850));

    $fixture = duplicatePreviewFixture($contents);

    $result = app(DuplicatePreviewService::class)->previewFile(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
        $fixture['center']->id,
        'field_specific_v1',
    );

    expect($result->exact)->toBe(1);
    expect($result->probable)->toBe(0);
    expect($result->newUnique)->toBe(1);
});

test('duplicate preview counts probable duplicates with different customer names', function () {
    $rowOne = completedFrenchDataRow();
    $rowTwo = implode(';', [
        '01/06/2026',
        '10:30',
        '02/06/2026',
        'Acme Sarl Ltd',
        'VL',
        'C',
        'LT-123-AB',
        '10 000',
        '1 925',
        '11 925',
    ]);

    $contents = verificationReadyFrenchCsv([$rowOne, $rowTwo], frenchFooterLine(2, 20_000, 3_850, 23_850));
    $fixture = duplicatePreviewFixture($contents);

    $result = app(DuplicatePreviewService::class)->previewFile(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
        $fixture['center']->id,
        'field_specific_v1',
    );

    expect($result->exact)->toBe(0);
    expect($result->probable)->toBe(1);
    expect($result->newUnique)->toBe(2);
});

test('duplicate preview counts historical exact duplicates against master ledger', function () {
    $center = createTestCenter();
    $contents = verificationReadyFrenchCsv([completedFrenchDataRow()]);
    $path = storeInspectionFixture('temp/verifications/historical-duplicate.csv', $contents);
    $inspection = app(CsvInspectionService::class)->inspect($path);
    $mapping = app(HeaderMappingService::class)->map($inspection);

    $row = app(CsvParsingService::class)->streamRows(
        $path,
        $inspection->delimiter,
        $mapping->mapping,
    )->current();

    $canonical = app(NormalizationService::class)->normalizeParsedRow($row);
    seedMasterLedgerExactHash($center->id, $canonical->exactCanonicalHash());

    $result = app(DuplicatePreviewService::class)->previewFile(
        $path,
        $inspection->delimiter,
        $mapping->mapping,
        $center->id,
        'field_specific_v1',
    );

    expect($result->exact)->toBe(1);
    expect($result->newUnique)->toBe(0);
});

test('duplicate preview excludes invalid rows from counts', function () {
    $contents = verificationReadyFrenchCsv([
        completedFrenchDataRow(),
        completedFrenchDataRow(net: '-100', vat: '0', ttc: '-100'),
    ], frenchFooterLine(1, 10_000, 1_925, 11_925));

    $fixture = duplicatePreviewFixture($contents);

    $result = app(DuplicatePreviewService::class)->previewFile(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
        $fixture['center']->id,
        'field_specific_v1',
    );

    expect($result->exact)->toBe(0);
    expect($result->newUnique)->toBe(1);
    expect($result->normalizedRows)->toBe(1);
});

test('process verification job stores duplicate summary and marks verification ready', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([
            completedFrenchDataRow(),
            completedFrenchDataRow(),
        ], frenchFooterLine(2, 20_000, 3_850, 23_850)),
    );

    runProcessVerificationJob($verification->token);

    $verification->refresh();

    expect($verification->status)->toBe(VerificationStatus::Ready);
    expect($verification->verified_at)->not->toBeNull();
    expect($verification->duplicate_summary)->toBe([
        'exact' => 1,
        'probable' => 0,
        'new_unique' => 1,
    ]);
    expect($verification->validation_result['duplicate_preview']['new_unique'])->toBe(1);
    expect($verification->validation_result['normalization']['normalized_rows'])->toBe(2);
});
