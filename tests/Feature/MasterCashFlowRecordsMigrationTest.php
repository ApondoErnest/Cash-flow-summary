<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use App\Modules\CsvImports\Enums\CompletionStatus;
use App\Modules\CsvImports\Enums\FinancialStatus;
use App\Modules\CsvImports\Enums\ImportRowStatus;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportRow;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('master cash flow records migration creates table with data model columns', function () {
    expect(Schema::hasTable('master_cash_flow_records'))->toBeTrue();
    expect(Schema::hasColumns('master_cash_flow_records', [
        'id',
        'center_id',
        'registration_date',
        'registration_time',
        'completion_date',
        'customer_name',
        'customer_name_normalized',
        'category_code',
        'inspection_type_code',
        'licence_plate',
        'licence_plate_normalized',
        'net_amount',
        'vat_amount',
        'gross_amount',
        'completion_status',
        'financial_status',
        'exact_canonical_hash',
        'normalization_policy_version',
        'first_import_id',
        'first_import_row_id',
        'first_seen_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('master cash flow record persists ledger fields and provenance', function () {
    [$center, $import, $importRow] = createMasterRecordFixtures();

    $record = MasterCashFlowRecord::query()->create([
        'center_id' => $center->id,
        'registration_date' => '2024-06-15',
        'registration_time' => '09:30:00',
        'completion_date' => '2024-06-16',
        'customer_name' => 'Jean Dupont',
        'customer_name_normalized' => 'jean dupont',
        'category_code' => 'CAT1',
        'inspection_type_code' => 'VIS',
        'licence_plate' => 'LT-123-AB',
        'licence_plate_normalized' => 'LT123AB',
        'net_amount' => '10000.00',
        'vat_amount' => '1925.00',
        'gross_amount' => '11925.00',
        'completion_status' => CompletionStatus::Completed,
        'financial_status' => FinancialStatus::Revenue,
        'exact_canonical_hash' => hash('sha256', 'master-record-1'),
        'normalization_policy_version' => 'field_specific_v1',
        'first_import_id' => $import->id,
        'first_import_row_id' => $importRow->id,
        'first_seen_at' => now(),
    ]);

    $record->refresh();

    expect($record->center->code)->toBe('MST-CTR');
    expect($record->firstImport->original_filename)->toBe('cashflow-june.csv');
    expect($record->firstImportRow->source_row_number)->toBe(2);
    expect($record->completion_status)->toBe(CompletionStatus::Completed);
    expect($record->financial_status)->toBe(FinancialStatus::Revenue);
    expect($record->licence_plate_normalized)->toBe('LT123AB');
});

test('master cash flow records enforce unique center policy and hash combination', function () {
    [$center, $import, $importRow] = createMasterRecordFixtures();
    $hash = hash('sha256', 'duplicate-master');

    MasterCashFlowRecord::query()->create([
        'center_id' => $center->id,
        'registration_date' => '2024-06-15',
        'registration_time' => '09:30:00',
        'customer_name' => 'Jean Dupont',
        'customer_name_normalized' => 'jean dupont',
        'category_code' => 'CAT1',
        'inspection_type_code' => 'VIS',
        'licence_plate' => 'LT-123-AB',
        'licence_plate_normalized' => 'LT123AB',
        'net_amount' => '10000.00',
        'vat_amount' => '1925.00',
        'gross_amount' => '11925.00',
        'completion_status' => CompletionStatus::Completed,
        'financial_status' => FinancialStatus::Revenue,
        'exact_canonical_hash' => $hash,
        'normalization_policy_version' => 'field_specific_v1',
        'first_import_id' => $import->id,
        'first_import_row_id' => $importRow->id,
        'first_seen_at' => now(),
    ]);

    expect(fn () => MasterCashFlowRecord::query()->create([
        'center_id' => $center->id,
        'registration_date' => '2024-06-16',
        'registration_time' => '10:00:00',
        'customer_name' => 'Jean Dupont',
        'customer_name_normalized' => 'jean dupont',
        'category_code' => 'CAT1',
        'inspection_type_code' => 'VIS',
        'licence_plate' => 'LT-123-AB',
        'licence_plate_normalized' => 'LT123AB',
        'net_amount' => '10000.00',
        'vat_amount' => '1925.00',
        'gross_amount' => '11925.00',
        'completion_status' => CompletionStatus::Completed,
        'financial_status' => FinancialStatus::Revenue,
        'exact_canonical_hash' => $hash,
        'normalization_policy_version' => 'field_specific_v1',
        'first_import_id' => $import->id,
        'first_import_row_id' => $importRow->id,
        'first_seen_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('master cash flow records allow same hash for different centers', function () {
    [$centerA, $importA, $importRowA] = createMasterRecordFixtures(['code' => 'MST-A']);
    [$centerB, $importB, $importRowB] = createMasterRecordFixtures(['code' => 'MST-B']);
    $hash = hash('sha256', 'shared-hash');

    $recordA = createMasterRecord($centerA, $importA, $importRowA, $hash);
    $recordB = createMasterRecord($centerB, $importB, $importRowB, $hash);

    expect($recordA->id)->not->toBe($recordB->id);
});

test('master cash flow records allow same hash for different normalization policies', function () {
    [$center, $import, $importRow] = createMasterRecordFixtures();
    $hash = hash('sha256', 'policy-variant-hash');

    $v1 = createMasterRecord($center, $import, $importRow, $hash, 'field_specific_v1');
    $v2 = createMasterRecord($center, $import, $importRow, $hash, 'field_specific_v2');

    expect($v1->id)->not->toBe($v2->id);
});

test('import row can link to master cash flow record', function () {
    [$center, $import, $importRow] = createMasterRecordFixtures();

    $record = createMasterRecord(
        $center,
        $import,
        $importRow,
        hash('sha256', 'linked-master'),
    );

    $importRow->update([
        'master_record_id' => $record->id,
        'row_status' => ImportRowStatus::Accepted,
    ]);

    expect($importRow->fresh()->masterRecord->id)->toBe($record->id);
    expect($record->importRows)->toHaveCount(1);
});

test('master cash flow records migration runs after imports tables', function () {
    $migrationFiles = collect(scandir(database_path('migrations')))
        ->filter(fn (string $file) => str_ends_with($file, '.php'))
        ->values();

    $masterIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100009_create_master_cash_flow_records'));
    $importsIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100008_create_imports_import_rows_and_import_errors'));

    expect($masterIndex)->toBeGreaterThan($importsIndex);
});

/**
 * @param  array<string, mixed>  $centerAttributes
 * @return array{0: Center, 1: Import, 2: ImportRow}
 */
function createMasterRecordFixtures(array $centerAttributes = []): array
{
    $organization = Organization::query()->create([
        'name' => 'Master Org',
        'code' => 'MST-ORG-'.uniqid(),
    ]);

    $center = Center::query()->create(array_merge([
        'organization_id' => $organization->id,
        'name' => 'Master Center',
        'code' => 'MST-CTR',
    ], $centerAttributes));

    $user = User::query()->create([
        'organization_id' => $organization->id,
        'center_id' => $center->id,
        'name' => 'Master User',
        'username' => 'master.user.'.uniqid(),
        'password' => 'secret-password',
    ]);

    $verification = ImportVerification::query()->create([
        'token' => (string) Str::uuid(),
        'user_id' => $user->id,
        'center_id' => $center->id,
        'import_mode' => ImportMode::Operational,
        'original_filename' => 'cashflow-june.csv',
        'temp_storage_path' => 'temp/verifications/sample.csv',
        'file_size' => 4096,
        'file_hash' => hash('sha256', 'verify-'.uniqid()),
        'status' => VerificationStatus::Ready,
        'expires_at' => now()->addHours(2),
    ]);

    $import = Import::query()->create([
        'center_id' => $center->id,
        'import_verification_id' => $verification->id,
        'uploaded_by' => $user->id,
        'import_mode' => ImportMode::Operational,
        'source_language' => 'fr',
        'original_filename' => 'cashflow-june.csv',
        'storage_path' => 'imports/'.$center->id.'/cashflow-june.csv',
        'file_hash' => hash('sha256', 'import-'.uniqid()),
        'file_size' => 4096,
        'status' => ImportStatus::Completed,
    ]);

    $importRow = ImportRow::query()->create([
        'import_id' => $import->id,
        'center_id' => $center->id,
        'source_row_number' => 2,
        'business_date' => '2024-06-15',
        'original_values' => ['licence_plate' => 'LT-123-AB'],
        'canonical_values' => ['licence_plate' => 'LT123AB'],
        'raw_row_checksum' => hash('sha256', 'row-'.uniqid()),
        'exact_canonical_hash' => hash('sha256', 'canonical-'.uniqid()),
        'row_status' => ImportRowStatus::Accepted,
    ]);

    return [$center, $import, $importRow];
}

function createMasterRecord(
    Center $center,
    Import $import,
    ImportRow $importRow,
    string $hash,
    string $policyVersion = 'field_specific_v1',
): MasterCashFlowRecord {
    return MasterCashFlowRecord::query()->create([
        'center_id' => $center->id,
        'registration_date' => '2024-06-15',
        'registration_time' => '09:30:00',
        'customer_name' => 'Jean Dupont',
        'customer_name_normalized' => 'jean dupont',
        'category_code' => 'CAT1',
        'inspection_type_code' => 'VIS',
        'licence_plate' => 'LT-123-AB',
        'licence_plate_normalized' => 'LT123AB',
        'net_amount' => '10000.00',
        'vat_amount' => '1925.00',
        'gross_amount' => '11925.00',
        'completion_status' => CompletionStatus::Completed,
        'financial_status' => FinancialStatus::Revenue,
        'exact_canonical_hash' => $hash,
        'normalization_policy_version' => $policyVersion,
        'first_import_id' => $import->id,
        'first_import_row_id' => $importRow->id,
        'first_seen_at' => now(),
    ]);
}
