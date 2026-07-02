<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\CsvImports\Enums\DayComparisonResult;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportDayComparison;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\ActiveDailySnapshot;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\DailyVersions\Models\DailyVersionMembership;
use App\Modules\DailyVersions\Services\RevisionService;
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

test('import commit activates daily version and memberships for new business day', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    $comparison = ImportDayComparison::query()
        ->withoutCenterScope()
        ->where('import_id', $import->id)
        ->first();

    expect($comparison->comparison_result)->toBe(DayComparisonResult::New);
    expect($comparison->proposed_version_id)->not->toBeNull();

    $version = DailyVersion::query()->findOrFail($comparison->proposed_version_id);
    expect($version->status)->toBe(DailyVersionStatus::Active);
    expect($version->version_number)->toBe(1);

    expect(ActiveDailySnapshot::query()
        ->withoutCenterScope()
        ->where('center_id', $import->center_id)
        ->whereDate('business_date', '2026-06-01')
        ->value('daily_version_id'))->toBe($version->id);

    expect(DailyVersionMembership::query()->where('daily_version_id', $version->id)->count())->toBe(1);
});

test('import commit creates proposed revision without activating snapshot when day changes', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2026-06-01',
        'version_number' => 1,
        'dataset_hash' => hash('sha256', 'stale-active-dataset'),
        'record_count' => 1,
        'total_ht' => '10000.00',
        'total_vat' => '1925.00',
        'total_ttc' => '11925.00',
        'status' => DailyVersionStatus::Active,
    ]);

    $activeVersion = DailyVersion::query()->where('center_id', $center->id)->firstOrFail();

    ActiveDailySnapshot::query()->create([
        'center_id' => $center->id,
        'business_date' => '2026-06-01',
        'daily_version_id' => $activeVersion->id,
        'activated_at' => now(),
    ]);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);

    $import = commitVerificationFor($manager, $verification->fresh());

    expect($import->status)->toBe(ImportStatus::AwaitingOwnerApproval);

    $comparison = ImportDayComparison::query()
        ->withoutCenterScope()
        ->where('import_id', $import->id)
        ->first();

    $proposed = DailyVersion::query()->findOrFail($comparison->proposed_version_id);

    expect($proposed->status)->toBe(DailyVersionStatus::Proposed);
    expect($proposed->version_number)->toBe(2);
    expect($proposed->previous_version_id)->toBe($activeVersion->id);

    expect(ActiveDailySnapshot::query()
        ->withoutCenterScope()
        ->where('center_id', $center->id)
        ->value('daily_version_id'))->toBe($activeVersion->id);
});

test('owner can approve proposed revision and supersede prior active version', function () {
    [$import, $proposed, $activeVersion, $owner] = revisionApprovalFixture();

    $approved = app(RevisionService::class)->approve($owner, $proposed);

    expect($approved->status)->toBe(DailyVersionStatus::Active);
    expect($approved->approved_by)->toBe($owner->id);

    expect($activeVersion->fresh()->status)->toBe(DailyVersionStatus::Superseded);
    expect(ActiveDailySnapshot::query()
        ->withoutCenterScope()
        ->where('center_id', $import->center_id)
        ->whereDate('business_date', '2026-06-01')
        ->value('daily_version_id'))->toBe($proposed->id);

    expect($import->fresh()->status)->toBe(ImportStatus::Completed);
    expect(AuditLog::query()->where('event', 'revision.approved')->exists())->toBeTrue();
});

test('owner can reject proposed revision and complete import when no pending revisions remain', function () {
    [$import, $proposed, , $owner] = revisionApprovalFixture();

    $rejected = app(RevisionService::class)->reject($owner, $proposed, 'Totals do not match bank deposit');

    expect($rejected->status)->toBe(DailyVersionStatus::Rejected);
    expect($rejected->rejected_reason)->toBe('Totals do not match bank deposit');
    expect($import->fresh()->status)->toBe(ImportStatus::Completed);
    expect(AuditLog::query()->where('event', 'revision.rejected')->exists())->toBeTrue();
});

test('manager cannot approve proposed revision', function () {
    [, $proposed, , , $manager] = revisionApprovalFixture();

    expect(fn () => app(RevisionService::class)->approve($manager, $proposed))
        ->toThrow(AuthorizationException::class);
});

test('revision service rejects approval without owner active center', function () {
    [, $proposed, , $owner] = revisionApprovalFixture();

    $otherCenter = createTestCenter($owner->organization, ['code' => 'OTHER-'.uniqid()]);
    setOwnerActiveCenter($owner, $otherCenter);

    expect(fn () => app(RevisionService::class)->approve($owner, $proposed))
        ->toThrow(AuthorizationException::class);
});

test('revision service requires rejection reason', function () {
    [, $proposed, , $owner] = revisionApprovalFixture();

    expect(fn () => app(RevisionService::class)->reject($owner, $proposed, '   '))
        ->toThrow(\InvalidArgumentException::class, __('daily_versions.reject.reason_required'));
});
