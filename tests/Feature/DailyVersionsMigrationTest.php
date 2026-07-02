<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use App\Modules\CsvImports\Enums\CompletionStatus;
use App\Modules\CsvImports\Enums\DayComparisonResult;
use App\Modules\CsvImports\Enums\FinancialStatus;
use App\Modules\CsvImports\Enums\ImportRowStatus;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportDayComparison;
use App\Modules\CsvImports\Models\ImportRow;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\ActiveDailySnapshot;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\DailyVersions\Models\DailyVersionMembership;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('daily versions migration creates tables with data model columns', function () {
    expect(Schema::hasTable('daily_versions'))->toBeTrue();
    expect(Schema::hasColumns('daily_versions', [
        'id',
        'center_id',
        'business_date',
        'import_id',
        'version_number',
        'dataset_hash',
        'record_count',
        'total_ht',
        'total_vat',
        'total_ttc',
        'status',
        'previous_version_id',
        'revision_reason',
        'submitted_by',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasTable('daily_version_memberships'))->toBeTrue();
    expect(Schema::hasColumns('daily_version_memberships', [
        'id',
        'daily_version_id',
        'master_cash_flow_record_id',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasTable('active_daily_snapshots'))->toBeTrue();
    expect(Schema::hasColumns('active_daily_snapshots', [
        'id',
        'center_id',
        'business_date',
        'daily_version_id',
        'activated_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('daily version persists revision chain and approval metadata', function () {
    [$center, $import, $user, $masterRecord] = createDailyVersionFixtures();

    $versionOne = DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'import_id' => $import->id,
        'version_number' => 1,
        'dataset_hash' => hash('sha256', 'dataset-v1'),
        'record_count' => 1,
        'total_ht' => '10000.00',
        'total_vat' => '1925.00',
        'total_ttc' => '11925.00',
        'status' => DailyVersionStatus::Superseded,
        'submitted_by' => $user->id,
        'approved_by' => $user->id,
        'approved_at' => now()->subDay(),
    ]);

    $versionTwo = DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'import_id' => $import->id,
        'version_number' => 2,
        'dataset_hash' => hash('sha256', 'dataset-v2'),
        'record_count' => 1,
        'total_ht' => '12000.00',
        'total_vat' => '2310.00',
        'total_ttc' => '14310.00',
        'status' => DailyVersionStatus::Active,
        'previous_version_id' => $versionOne->id,
        'revision_reason' => 'Corrected customer totals',
        'submitted_by' => $user->id,
        'approved_by' => $user->id,
        'approved_at' => now(),
    ]);

    DailyVersionMembership::query()->create([
        'daily_version_id' => $versionTwo->id,
        'master_cash_flow_record_id' => $masterRecord->id,
    ]);

    $snapshot = ActiveDailySnapshot::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'daily_version_id' => $versionTwo->id,
        'activated_at' => now(),
    ]);

    $versionTwo->refresh();

    expect($versionTwo->center->code)->toBe('VER-CTR');
    expect($versionTwo->import->original_filename)->toBe('cashflow-june.csv');
    expect($versionTwo->previousVersion->version_number)->toBe(1);
    expect($versionTwo->status)->toBe(DailyVersionStatus::Active);
    expect($versionTwo->memberships)->toHaveCount(1);
    expect($versionTwo->memberships->first()->masterCashFlowRecord->id)->toBe($masterRecord->id);
    expect($snapshot->dailyVersion->id)->toBe($versionTwo->id);
});

test('daily versions enforce unique center business date and version number', function () {
    [$center, $import] = createDailyVersionFixtures();

    DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'import_id' => $import->id,
        'version_number' => 1,
        'dataset_hash' => hash('sha256', 'dataset-v1'),
        'record_count' => 1,
        'total_ht' => '10000.00',
        'total_vat' => '1925.00',
        'total_ttc' => '11925.00',
        'status' => DailyVersionStatus::Active,
    ]);

    expect(fn () => DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'import_id' => $import->id,
        'version_number' => 1,
        'dataset_hash' => hash('sha256', 'dataset-v1-dup'),
        'record_count' => 1,
        'total_ht' => '10000.00',
        'total_vat' => '1925.00',
        'total_ttc' => '11925.00',
        'status' => DailyVersionStatus::Proposed,
    ]))->toThrow(QueryException::class);
});

test('daily version memberships enforce unique master record per version', function () {
    [$center, $import, , $masterRecord] = createDailyVersionFixtures();

    $version = DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'import_id' => $import->id,
        'version_number' => 1,
        'dataset_hash' => hash('sha256', 'dataset-v1'),
        'record_count' => 1,
        'total_ht' => '10000.00',
        'total_vat' => '1925.00',
        'total_ttc' => '11925.00',
        'status' => DailyVersionStatus::Active,
    ]);

    DailyVersionMembership::query()->create([
        'daily_version_id' => $version->id,
        'master_cash_flow_record_id' => $masterRecord->id,
    ]);

    expect(fn () => DailyVersionMembership::query()->create([
        'daily_version_id' => $version->id,
        'master_cash_flow_record_id' => $masterRecord->id,
    ]))->toThrow(QueryException::class);
});

