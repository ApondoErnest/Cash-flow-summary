<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CsvImports\Enums\DuplicateType;
use App\Modules\CsvImports\Enums\ImportRowStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportRow;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\CsvVerification\Services\CsvParsingService;
use App\Modules\DuplicateDetection\Services\ExactDuplicateService;
use App\Modules\DuplicateDetection\Services\MasterLedgerService;
use App\Modules\DuplicateDetection\Support\ExactDuplicateKind;
use App\Modules\Normalization\Services\NormalizationService;
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
    $this->seed(HeaderAliasSeeder::class);
});

test('exact duplicate service detects within file match for identical canonical rows', function () {
    [$import, $firstRow, $secondRow] = createLedgerImportWithRows(
        verificationReadyFrenchCsv([
            completedFrenchDataRow(),
            completedFrenchDataRow(),
        ]),
    );

    $service = app(ExactDuplicateService::class);
    $firstMatch = $service->matchForImportRow($firstRow, $import->center_id, []);
    $secondMatch = $service->matchForImportRow($secondRow, $import->center_id, [
        $firstRow->exact_canonical_hash => $firstRow,
    ]);

    expect($firstMatch->kind)->toBe(ExactDuplicateKind::None);
    expect($secondMatch->kind)->toBe(ExactDuplicateKind::WithinFile);
    expect($secondMatch->withinFileSourceRow?->id)->toBe($firstRow->id);
});

test('exact duplicate service detects historical match from master ledger', function () {
    [$import, $row] = createLedgerImportWithRows(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $master = app(MasterLedgerService::class)->insertFromImportRow($row);

    $duplicateRow = ImportRow::query()->create([
        'import_id' => $import->id,
        'center_id' => $import->center_id,
        'source_row_number' => 99,
        'business_date' => $row->business_date,
        'original_values' => $row->original_values,
        'canonical_values' => $row->canonical_values,
        'raw_row_checksum' => $row->raw_row_checksum,
        'exact_canonical_hash' => $row->exact_canonical_hash,
        'similarity_fingerprint' => $row->similarity_fingerprint,
        'normalization_policy_version' => $row->normalization_policy_version,
        'row_status' => ImportRowStatus::New,
    ]);

    $match = app(ExactDuplicateService::class)->matchForImportRow(
        $duplicateRow,
        $import->center_id,
        [],
    );

    expect($match->kind)->toBe(ExactDuplicateKind::Historical);
    expect($match->masterRecord?->id)->toBe($master->id);
});

test('master ledger service inserts master for accepted row and classifies within file duplicates', function () {
    [$import, $firstRow, $secondRow] = createLedgerImportWithRows(
        verificationReadyFrenchCsv([
            completedFrenchDataRow(),
            completedFrenchDataRow(),
        ]),
    );

    $result = app(MasterLedgerService::class)->processImport($import);

    expect($result->newMasters)->toBe(1);
    expect($result->withinFileDuplicates)->toBe(1);
    expect($result->historicalDuplicates)->toBe(0);

    $firstRow->refresh();
    $secondRow->refresh();

    expect($firstRow->row_status)->toBe(ImportRowStatus::Accepted);
    expect($secondRow->row_status)->toBe(ImportRowStatus::DuplicateWithinFile);
    expect($secondRow->duplicate_type)->toBe(DuplicateType::WithinFile);
    expect($secondRow->duplicate_of_import_row_id)->toBe($firstRow->id);
    expect($secondRow->master_record_id)->toBe($firstRow->master_record_id);

    expect(MasterCashFlowRecord::query()->where('center_id', $import->center_id)->count())->toBe(1);
});

test('master ledger service classifies historical duplicates without inserting new masters', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);
    $contents = loadCsvFixture('duplicate_historical.csv');
    $fixture = parseCsvFixture($contents);
    $parsedRow = app(CsvParsingService::class)
        ->streamRows($fixture['path'], $fixture['delimiter'], $fixture['mapping'])
        ->current();
    $canonical = app(NormalizationService::class)->normalizeParsedRow($parsedRow);

    seedMasterLedgerExactHash(
        $center->id,
        $canonical->exactCanonicalHash(),
        $canonical->normalizationPolicyVersion,
        $canonical,
        $parsedRow->rawValues,
    );

    $verification = startVerificationFor($manager, $center, $contents);
    runProcessVerificationJob($verification->token);
    $import = commitVerificationFor($manager, $verification->fresh());

    expect($import->historical_duplicate_count)->toBe(1);
    expect($import->new_master_count)->toBe(0);
    expect($import->duplicate_within_file_count)->toBe(0);

    $row = ImportRow::query()->where('import_id', $import->id)->first();
    expect($row->row_status)->toBe(ImportRowStatus::HistoricalDuplicate);
    expect($row->duplicate_type)->toBe(DuplicateType::Historical);
    expect($row->master_record_id)->not->toBeNull();

    expect(MasterCashFlowRecord::query()->where('center_id', $center->id)->count())->toBe(1);
});

test('master ledger insert from import row returns existing record on unique constraint', function () {
    [$import, $row] = createLedgerImportWithRows(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $service = app(MasterLedgerService::class);
    $first = $service->insertFromImportRow($row);
    $second = $service->insertFromImportRow($row);

    expect($second->id)->toBe($first->id);
    expect(MasterCashFlowRecord::query()->where('center_id', $import->center_id)->count())->toBe(1);
});

/**
 * @return array{0: Import, 1: ImportRow, 2?: ImportRow}
 */
function createLedgerImportWithRows(string $contents): array
{
    $verification = runVerificationPipelineForContents($contents);
    $user = User::query()->findOrFail($verification->user_id);
    test()->actingAs($user);

    $import = commitVerificationFor($user, $verification);
    $rows = ImportRow::query()
        ->where('import_id', $import->id)
        ->orderBy('source_row_number')
        ->get();

    MasterCashFlowRecord::query()->where('center_id', $import->center_id)->delete();
    ImportRow::query()->where('import_id', $import->id)->update([
        'row_status' => ImportRowStatus::New,
        'duplicate_type' => null,
        'duplicate_of_import_row_id' => null,
        'master_record_id' => null,
    ]);

    $resetRows = $rows->fresh();

    if ($resetRows->count() === 1) {
        return [$import, $resetRows->first()];
    }

    return [$import, $resetRows->first(), $resetRows->last()];
}
