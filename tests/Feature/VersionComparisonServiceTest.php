<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CsvImports\Enums\DayComparisonResult;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportDayComparison;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\ActiveDailySnapshot;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\DailyVersions\Services\DailyDatasetService;
use App\Modules\DailyVersions\Services\VersionComparisonService;
use App\Modules\DailyVersions\Support\DailyDataset;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    config([
        'csv_verification.temp_disk' => 'local',
        'csv_imports.permanent_disk' => 'local',
    ]);
    test()->seed(HeaderAliasSeeder::class);
});

test('daily dataset service builds hash and totals from unique masters for a date', function () {
    [$import] = committedImportWithRows(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $dataset = app(DailyDatasetService::class)->buildFromImport($import, '2026-06-01');

    expect($dataset->recordCount)->toBe(1);
    expect($dataset->totalHt)->toBe('10000.00');
    expect($dataset->totalVat)->toBe('1925.00');
    expect($dataset->totalTtc)->toBe('11925.00');
    expect($dataset->datasetHash)->toHaveLength(64);
    expect($dataset->masterRecordIds)->toHaveCount(1);
});

test('daily dataset service deduplicates within file master references for one date', function () {
    [$import] = committedImportWithRows(
        verificationReadyFrenchCsv([
            completedFrenchDataRow(),
            completedFrenchDataRow(),
        ]),
    );

    $dataset = app(DailyDatasetService::class)->buildFromImport($import, '2026-06-01');

    expect($dataset->recordCount)->toBe(1);
    expect($dataset->totalTtc)->toBe('11925.00');
});

test('version comparison service records new day when no active snapshot exists', function () {
    [$import] = committedImportWithRows(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $comparison = ImportDayComparison::query()
        ->withoutCenterScope()
        ->where('import_id', $import->id)
        ->whereDate('business_date', '2026-06-01')
        ->first();

    expect($comparison)->not->toBeNull();
    expect($comparison->comparison_result)->toBe(DayComparisonResult::New);
    expect($comparison->existing_version_id)->toBeNull();
    expect($comparison->proposed_ttc)->toBe('11925.00');
});

test('version comparison service records unchanged when proposed dataset matches active snapshot', function () {
    [$import] = committedImportWithRows(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    activateDailyDatasetForImport($import, '2026-06-01');

    ImportDayComparison::query()->where('import_id', $import->id)->delete();

    $result = app(VersionComparisonService::class)->processImport($import);

    expect($result->unchangedDays)->toBe(1);

    $comparison = ImportDayComparison::query()
        ->where('import_id', $import->id)
        ->whereDate('business_date', '2026-06-01')
        ->first();

    expect($comparison->comparison_result)->toBe(DayComparisonResult::Unchanged);
    expect($comparison->record_count_delta)->toBe(0);
});

test('version comparison service records revision required when dataset differs from active snapshot', function () {
    [$import] = committedImportWithRows(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    activateDailyDatasetForImport($import, '2026-06-01', datasetHash: hash('sha256', 'different-dataset'));

    ImportDayComparison::query()->where('import_id', $import->id)->delete();

    $result = app(VersionComparisonService::class)->processImport($import);

    expect($result->revisionRequiredDays)->toBe(1);

    $comparison = ImportDayComparison::query()
        ->where('import_id', $import->id)
        ->whereDate('business_date', '2026-06-01')
        ->first();

    expect($comparison->comparison_result)->toBe(DayComparisonResult::RevisionRequired);
    expect($comparison->existing_version_id)->not->toBeNull();
    expect($comparison->record_count_delta)->toBe(0);
});

test('version comparison service records covered without rows for dates in period without import rows', function () {
    $contents = buildCsvFile(
        frenchCsvHeaderLine(),
        [
            frenchDataRow('01/06/2026', '10:30', '02/06/2026', 'ACME SARL', 'VL', 'C', 'LT-123-AB', '10 000', '1 925', '11 925'),
            frenchDataRow('03/06/2026', '10:30', '04/06/2026', 'ACME SARL', 'VL', 'C', 'LT-456-CD', '10 000', '1 925', '11 925'),
        ],
        frenchFooterLine(2, 20_000, 3_850, 23_850),
    );

    [$import] = committedImportWithRows($contents);

    expect(ImportDayComparison::query()->where('import_id', $import->id)->count())->toBe(3);

    $covered = ImportDayComparison::query()
        ->where('import_id', $import->id)
        ->whereDate('business_date', '2026-06-02')
        ->first();

    expect($covered->comparison_result)->toBe(DayComparisonResult::CoveredWithoutRows);
});

test('import service awaits owner approval when commit revises an active day', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2026-06-01',
        'version_number' => 1,
        'dataset_hash' => hash('sha256', 'stale-active-dataset'),
        'record_count' => 1,
        'total_ht' => '10000.00',
        'total_vat' => '1925.00',
        'total_ttc' => '11925.00',
        'status' => DailyVersionStatus::Active,
    ]);

    $activeVersion = DailyVersion::query()->where('center_id', $center->id)->firstOrFail();

    ActiveDailySnapshot::query()->create([
        'center_id' => $center->id,
        'business_date' => '2026-06-01',
        'daily_version_id' => $activeVersion->id,
        'activated_at' => now(),
    ]);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);

    $import = commitVerificationFor($manager, $verification->fresh());

    expect($import->status)->toBe(ImportStatus::AwaitingOwnerApproval);

    $comparison = ImportDayComparison::query()
        ->where('import_id', $import->id)
        ->whereDate('business_date', '2026-06-01')
        ->first();

    expect($comparison->comparison_result)->toBe(DayComparisonResult::RevisionRequired);
});
