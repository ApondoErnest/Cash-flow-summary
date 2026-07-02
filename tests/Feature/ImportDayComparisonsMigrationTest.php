<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use App\Modules\CsvImports\Enums\DayComparisonResult;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportDayComparison;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\DailyVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('import day comparisons migration creates table with data model columns', function () {
    expect(Schema::hasTable('import_day_comparisons'))->toBeTrue();
    expect(Schema::hasColumns('import_day_comparisons', [
        'id',
        'import_id',
        'center_id',
        'business_date',
        'comparison_result',
        'existing_version_id',
        'proposed_version_id',
        'existing_ht',
        'existing_vat',
        'existing_ttc',
        'proposed_ht',
        'proposed_vat',
        'proposed_ttc',
        'record_count_delta',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('import day comparison persists revision outcome with totals', function () {
    [$center, $import] = createImportDayComparisonFixtures();

    $existingVersion = DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'import_id' => $import->id,
        'version_number' => 1,
        'dataset_hash' => hash('sha256', 'existing-dataset'),
        'record_count' => 10,
        'total_ht' => '50000.00',
        'total_vat' => '9625.00',
        'total_ttc' => '59625.00',
        'status' => DailyVersionStatus::Active,
    ]);

    $proposedVersion = DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'import_id' => $import->id,
        'version_number' => 2,
        'dataset_hash' => hash('sha256', 'proposed-dataset'),
        'record_count' => 12,
        'total_ht' => '52000.00',
        'total_vat' => '10010.00',
        'total_ttc' => '62010.00',
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
        'existing_ht' => '50000.00',
        'existing_vat' => '9625.00',
        'existing_ttc' => '59625.00',
        'proposed_ht' => '52000.00',
        'proposed_vat' => '10010.00',
        'proposed_ttc' => '62010.00',
        'record_count_delta' => 2,
    ]);

    $comparison->refresh();

    expect($comparison->import->original_filename)->toBe('cashflow-june.csv');
    expect($comparison->center->code)->toBe('DAY-CTR');
    expect($comparison->comparison_result)->toBe(DayComparisonResult::RevisionRequired);
    expect($comparison->existingVersion->version_number)->toBe(1);
    expect($comparison->proposedVersion->version_number)->toBe(2);
    expect($comparison->record_count_delta)->toBe(2);
    expect($comparison->proposed_ttc)->toBe('62010.00');
});

test('import day comparison can record a new business date without version links', function () {
    [$center, $import] = createImportDayComparisonFixtures();

    $comparison = ImportDayComparison::query()->create([
        'import_id' => $import->id,
        'center_id' => $center->id,
        'business_date' => '2024-06-16',
        'comparison_result' => DayComparisonResult::New,
        'proposed_ht' => '10000.00',
        'proposed_vat' => '1925.00',
        'proposed_ttc' => '11925.00',
        'record_count_delta' => 5,
    ]);

    expect($comparison->existing_version_id)->toBeNull();
    expect($comparison->proposed_version_id)->toBeNull();
    expect($comparison->comparison_result)->toBe(DayComparisonResult::New);
});

test('import day comparisons enforce one row per import and business date', function () {
    [$center, $import] = createImportDayComparisonFixtures();

    ImportDayComparison::query()->create([
        'import_id' => $import->id,
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'comparison_result' => DayComparisonResult::Unchanged,
    ]);

    expect(fn () => ImportDayComparison::query()->create([
        'import_id' => $import->id,
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'comparison_result' => DayComparisonResult::RevisionRequired,
    ]))->toThrow(QueryException::class);
});

test('import exposes day comparison relationship', function () {
    [$center, $import] = createImportDayComparisonFixtures();

    ImportDayComparison::query()->create([
        'import_id' => $import->id,
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'comparison_result' => DayComparisonResult::CoveredWithoutRows,
    ]);

    expect($import->fresh()->dayComparisons)->toHaveCount(1)
        ->and($import->dayComparisons->first()->comparison_result)
        ->toBe(DayComparisonResult::CoveredWithoutRows);
});

test('import day comparisons migration runs after master cash flow records', function () {
    $migrationFiles = collect(scandir(database_path('migrations')))
        ->filter(fn (string $file) => str_ends_with($file, '.php'))
        ->values();

    $comparisonIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100010_create_import_day_comparisons'));
    $masterIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100009_create_master_cash_flow_records'));

    expect($comparisonIndex)->toBeGreaterThan($masterIndex);
});

/**
 * @return array{0: Center, 1: Import}
 */
function createImportDayComparisonFixtures(): array
{
    $organization = Organization::query()->create([
        'name' => 'Day Comparison Org',
        'code' => 'DAY-ORG-'.uniqid(),
    ]);

    $center = Center::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Day Comparison Center',
        'code' => 'DAY-CTR',
    ]);

    $user = User::query()->create([
        'organization_id' => $organization->id,
        'center_id' => $center->id,
        'name' => 'Comparison User',
        'username' => 'comparison.user.'.uniqid(),
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
        'status' => ImportStatus::AwaitingOwnerApproval,
    ]);

    return [$center, $import];
}
