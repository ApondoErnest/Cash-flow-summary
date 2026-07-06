<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CsvImports\Enums\DayComparisonResult;
use App\Modules\CsvImports\Enums\DuplicateType;
use App\Modules\CsvImports\Enums\ImportRowStatus;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportDayComparison;
use App\Modules\CsvImports\Models\ImportRow;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\ActiveDailySnapshot;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\DailyVersions\Models\DailyVersionMembership;
use App\Modules\DailyVersions\Services\DailyDatasetService;
use App\Modules\DuplicateDetection\Services\MasterLedgerService;
use App\Modules\Reports\Services\SummaryGenerationService;
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

test('financial backend gate end to end import creates masters versions and active snapshot', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    expect($import->status)->toBe(ImportStatus::Completed);
    expect($import->new_master_count)->toBe(1);
    expect(MasterCashFlowRecord::query()->where('center_id', $import->center_id)->count())->toBe(1);

    $snapshot = ActiveDailySnapshot::query()
        ->withoutCenterScope()
        ->where('center_id', $import->center_id)
        ->whereDate('business_date', '2026-06-01')
        ->first();

    expect($snapshot)->not->toBeNull();

    $version = DailyVersion::query()->findOrFail($snapshot->daily_version_id);
    expect($version->status)->toBe(DailyVersionStatus::Active);
    expect(DailyVersionMembership::query()->where('daily_version_id', $version->id)->count())->toBe(1);

    $comparison = ImportDayComparison::query()
        ->withoutCenterScope()
        ->where('import_id', $import->id)
        ->first();

    expect($comparison->comparison_result)->toBe(DayComparisonResult::New);
});

test('duplicate in file fixture commit keeps single master for identical rows', function () {
    [$verification, $manager] = readyVerificationForCommit(loadCsvFixture('duplicate_in_file.csv'));

    $import = commitVerificationFor($manager, $verification);

    expect($import->status)->toBe(ImportStatus::CompletedWithDuplicates);
    expect($import->duplicate_within_file_count)->toBe(1);
    expect($import->new_master_count)->toBe(1);
    expect(ImportRow::query()->where('import_id', $import->id)->count())->toBe(2);
    expect(ImportRow::query()
        ->where('import_id', $import->id)
        ->where('row_status', ImportRowStatus::DuplicateWithinFile)
        ->count())->toBe(1);
    expect(MasterCashFlowRecord::query()->where('center_id', $import->center_id)->count())->toBe(1);
});

test('duplicate historical fixture commit classifies row without creating new master', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);
    $contents = loadCsvFixture('duplicate_historical.csv');
    $fixture = parseCsvFixture($contents);
    $parsedRow = app(\App\Modules\CsvVerification\Services\CsvParsingService::class)
        ->streamRows($fixture['path'], $fixture['delimiter'], $fixture['mapping'])
        ->current();
    $canonical = app(\App\Modules\Normalization\Services\NormalizationService::class)->normalizeParsedRow($parsedRow);

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
    expect(MasterCashFlowRecord::query()->where('center_id', $center->id)->count())->toBe(1);

    $row = ImportRow::query()->where('import_id', $import->id)->first();
    expect($row->row_status)->toBe(ImportRowStatus::HistoricalDuplicate);
    expect($row->duplicate_type)->toBe(DuplicateType::Historical);
});

