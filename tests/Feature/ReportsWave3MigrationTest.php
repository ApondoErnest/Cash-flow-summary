<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\Reports\Enums\ExportFormat;
use App\Modules\Reports\Enums\ExportRequestStatus;
use App\Modules\Reports\Models\Anomaly;
use App\Modules\Reports\Models\DailySummary;
use App\Modules\Reports\Models\ExportRequest;
use App\Modules\Reports\Models\SummaryBreakdown;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('anomalies migration creates table with data model columns', function () {
    expect(Schema::hasTable('anomalies'))->toBeTrue();
    expect(Schema::hasColumns('anomalies', [
        'id',
        'center_id',
        'import_id',
        'type',
        'description',
        'metadata',
        'resolved_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('daily summaries and summary breakdowns migrations create tables', function () {
    expect(Schema::hasTable('daily_summaries'))->toBeTrue();
    expect(Schema::hasColumns('daily_summaries', [
        'id',
        'center_id',
        'business_date',
        'daily_version_id',
        'record_count',
        'total_ht',
        'total_vat',
        'total_ttc',
        'generated_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasTable('summary_breakdowns'))->toBeTrue();
    expect(Schema::hasColumns('summary_breakdowns', [
        'id',
        'daily_summary_id',
        'breakdown_key',
        'breakdown_value',
        'record_count',
        'total_ht',
        'total_vat',
        'total_ttc',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('export requests migration creates table with data model columns', function () {
    expect(Schema::hasTable('export_requests'))->toBeTrue();
    expect(Schema::hasColumns('export_requests', [
        'id',
        'user_id',
        'center_id',
        'report_type',
        'filters',
        'format',
        'status',
        'storage_path',
        'expires_at',
        'completed_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('anomaly persists center import metadata and resolution state', function () {
    [$center, $import] = createReportsMigrationFixtures();

    $anomaly = Anomaly::query()->create([
        'center_id' => $center->id,
        'import_id' => $import->id,
        'type' => 'reconciliation_failure',
        'description' => 'Footer totals do not match parsed rows.',
        'metadata' => ['expected_ttc' => 119250, 'actual_ttc' => 118000],
    ]);

    $anomaly->refresh();

    expect($anomaly->center->code)->toBe('RPT-CTR');
    expect($anomaly->import->original_filename)->toBe('cashflow-june.csv');
    expect($anomaly->metadata['actual_ttc'])->toBe(118000);
    expect($anomaly->resolved_at)->toBeNull();
    expect($import->fresh()->anomalies)->toHaveCount(1);
});

test('daily summary persists cached totals and breakdown rows', function () {
    [$center, $import, $user, $dailyVersion] = createReportsMigrationFixtures(withDailyVersion: true);

    $summary = DailySummary::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'daily_version_id' => $dailyVersion->id,
        'record_count' => 12,
        'total_ht' => '100000.00',
        'total_vat' => '19250.00',
        'total_ttc' => '119250.00',
        'generated_at' => now(),
    ]);

    $breakdown = SummaryBreakdown::query()->create([
        'daily_summary_id' => $summary->id,
        'breakdown_key' => 'category_code',
        'breakdown_value' => 'B1',
        'record_count' => 5,
        'total_ht' => '50000.00',
        'total_vat' => '9625.00',
        'total_ttc' => '59625.00',
    ]);

    $summary->refresh();

    expect($summary->dailyVersion->version_number)->toBe(1);
    expect($summary->total_ttc)->toBe('119250.00');
    expect($summary->breakdowns)->toHaveCount(1);
    expect($breakdown->dailySummary->id)->toBe($summary->id);
});

test('daily summaries enforce one cache row per center and business date', function () {
    [$center, , , $dailyVersion] = createReportsMigrationFixtures(withDailyVersion: true);

    DailySummary::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'daily_version_id' => $dailyVersion->id,
        'record_count' => 1,
        'total_ht' => '10000.00',
        'total_vat' => '1925.00',
        'total_ttc' => '11925.00',
        'generated_at' => now(),
    ]);

    expect(fn () => DailySummary::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'daily_version_id' => $dailyVersion->id,
        'record_count' => 2,
        'total_ht' => '20000.00',
        'total_vat' => '3850.00',
        'total_ttc' => '23850.00',
        'generated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('summary breakdowns enforce unique dimension values per summary', function () {
    [$center, , , $dailyVersion] = createReportsMigrationFixtures(withDailyVersion: true);

    $summary = DailySummary::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'daily_version_id' => $dailyVersion->id,
        'record_count' => 1,
        'total_ht' => '10000.00',
        'total_vat' => '1925.00',
        'total_ttc' => '11925.00',
        'generated_at' => now(),
    ]);

    SummaryBreakdown::query()->create([
        'daily_summary_id' => $summary->id,
        'breakdown_key' => 'category_code',
        'breakdown_value' => 'B1',
        'record_count' => 1,
        'total_ht' => '10000.00',
        'total_vat' => '1925.00',
        'total_ttc' => '11925.00',
    ]);

    expect(fn () => SummaryBreakdown::query()->create([
        'daily_summary_id' => $summary->id,
        'breakdown_key' => 'category_code',
        'breakdown_value' => 'B1',
        'record_count' => 2,
        'total_ht' => '20000.00',
        'total_vat' => '3850.00',
        'total_ttc' => '23850.00',
    ]))->toThrow(QueryException::class);
});

test('export request persists report job metadata and completion state', function () {
    [$center, , $user] = createReportsMigrationFixtures();

    $export = ExportRequest::query()->create([
        'user_id' => $user->id,
        'center_id' => $center->id,
        'report_type' => 'monthly_summary',
        'filters' => ['month' => '2024-06'],
        'format' => ExportFormat::Xlsx,
        'status' => ExportRequestStatus::Completed,
        'storage_path' => 'exports/'.$center->id.'/monthly-2024-06.xlsx',
        'expires_at' => now()->addDay(),
        'completed_at' => now(),
    ]);

    $export->refresh();

    expect($export->user->username)->toBe($user->username);
    expect($export->center->code)->toBe('RPT-CTR');
    expect($export->format)->toBe(ExportFormat::Xlsx);
    expect($export->status)->toBe(ExportRequestStatus::Completed);
    expect($export->filters)->toBe(['month' => '2024-06']);
});

test('export request allows owner scoped export without center when nullable', function () {
    [, , $user] = createReportsMigrationFixtures();

    $export = ExportRequest::query()->create([
        'user_id' => $user->id,
        'center_id' => null,
        'report_type' => 'organization_overview',
        'filters' => ['year' => 2024],
        'format' => ExportFormat::Pdf,
        'status' => ExportRequestStatus::Pending,
    ]);

    expect($export->center_id)->toBeNull();
    expect($export->format)->toBe(ExportFormat::Pdf);
});

test('reports wave 3 migration runs after daily versions tables', function () {
    $migrationFiles = collect(scandir(database_path('migrations')))
        ->filter(fn (string $file) => str_ends_with($file, '.php'))
        ->values();

    $reportsIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100012_create_anomalies_summaries_and_export_requests'));
    $dailyVersionsIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100011_create_daily_versions_memberships'));

    expect($reportsIndex)->toBeGreaterThan($dailyVersionsIndex);
});

/**
 * @return array{0: Center, 1: Import, 2: User, 3?: DailyVersion}
 */
function createReportsMigrationFixtures(bool $withDailyVersion = false): array
{
    $organization = Organization::query()->create([
        'name' => 'Reports Org',
        'code' => 'RPT-ORG-'.uniqid(),
    ]);

    $center = Center::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Reports Center',
        'code' => 'RPT-CTR',
    ]);

    $user = User::query()->create([
        'organization_id' => $organization->id,
        'center_id' => $center->id,
        'name' => 'Reports User',
        'username' => 'reports.user.'.uniqid(),
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

    if (! $withDailyVersion) {
        return [$center, $import, $user];
    }

    $dailyVersion = DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2024-06-15',
        'import_id' => $import->id,
        'version_number' => 1,
        'dataset_hash' => hash('sha256', 'dataset-v1'),
        'record_count' => 12,
        'total_ht' => '100000.00',
        'total_vat' => '19250.00',
        'total_ttc' => '119250.00',
        'status' => DailyVersionStatus::Active,
    ]);

    return [$center, $import, $user, $dailyVersion];
}