test('active daily snapshots enforce one active version per center and date', function () {
    [$center, $import] = createDailyVersionFixtures();

    $versionOne = DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'import_id' => $import->id,
        'version_number' => 1,
        'dataset_hash' => hash('sha256', 'dataset-v1'),
        'record_count' => 1,
        'total_ht' => '10000.00',
        'total_vat' => '1925.00',
        'total_ttc' => '11925.00',
        'status' => DailyVersionStatus::Superseded,
    ]);

    $versionTwo = DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'import_id' => $import->id,
        'version_number' => 2,
        'dataset_hash' => hash('sha256', 'dataset-v2'),
        'record_count' => 1,
        'total_ht' => '12000.00',
        'total_vat' => '2310.00',
        'total_ttc' => '14310.00',
        'status' => DailyVersionStatus::Active,
    ]);

    ActiveDailySnapshot::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'daily_version_id' => $versionOne->id,
        'activated_at' => now()->subDay(),
    ]);

    expect(fn () => ActiveDailySnapshot::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'daily_version_id' => $versionTwo->id,
        'activated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('import day comparisons can link existing and proposed daily versions', function () {
    [$center, $import] = createDailyVersionFixtures();

    $existingVersion = DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'import_id' => $import->id,
        'version_number' => 1,
        'dataset_hash' => hash('sha256', 'dataset-existing'),
        'record_count' => 1,
        'total_ht' => '10000.00',
        'total_vat' => '1925.00',
        'total_ttc' => '11925.00',
        'status' => DailyVersionStatus::Active,
    ]);

    $proposedVersion = DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'import_id' => $import->id,
        'version_number' => 2,
        'dataset_hash' => hash('sha256', 'dataset-proposed'),
        'record_count' => 1,
        'total_ht' => '12000.00',
        'total_vat' => '2310.00',
        'total_ttc' => '14310.00',
        'status' => DailyVersionStatus::Proposed,
        'previous_version_id' => $existingVersion->id,
    ]);

    $comparison = ImportDayComparison::query()->create([
        'import_id' => $import->id,
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'comparison_result' => DayComparisonResult::RevisionRequired,
        'existing_version_id' => $existingVersion->id,
        'proposed_version_id' => $proposedVersion->id,
        'existing_ttc' => '11925.00',
        'proposed_ttc' => '14310.00',
        'record_count_delta' => 0,
    ]);

    expect($comparison->existingVersion->version_number)->toBe(1);
    expect($comparison->proposedVersion->status)->toBe(DailyVersionStatus::Proposed);
});

test('daily versions migration runs after import day comparisons', function () {
    $migrationFiles = collect(scandir(database_path('migrations')))
        ->filter(fn (string $file) => str_ends_with($file, '.php'))
        ->values();

    $dailyVersionsIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100011_create_daily_versions_memberships'));
    $comparisonIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100010_create_import_day_comparisons'));

    expect($dailyVersionsIndex)->toBeGreaterThan($comparisonIndex);
});

/**
 * @return array{0: Center, 1: Import, 2: User, 3: MasterCashFlowRecord}
 */
function createDailyVersionFixtures(): array
{
    $organization = Organization::query()->create([
        'name' => 'Version Org',
        'code' => 'VER-ORG-'.uniqid(),
    ]);

    $center = Center::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Version Center',
        'code' => 'VER-CTR',
    ]);

    $user = User::query()->create([
        'organization_id' => $organization->id,
        'center_id' => $center->id,
        'name' => 'Version User',
        'username' => 'version.user.'.uniqid(),
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
        'source_row_number' => 1,
        'business_date' => '2024-06-15',
        'original_values' => ['licence_plate' => 'LT-123-AB'],
        'canonical_values' => ['licence_plate' => 'LT123AB'],
        'raw_row_checksum' => hash('sha256', 'row-'.uniqid()),
        'exact_canonical_hash' => hash('sha256', 'canonical-'.uniqid()),
        'row_status' => ImportRowStatus::Accepted,
    ]);

    $masterRecord = MasterCashFlowRecord::query()->create([
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
        'exact_canonical_hash' => hash('sha256', 'master-'.uniqid()),
        'normalization_policy_version' => 'field_specific_v1',
        'first_import_id' => $import->id,
        'first_import_row_id' => $importRow->id,
        'first_seen_at' => now(),
    ]);

    return [$center, $import, $user, $masterRecord];
}