test('all duplicate fixture commit inserts no new masters when rows already exist in ledger', function () {
    [$firstVerification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    $center = $firstVerification->center;
    commitVerificationFor($manager, $firstVerification);

    $secondVerification = startVerificationFor($manager, $center, loadCsvFixture('all_duplicate.csv'));
    runProcessVerificationJob($secondVerification->token);
    $import = commitVerificationFor($manager, $secondVerification->fresh());

    expect($import->new_master_count)->toBe(0);
    expect($import->historical_duplicate_count)->toBe(2);
    expect(MasterCashFlowRecord::query()->where('center_id', $center->id)->count())->toBe(1);
});

test('parallel master ledger inserts for identical rows resolve to one master record', function () {
    $verification = runVerificationPipelineForContents(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    $user = User::query()->findOrFail($verification->user_id);
    test()->actingAs($user);

    $import = commitVerificationFor($user, $verification);
    $sourceRow = ImportRow::query()
        ->where('import_id', $import->id)
        ->orderBy('source_row_number')
        ->firstOrFail();

    MasterCashFlowRecord::query()->where('center_id', $import->center_id)->delete();
    ImportRow::query()->where('import_id', $import->id)->update([
        'row_status' => ImportRowStatus::New,
        'duplicate_type' => null,
        'duplicate_of_import_row_id' => null,
        'master_record_id' => null,
    ]);

    $parallelRow = ImportRow::query()->create([
        'import_id' => $import->id,
        'center_id' => $import->center_id,
        'source_row_number' => 99,
        'business_date' => $sourceRow->business_date,
        'original_values' => $sourceRow->original_values,
        'canonical_values' => $sourceRow->canonical_values,
        'raw_row_checksum' => hash('sha256', 'parallel-row'),
        'exact_canonical_hash' => $sourceRow->exact_canonical_hash,
        'similarity_fingerprint' => $sourceRow->similarity_fingerprint,
        'normalization_policy_version' => $sourceRow->normalization_policy_version,
        'row_status' => ImportRowStatus::New,
    ]);

    $service = app(MasterLedgerService::class);
    $masterFromFirst = $service->insertFromImportRow($sourceRow->fresh());
    $masterFromParallel = $service->insertFromImportRow($parallelRow->fresh());

    expect($masterFromFirst->id)->toBe($masterFromParallel->id);
    expect(MasterCashFlowRecord::query()->where('center_id', $import->center_id)->count())->toBe(1);
});

test('overlapping import files keep single master and do not double count revenue', function () {
    [$firstVerification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    $center = $firstVerification->center;
    $firstImport = commitVerificationFor($manager, $firstVerification);

    $secondContents = verificationReadyFrenchCsv([
        completedFrenchDataRow(),
        completedFrenchDataRow(registrationDate: '02/06/2026', completionDate: '03/06/2026'),
    ]);

    $secondVerification = startVerificationFor($manager, $center, $secondContents);
    runProcessVerificationJob($secondVerification->token);
    $secondImport = commitVerificationFor($manager, $secondVerification->fresh());

    expect(MasterCashFlowRecord::query()->where('center_id', $center->id)->count())->toBe(2);

    $dataset = app(DailyDatasetService::class)->buildFromImport($secondImport, '2026-06-01');
    expect($dataset->recordCount)->toBe(1);
    expect($dataset->totalTtc)->toBe('11925.00');

    $summary = app(SummaryGenerationService::class)->regenerate($center->id, '2026-06-01');
    expect($summary?->record_count)->toBe(1);
    expect($summary?->total_ttc)->toBe('11925.00');

    expect($firstImport->new_master_count)->toBe(1);
    expect($secondImport->historical_duplicate_count)->toBe(1);
    expect($secondImport->new_master_count)->toBe(1);
});

test('summary generation uses active snapshot memberships and excludes superseded version totals', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    $activeVersion = DailyVersion::query()
        ->withoutCenterScope()
        ->where('import_id', $import->id)
        ->where('status', DailyVersionStatus::Active)
        ->firstOrFail();

    $activeMaster = MasterCashFlowRecord::query()
        ->where('center_id', $import->center_id)
        ->firstOrFail();

    $extraContents = verificationReadyFrenchCsv([
        completedFrenchDataRow(
            registrationDate: '01/06/2026',
            completionDate: '02/06/2026',
            net: '20 000',
            vat: '3 850',
            ttc: '23 850',
        ),
    ]);
    $extraFixture = parseCsvFixture($extraContents);
    $extraParsedRow = app(\App\Modules\CsvVerification\Services\CsvParsingService::class)
        ->streamRows($extraFixture['path'], $extraFixture['delimiter'], $extraFixture['mapping'])
        ->current();
    $extraCanonical = app(\App\Modules\Normalization\Services\NormalizationService::class)
        ->normalizeParsedRow($extraParsedRow);

    $extraMaster = MasterCashFlowRecord::query()->create([
        'center_id' => $import->center_id,
        'registration_date' => $extraCanonical->canonicalFields()['registration_date'],
        'registration_time' => $extraCanonical->canonicalFields()['registration_time'] ?? '10:30:00',
        'completion_date' => $extraCanonical->canonicalFields()['completion_date'] ?? null,
        'customer_name' => (string) ($extraParsedRow->rawValues['customer_name'] ?? 'ACME SARL'),
        'customer_name_normalized' => (string) ($extraCanonical->canonicalFields()['customer_name'] ?? 'acme sarl'),
        'category_code' => (string) ($extraCanonical->canonicalFields()['category_code'] ?? 'VL'),
        'inspection_type_code' => (string) ($extraCanonical->canonicalFields()['inspection_type_code'] ?? 'C'),
        'licence_plate' => (string) ($extraParsedRow->rawValues['licence_plate'] ?? 'LT-123-AB'),
        'licence_plate_normalized' => (string) ($extraCanonical->canonicalFields()['licence_plate'] ?? 'LT123AB'),
        'net_amount' => '20000.00',
        'vat_amount' => '3850.00',
        'gross_amount' => '23850.00',
        'completion_status' => \App\Modules\CsvImports\Enums\CompletionStatus::Completed,
        'financial_status' => \App\Modules\CsvImports\Enums\FinancialStatus::Revenue,
        'exact_canonical_hash' => $extraCanonical->exactCanonicalHash(),
        'normalization_policy_version' => $extraCanonical->normalizationPolicyVersion,
        'first_import_id' => $import->id,
        'first_import_row_id' => ImportRow::query()->where('import_id', $import->id)->value('id'),
        'first_seen_at' => now(),
    ]);

    $supersededVersion = DailyVersion::query()->create([
        'center_id' => $import->center_id,
        'business_date' => '2026-06-01',
        'import_id' => $import->id,
        'version_number' => 99,
        'dataset_hash' => hash('sha256', 'superseded-inflated-dataset'),
        'record_count' => 2,
        'total_ht' => '30000.00',
        'total_vat' => '5775.00',
        'total_ttc' => '35775.00',
        'status' => DailyVersionStatus::Superseded,
    ]);

    DailyVersionMembership::query()->insert([
        [
            'daily_version_id' => $supersededVersion->id,
            'master_cash_flow_record_id' => $activeMaster->id,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'daily_version_id' => $supersededVersion->id,
            'master_cash_flow_record_id' => $extraMaster->id,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    expect(ActiveDailySnapshot::query()
        ->withoutCenterScope()
        ->where('center_id', $import->center_id)
        ->whereDate('business_date', '2026-06-01')
        ->value('daily_version_id'))->toBe($activeVersion->id);

    $summary = app(SummaryGenerationService::class)->regenerate($import->center_id, '2026-06-01');

    expect($summary?->record_count)->toBe(1);
    expect($summary?->total_ttc)->toBe('11925.00');
    expect($summary?->daily_version_id)->toBe($activeVersion->id);
});
