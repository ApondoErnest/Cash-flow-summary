<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\CsvImports\Enums\DayComparisonResult;
use App\Modules\CsvImports\Enums\ImportRowStatus;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Exceptions\ExactFileDuplicateException;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportDayComparison;
use App\Modules\CsvImports\Models\ImportRow;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\CsvImports\Services\ImportService;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
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

test('import service commits ready verification to permanent import with rows', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    expect($verification->status)->toBe(VerificationStatus::Ready);

    $import = commitVerificationFor($manager, $verification);

    expect($import->center_id)->toBe($verification->center_id);
    expect($import->import_verification_id)->toBe($verification->id);
    expect($import->uploaded_by)->toBe($manager->id);
    expect($import->file_hash)->toBe($verification->file_hash);
    expect($import->status)->toBe(ImportStatus::Completed);
    expect($import->declared_count)->toBe(1);
    expect($import->parsed_count)->toBe(1);
    expect($import->new_master_count)->toBe(1);

    Storage::disk('local')->assertExists($import->storage_path);
    expect($import->storage_path)->toStartWith("imports/{$verification->center_id}/{$import->id}/");

    $verification->refresh();
    expect($verification->status)->toBe(VerificationStatus::Imported);
    expect($verification->import_id)->toBe($import->id);
    expect($verification->committed_at)->not->toBeNull();
    Storage::disk('local')->assertMissing($verification->temp_storage_path);

    $rows = ImportRow::query()->where('import_id', $import->id)->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->row_status)->toBe(ImportRowStatus::Accepted);
    expect($rows->first()->master_record_id)->not->toBeNull();
    expect($rows->first()->exact_canonical_hash)->toHaveLength(64);
    expect(MasterCashFlowRecord::query()->where('center_id', $import->center_id)->count())->toBe(1);

    $comparison = ImportDayComparison::query()->where('import_id', $import->id)->first();
    expect($comparison)->not->toBeNull();
    expect($comparison->comparison_result)->toBe(DayComparisonResult::New);

    expect(AuditLog::query()->where('event', 'import.created')->where('resource_id', $import->id)->exists())->toBeTrue();
});

test('import service rejects double commit of the same token', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    commitVerificationFor($manager, $verification);

    expect(fn () => commitVerificationFor($manager, $verification->fresh()))
        ->toThrow(InvalidArgumentException::class, __('csv_import.commit.already_committed'));
});

test('import service detects exact file duplicate and links verification to existing import', function () {
    $contents = verificationReadyFrenchCsv([completedFrenchDataRow()]);

    [$firstVerification, $manager] = readyVerificationForCommit($contents);
    $firstImport = commitVerificationFor($manager, $firstVerification);

    $secondVerification = startVerificationFor($manager, $firstVerification->center, $contents);
    runProcessVerificationJob($secondVerification->token);
    $secondVerification = $secondVerification->fresh();

    expect($secondVerification->status)->toBe(VerificationStatus::Ready);

    try {
        commitVerificationFor($manager, $secondVerification);
        test()->fail('Expected ExactFileDuplicateException was not thrown.');
    } catch (ExactFileDuplicateException $exception) {
        expect($exception->existingImport->id)->toBe($firstImport->id);
    }

    $secondVerification->refresh();
    expect($secondVerification->status)->toBe(VerificationStatus::Imported);
    expect($secondVerification->import_id)->toBe($firstImport->id);
    Storage::disk('local')->assertMissing($secondVerification->temp_storage_path);

    expect(Import::query()->where('center_id', $firstVerification->center_id)->count())->toBe(1);
    expect(AuditLog::query()->where('event', 'import.exact_file_duplicate')->exists())->toBeTrue();
});

test('import service requires verification owner and center authorization', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $otherCenter = createTestCenter($manager->organization, ['code' => 'OTHER-'.uniqid()]);
    $otherManager = actingAsManager($otherCenter);

    expect(fn () => commitVerificationFor($otherManager, $verification))
        ->toThrow(AuthorizationException::class);

    $owner = actingAsOwner();
    $otherOwnerCenter = createTestCenter($owner->organization, ['code' => 'OWNER-OTHER-'.uniqid()]);
    setOwnerActiveCenter($owner, $otherOwnerCenter);

    expect(fn () => commitVerificationFor($owner, $verification))
        ->toThrow(AuthorizationException::class);
});

test('import service rejects commit when verification is not ready', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);
    $verification = startVerificationFor($manager, $center);

    expect($verification->status)->toBe(VerificationStatus::Pending);

    expect(fn () => commitVerificationFor($manager, $verification))
        ->toThrow(InvalidArgumentException::class, __('csv_import.commit.not_ready'));
});

test('import service marks completed with duplicates when duplicate preview reported exact rows', function () {
    [$verification, $manager] = readyVerificationForCommit(loadCsvFixture('duplicate_in_file.csv'));

    expect($verification->duplicate_summary['exact'])->toBeGreaterThan(0);

    $import = commitVerificationFor($manager, $verification);

    expect($import->status)->toBe(ImportStatus::CompletedWithDuplicates);
    expect($import->duplicate_within_file_count)->toBe(1);
    expect($import->historical_duplicate_count)->toBe(0);
    expect($import->new_master_count)->toBe(1);
    expect(ImportRow::query()->where('import_id', $import->id)->count())->toBe(2);
    expect(ImportRow::query()->where('import_id', $import->id)->where('row_status', ImportRowStatus::DuplicateWithinFile)->count())->toBe(1);
    expect(MasterCashFlowRecord::query()->where('center_id', $import->center_id)->count())->toBe(1);
});
