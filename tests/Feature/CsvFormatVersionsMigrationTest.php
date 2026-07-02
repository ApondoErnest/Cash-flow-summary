<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use App\Modules\CsvVerification\Models\CsvFormatVersion;
use App\Modules\CsvVerification\Models\HeaderAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('csv format versions migration creates table with data model columns', function () {
    expect(Schema::hasTable('csv_format_versions'))->toBeTrue();
    expect(Schema::hasColumns('csv_format_versions', [
        'id',
        'name',
        'code',
        'version',
        'column_count',
        'delimiter',
        'encoding',
        'is_active',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('header aliases migration creates table with data model columns', function () {
    expect(Schema::hasTable('header_aliases'))->toBeTrue();
    expect(Schema::hasColumns('header_aliases', [
        'id',
        'csv_format_version_id',
        'canonical_field',
        'language',
        'source_header',
        'normalized_header',
        'is_required',
        'is_active',
        'created_by',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('csv format version persists with csv specification defaults', function () {
    $format = CsvFormatVersion::query()->create([
        'name' => 'Cash Flow CSV v1',
        'code' => 'cashflow_csv_v1',
        'version' => '1.0.0',
    ]);

    $format->refresh();

    expect($format->column_count)->toBe(10);
    expect($format->delimiter)->toBe(';');
    expect($format->encoding)->toBe('UTF-8');
    expect($format->is_active)->toBeFalse();
});

test('header alias links to format version and optional creator', function () {
    $organization = Organization::query()->create([
        'name' => 'CSV Org',
        'code' => 'CSV-ORG',
    ]);

    $center = Center::query()->create([
        'organization_id' => $organization->id,
        'name' => 'CSV Center',
        'code' => 'CSV-CTR',
    ]);

    $owner = User::query()->create([
        'organization_id' => $organization->id,
        'center_id' => $center->id,
        'name' => 'Owner User',
        'username' => 'csv.owner',
        'password' => 'secret-password',
    ]);

    $format = CsvFormatVersion::query()->create([
        'name' => 'Cash Flow CSV v1',
        'code' => 'cashflow_csv_v1',
        'version' => '1.0.0',
        'is_active' => true,
    ]);

    $alias = HeaderAlias::query()->create([
        'csv_format_version_id' => $format->id,
        'canonical_field' => 'registration_date',
        'language' => 'fr',
        'source_header' => 'Date Enregistrement',
        'normalized_header' => 'date enregistrement',
        'created_by' => $owner->id,
    ]);

    $alias->refresh();

    expect($alias->csvFormatVersion->code)->toBe('cashflow_csv_v1');
    expect($alias->creator->username)->toBe('csv.owner');
    expect($alias->is_required)->toBeTrue();
    expect($alias->is_active)->toBeTrue();
});

test('header alias source header is unique per format version and language', function () {
    $format = CsvFormatVersion::query()->create([
        'name' => 'Cash Flow CSV v1',
        'code' => 'cashflow_csv_v1',
        'version' => '1.0.0',
    ]);

    HeaderAlias::query()->create([
        'csv_format_version_id' => $format->id,
        'canonical_field' => 'registration_date',
        'language' => 'fr',
        'source_header' => 'Date Enregistrement',
        'normalized_header' => 'date enregistrement',
    ]);

    expect(fn () => HeaderAlias::query()->create([
        'csv_format_version_id' => $format->id,
        'canonical_field' => 'registration_date',
        'language' => 'fr',
        'source_header' => 'Date Enregistrement',
        'normalized_header' => 'date enregistrement',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('csv format wave 2 migration runs after wave 1 audit logs', function () {
    $migrationFiles = collect(scandir(database_path('migrations')))
        ->filter(fn (string $file) => str_ends_with($file, '.php'))
        ->values();

    $csvFormatIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100005_create_csv_format_versions'));
    $auditIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100004_create_audit_logs_table'));

    expect($csvFormatIndex)->toBeGreaterThan($auditIndex);
});
