<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('import verifications migration creates table with data model columns', function () {
    expect(Schema::hasTable('import_verifications'))->toBeTrue();
    expect(Schema::hasColumns('import_verifications', [
        'id',
        'token',
        'user_id',
        'center_id',
        'import_mode',
        'notify_owner',
        'original_filename',
        'temp_storage_path',
        'file_size',
        'file_hash',
        'source_language',
        'encoding',
        'delimiter',
        'reported_period',
        'actual_period_start',
        'actual_period_end',
        'footer_summary',
        'validation_result',
        'row_stats',
        'duplicate_summary',
        'status',
        'error_message',
        'import_id',
        'expires_at',
        'verified_at',
        'committed_at',
        'rejected_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('import verification persists token workflow state with json summaries', function () {
    $organization = Organization::query()->create([
        'name' => 'Verify Org',
        'code' => 'VER-ORG',
    ]);

    $center = Center::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Verify Center',
        'code' => 'VER-CTR',
    ]);

    $user = User::query()->create([
        'organization_id' => $organization->id,
        'center_id' => $center->id,
        'name' => 'Verifier',
        'username' => 'verify.user',
        'password' => 'secret-password',
    ]);

    $token = (string) Str::uuid();

    $verification = ImportVerification::query()->create([
        'token' => $token,
        'user_id' => $user->id,
        'center_id' => $center->id,
        'import_mode' => ImportMode::Operational,
        'original_filename' => 'cashflow-june.csv',
        'temp_storage_path' => 'temp/verifications/'.$token.'.csv',
        'file_size' => 2048,
        'file_hash' => hash('sha256', 'sample-csv'),
        'source_language' => 'fr',
        'encoding' => 'UTF-8',
        'delimiter' => ';',
        'footer_summary' => ['count' => 12, 'ht' => 100000, 'vat' => 19250, 'ttc' => 119250],
        'validation_result' => ['structure_valid' => true, 'reconciled' => true],
        'row_stats' => ['completed' => 10, 'unfinished' => 1, 'zero' => 0, 'invalid' => 1],
        'duplicate_summary' => ['exact' => 0, 'probable' => 2, 'new_unique' => 10],
        'status' => VerificationStatus::Ready,
        'expires_at' => now()->addHours(2),
        'verified_at' => now(),
    ]);

    $verification->refresh();

    expect($verification->token)->toBe($token);
    expect($verification->user->username)->toBe('verify.user');
    expect($verification->center->code)->toBe('VER-CTR');
    expect($verification->import_mode)->toBe(ImportMode::Operational);
    expect($verification->status)->toBe(VerificationStatus::Ready);
    expect($verification->notify_owner)->toBeFalse();
    expect($verification->footer_summary)->toBe(['count' => 12, 'ht' => 100000, 'vat' => 19250, 'ttc' => 119250]);
    expect($verification->duplicate_summary['probable'])->toBe(2);
    expect($verification->import_id)->toBeNull();
});

test('import verification token must be unique', function () {
    $organization = Organization::query()->create([
        'name' => 'Token Org',
        'code' => 'TOK-ORG',
    ]);

    $center = Center::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Token Center',
        'code' => 'TOK-CTR',
    ]);

    $user = User::query()->create([
        'organization_id' => $organization->id,
        'center_id' => $center->id,
        'name' => 'Token User',
        'username' => 'token.user',
        'password' => 'secret-password',
    ]);

    $token = (string) Str::uuid();

    ImportVerification::query()->create([
        'token' => $token,
        'user_id' => $user->id,
        'center_id' => $center->id,
        'import_mode' => ImportMode::Historical,
        'original_filename' => 'first.csv',
        'temp_storage_path' => 'temp/verifications/first.csv',
        'file_size' => 512,
        'file_hash' => hash('sha256', 'first'),
        'expires_at' => now()->addHours(2),
    ]);

    expect(fn () => ImportVerification::query()->create([
        'token' => $token,
        'user_id' => $user->id,
        'center_id' => $center->id,
        'import_mode' => ImportMode::Historical,
        'original_filename' => 'second.csv',
        'temp_storage_path' => 'temp/verifications/second.csv',
        'file_size' => 512,
        'file_hash' => hash('sha256', 'second'),
        'expires_at' => now()->addHours(2),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('import verifications migration runs after csv format tables', function () {
    $migrationFiles = collect(scandir(database_path('migrations')))
        ->filter(fn (string $file) => str_ends_with($file, '.php'))
        ->values();

    $verificationIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100006_create_import_verifications'));
    $csvFormatIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100005_create_csv_format_versions'));

    expect($verificationIndex)->toBeGreaterThan($csvFormatIndex);
});
