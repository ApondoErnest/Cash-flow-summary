<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use App\Modules\CsvImports\Enums\DuplicateType;
use App\Modules\CsvImports\Enums\ImportRowStatus;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportError;
use App\Modules\CsvImports\Models\ImportRow;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('imports migration creates table with data model columns', function () {
    expect(Schema::hasTable('imports'))->toBeTrue();
    expect(Schema::hasColumns('imports', [
        'id',
        'center_id',
        'import_verification_id',
        'uploaded_by',
        'approved_by',
        'import_mode',
        'source_language',
        'original_filename',
        'storage_path',
        'file_hash',
        'file_size',
        'encoding',
        'delimiter',
        'reported_period',
        'actual_period_start',
        'actual_period_end',
        'declared_count',
        'parsed_count',
        'invalid_count',
        'duplicate_within_file_count',
        'historical_duplicate_count',
        'new_master_count',
        'source_ht',
        'source_vat',
        'source_ttc',
        'calculated_ht',
        'calculated_vat',
        'calculated_ttc',
        'status',
        'warnings',
        'processing_started_at',
        'completed_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('import rows migration creates table with data model columns', function () {
    expect(Schema::hasTable('import_rows'))->toBeTrue();
    expect(Schema::hasColumns('import_rows', [
        'id',
        'import_id',
        'center_id',
        'source_row_number',
        'business_date',
        'original_values',
        'canonical_values',
        'raw_row_checksum',
        'exact_canonical_hash',
        'similarity_fingerprint',
        'normalization_policy_version',
        'master_record_id',
        'row_status',
        'duplicate_type',
        'duplicate_of_import_row_id',
        'validation_errors',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('import errors migration creates table with data model columns', function () {
    expect(Schema::hasTable('import_errors'))->toBeTrue();
    expect(Schema::hasColumns('import_errors', [
        'id',
        'import_id',
        'import_verification_id',
        'source_row_number',
        'field',
        'error_code',
        'original_value',
        'raw_row',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('import persists permanent file metadata and footer totals', function () {
    [$center, $user, $verification] = createImportTestFixtures();

    $import = Import::query()->create([
        'center_id' => $center->id,
        'import_verification_id' => $verification->id,
        'uploaded_by' => $user->id,
        'import_mode' => ImportMode::Operational,
        'source_language' => 'fr',
        'original_filename' => 'cashflow-june.csv',
        'storage_path' => 'imports/'.$center->id.'/cashflow-june.csv',
        'file_hash' => hash('sha256', 'committed-csv'),
        'file_size' => 4096,
        'encoding' => 'UTF-8',
        'delimiter' => ';',
        'reported_period' => '2024-06',
        'actual_period_start' => '2024-06-01',
        'actual_period_end' => '2024-06-30',
        'declared_count' => 10,
        'parsed_count' => 10,
        'invalid_count' => 0,
        'duplicate_within_file_count' => 1,
        'historical_duplicate_count' => 2,
        'new_master_count' => 7,
        'source_ht' => '100000.00',
        'source_vat' => '19250.00',
        'source_ttc' => '119250.00',
        'calculated_ht' => '100000.00',
        'calculated_vat' => '19250.00',
        'calculated_ttc' => '119250.00',
        'status' => ImportStatus::CompletedWithDuplicates,
        'warnings' => ['probable_duplicates' => 1],
        'processing_started_at' => now()->subMinute(),
        'completed_at' => now(),
    ]);

    $import->refresh();

    expect($import->center->code)->toBe('IMP-CTR');
    expect($import->uploadedBy->username)->toBe('import.user');
    expect($import->importVerification->token)->toBe($verification->token);
    expect($import->import_mode)->toBe(ImportMode::Operational);
    expect($import->status)->toBe(ImportStatus::CompletedWithDuplicates);
    expect($import->warnings)->toBe(['probable_duplicates' => 1]);
    expect($import->new_master_count)->toBe(7);
});

test('import row persists canonical values and duplicate linkage', function () {
    [$center, $user, $verification] = createImportTestFixtures();

    $import = Import::query()->create([
        'center_id' => $center->id,
        'import_verification_id' => $verification->id,
        'uploaded_by' => $user->id,
        'import_mode' => ImportMode::Operational,
        'source_language' => 'fr',
        'original_filename' => 'cashflow-june.csv',
        'storage_path' => 'imports/'.$center->id.'/cashflow-june.csv',
        'file_hash' => hash('sha256', 'committed-csv-rows'),
        'file_size' => 4096,
        'status' => ImportStatus::Processing,
    ]);

    $original = ImportRow::query()->create([
        'import_id' => $import->id,
        'center_id' => $center->id,
        'source_row_number' => 2,
        'business_date' => '2024-06-15',
        'original_values' => ['licence_plate' => 'LT-123-AB'],
        'canonical_values' => ['licence_plate' => 'LT123AB'],
        'raw_row_checksum' => hash('sha256', 'row-2'),
        'exact_canonical_hash' => hash('sha256', 'canonical-row-2'),
        'row_status' => ImportRowStatus::Accepted,
    ]);

    $duplicate = ImportRow::query()->create([
        'import_id' => $import->id,
        'center_id' => $center->id,
        'source_row_number' => 5,
        'business_date' => '2024-06-15',
        'original_values' => ['licence_plate' => 'LT-123-AB'],
        'canonical_values' => ['licence_plate' => 'LT123AB'],
        'raw_row_checksum' => hash('sha256', 'row-5'),
        'exact_canonical_hash' => hash('sha256', 'canonical-row-2'),
        'row_status' => ImportRowStatus::DuplicateWithinFile,
        'duplicate_type' => DuplicateType::WithinFile,
        'duplicate_of_import_row_id' => $original->id,
    ]);

    expect($duplicate->import->id)->toBe($import->id);
    expect($duplicate->duplicateOf->source_row_number)->toBe(2);
    expect($duplicate->row_status)->toBe(ImportRowStatus::DuplicateWithinFile);
    expect($duplicate->duplicate_type)->toBe(DuplicateType::WithinFile);
});

test('import error can be stored for verification or import context', function () {
    [$center, $user, $verification] = createImportTestFixtures();

    $verificationError = ImportError::query()->create([
        'import_verification_id' => $verification->id,
        'source_row_number' => 4,
        'field' => 'gross_amount',
        'error_code' => 'invalid_amount',
        'original_value' => 'abc',
        'raw_row' => '2024-06-15;LT-123-AB;abc',
    ]);

    $import = Import::query()->create([
        'center_id' => $center->id,
        'import_verification_id' => $verification->id,
        'uploaded_by' => $user->id,
        'import_mode' => ImportMode::Operational,
        'source_language' => 'fr',
        'original_filename' => 'cashflow-june.csv',
        'storage_path' => 'imports/'.$center->id.'/cashflow-june.csv',
        'file_hash' => hash('sha256', 'committed-csv-errors'),
        'file_size' => 4096,
        'status' => ImportStatus::Failed,
    ]);

    $importError = ImportError::query()->create([
        'import_id' => $import->id,
        'source_row_number' => 8,
        'field' => 'business_date',
        'error_code' => 'invalid_date',
        'original_value' => '31/13/2024',
    ]);

    expect($verificationError->import_id)->toBeNull();
    expect($verificationError->importVerification->token)->toBe($verification->token);
    expect($importError->import->id)->toBe($import->id);
    expect($importError->import_verification_id)->toBeNull();
});

test('imports enforce unique center and file hash combination', function () {
    [$center, $user, $verification] = createImportTestFixtures();
    $fileHash = hash('sha256', 'duplicate-file');

    Import::query()->create([
        'center_id' => $center->id,
        'import_verification_id' => $verification->id,
        'uploaded_by' => $user->id,
        'import_mode' => ImportMode::Operational,
        'source_language' => 'fr',
        'original_filename' => 'first.csv',
        'storage_path' => 'imports/'.$center->id.'/first.csv',
        'file_hash' => $fileHash,
        'file_size' => 1024,
        'status' => ImportStatus::Completed,
    ]);

    expect(fn () => Import::query()->create([
        'center_id' => $center->id,
        'import_verification_id' => $verification->id,
        'uploaded_by' => $user->id,
        'import_mode' => ImportMode::Operational,
        'source_language' => 'fr',
        'original_filename' => 'second.csv',
        'storage_path' => 'imports/'.$center->id.'/second.csv',
        'file_hash' => $fileHash,
        'file_size' => 1024,
        'status' => ImportStatus::ExactFileDuplicate,
    ]))->toThrow(QueryException::class);
});

test('import verification import id foreign key links committed import', function () {
    [$center, $user, $verification] = createImportTestFixtures();

    $import = Import::query()->create([
        'center_id' => $center->id,
        'import_verification_id' => $verification->id,
        'uploaded_by' => $user->id,
        'import_mode' => ImportMode::Operational,
        'source_language' => 'fr',
        'original_filename' => 'cashflow-june.csv',
        'storage_path' => 'imports/'.$center->id.'/cashflow-june.csv',
        'file_hash' => hash('sha256', 'linked-import'),
        'file_size' => 4096,
        'status' => ImportStatus::Completed,
    ]);

    $verification->update([
        'import_id' => $import->id,
        'status' => VerificationStatus::Imported,
        'committed_at' => now(),
    ]);

    expect($verification->fresh()->import->id)->toBe($import->id);
});

test('imports wave 3 migration runs after import verifications', function () {
    $migrationFiles = collect(scandir(database_path('migrations')))
        ->filter(fn (string $file) => str_ends_with($file, '.php'))
        ->values();

    $importsIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100008_create_imports_import_rows_and_import_errors'));
    $verificationIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100006_create_import_verifications'));

    expect($importsIndex)->toBeGreaterThan($verificationIndex);
});

/**
 * @return array{0: Center, 1: User, 2: ImportVerification}
 */
function createImportTestFixtures(): array
{
    $organization = Organization::query()->create([
        'name' => 'Import Org',
        'code' => 'IMP-ORG',
    ]);

    $center = Center::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Import Center',
        'code' => 'IMP-CTR',
    ]);

    $user = User::query()->create([
        'organization_id' => $organization->id,
        'center_id' => $center->id,
        'name' => 'Importer',
        'username' => 'import.user',
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
        'file_hash' => hash('sha256', 'verify-file'),
        'status' => VerificationStatus::Ready,
        'expires_at' => now()->addHours(2),
        'verified_at' => now(),
    ]);

    return [$center, $user, $verification];
}
