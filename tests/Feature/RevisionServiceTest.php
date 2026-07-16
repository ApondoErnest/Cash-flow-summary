<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\CsvImports\Enums\DayComparisonResult;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportDayComparison;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\ActiveDailySnapshot;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\DailyVersions\Models\DailyVersionMembership;
use App\Modules\DailyVersions\Services\RevisionService;
use App\Modules\DailyVersions\Support\RevisionActivationPolicy;
use App\Modules\CsvVerification\Enums\ImportMode;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

test('import commit creates proposed revision without activating snapshot when import is on a later calendar day', function () {
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

test('revision activation policy auto activates operational imports on same center calendar day', function () {
    $center = createTestCenter();
    $import = new Import([
        'center_id' => $center->id,
        'import_mode' => ImportMode::Operational,
    ]);
    $import->setRelation('center', $center->load('organization'));

    $policy = app(RevisionActivationPolicy::class);
    $moment = Carbon::parse('2026-07-15 18:00:00', 'Africa/Douala');

    expect($policy->shouldAutoActivateRevision($import, '2026-07-15', $moment))->toBeTrue()
        ->and($policy->shouldAutoActivateRevision($import, '2026-07-14', $moment))->toBeFalse();
});

test('revision activation policy never auto activates correction imports', function () {
    $center = createTestCenter();
    $import = new Import([
        'center_id' => $center->id,
        'import_mode' => ImportMode::Correction,
    ]);
    $import->setRelation('center', $center->load('organization'));

    $moment = Carbon::parse('2026-06-01 12:00:00', 'Africa/Douala');

    expect(app(RevisionActivationPolicy::class)
        ->shouldAutoActivateRevision($import, '2026-06-01', $moment))->toBeFalse();
});

test('operational same day cumulative import auto activates revised snapshot', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 14:00:00', 'Africa/Douala'));

    try {
        $center = createTestCenter();
        $manager = actingAsManager($center);

        $firstVerification = startVerificationFor(
            $manager,
            $center,
            verificationReadyFrenchCsv([
                completedFrenchDataRow(registrationDate: '15/07/2026', completionDate: '15/07/2026'),
            ]),
        );
        runProcessVerificationJob($firstVerification->token);
        commitVerificationFor($manager, $firstVerification->fresh());

        $secondVerification = startVerificationFor(
            $manager,
            $center,
            reconciledFrenchCsv([
                completedFrenchDataRow(registrationDate: '15/07/2026', completionDate: '15/07/2026'),
                completedFrenchDataRow(
                    registrationDate: '15/07/2026',
                    completionDate: '15/07/2026',
                    net: '20 000',
                    vat: '3 850',
                    ttc: '23 850',
                ),
            ]),
        );
        runProcessVerificationJob($secondVerification->token);
        $secondImport = commitVerificationFor($manager, $secondVerification->fresh());

        expect($secondImport->status)->toBe(ImportStatus::CompletedWithDuplicates)
            ->and($secondImport->historical_duplicate_count)->toBe(1)
            ->and($secondImport->new_master_count)->toBe(1)
            ->and(MasterCashFlowRecord::query()->where('center_id', $center->id)->count())->toBe(2);

        $comparison = ImportDayComparison::query()
            ->withoutCenterScope()
            ->where('import_id', $secondImport->id)
            ->firstOrFail();

        $version = DailyVersion::query()->findOrFail($comparison->proposed_version_id);

        expect($comparison->comparison_result)->toBe(DayComparisonResult::RevisionRequired)
            ->and($version->status)->toBe(DailyVersionStatus::Active)
            ->and($version->version_number)->toBe(2)
            ->and($version->record_count)->toBe(2)
            ->and(DailyVersion::query()
                ->withoutCenterScope()
                ->where('import_id', $secondImport->id)
                ->where('status', DailyVersionStatus::Proposed)
                ->count())->toBe(0)
            ->and(ActiveDailySnapshot::query()
                ->withoutCenterScope()
                ->where('center_id', $center->id)
                ->whereDate('business_date', '2026-07-15')
                ->value('daily_version_id'))->toBe($version->id);
    } finally {
        Carbon::setTestNow();
    }
});

test('operational next day cumulative import requires owner approval', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    Carbon::setTestNow(Carbon::parse('2026-07-15 14:00:00', 'Africa/Douala'));

    try {
        $firstVerification = startVerificationFor(
            $manager,
            $center,
            verificationReadyFrenchCsv([
                completedFrenchDataRow(registrationDate: '15/07/2026', completionDate: '15/07/2026'),
            ]),
        );
        runProcessVerificationJob($firstVerification->token);
        $firstImport = commitVerificationFor($manager, $firstVerification->fresh());

        expect($firstImport->status)->toBe(ImportStatus::Completed);

        $firstActiveId = ActiveDailySnapshot::query()
            ->withoutCenterScope()
            ->where('center_id', $center->id)
            ->whereDate('business_date', '2026-07-15')
            ->value('daily_version_id');

        Carbon::setTestNow(Carbon::parse('2026-07-16 10:00:00', 'Africa/Douala'));

        $secondVerification = startVerificationFor(
            $manager,
            $center,
            reconciledFrenchCsv([
                completedFrenchDataRow(registrationDate: '15/07/2026', completionDate: '15/07/2026'),
                completedFrenchDataRow(
                    registrationDate: '15/07/2026',
                    completionDate: '15/07/2026',
                    net: '20 000',
                    vat: '3 850',
                    ttc: '23 850',
                ),
            ]),
        );
        runProcessVerificationJob($secondVerification->token);
        $secondImport = commitVerificationFor($manager, $secondVerification->fresh());

        expect($secondImport->status)->toBe(ImportStatus::AwaitingOwnerApproval)
            ->and(MasterCashFlowRecord::query()->where('center_id', $center->id)->count())->toBe(2);

        $proposed = DailyVersion::query()
            ->withoutCenterScope()
            ->where('import_id', $secondImport->id)
            ->firstOrFail();

        expect($proposed->status)->toBe(DailyVersionStatus::Proposed)
            ->and($proposed->record_count)->toBe(2)
            ->and(ActiveDailySnapshot::query()
                ->withoutCenterScope()
                ->where('center_id', $center->id)
                ->whereDate('business_date', '2026-07-15')
                ->value('daily_version_id'))->toBe($firstActiveId);
    } finally {
        Carbon::setTestNow();
    }
});

test('historical same day cumulative import also auto activates', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 16:00:00', 'Africa/Douala'));

    try {
        $center = createTestCenter();
        $manager = actingAsManager($center);

        $firstVerification = startVerificationFor(
            $manager,
            $center,
            verificationReadyFrenchCsv([
                completedFrenchDataRow(registrationDate: '15/07/2026', completionDate: '15/07/2026'),
            ]),
            importMode: ImportMode::Historical,
        );
        runProcessVerificationJob($firstVerification->token);
        commitVerificationFor($manager, $firstVerification->fresh());

        $secondVerification = startVerificationFor(
            $manager,
            $center,
            reconciledFrenchCsv([
                completedFrenchDataRow(registrationDate: '15/07/2026', completionDate: '15/07/2026'),
                completedFrenchDataRow(
                    registrationDate: '15/07/2026',
                    completionDate: '15/07/2026',
                    net: '20 000',
                    vat: '3 850',
                    ttc: '23 850',
                ),
            ]),
            importMode: ImportMode::Historical,
        );
        runProcessVerificationJob($secondVerification->token);
        $secondImport = commitVerificationFor($manager, $secondVerification->fresh());

        expect($secondImport->status)->toBe(ImportStatus::CompletedWithDuplicates);

        $version = DailyVersion::query()
            ->withoutCenterScope()
            ->where('import_id', $secondImport->id)
            ->firstOrFail();

        expect($version->status)->toBe(DailyVersionStatus::Active)
            ->and($version->record_count)->toBe(2);
    } finally {
        Carbon::setTestNow();
    }
});

test('operational same day import auto activates when existing row totals change', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 18:00:00', 'Africa/Douala'));

    try {
        $center = createTestCenter();
        $manager = actingAsManager($center);

        DailyVersion::query()->create([
            'center_id' => $center->id,
            'business_date' => '2026-07-15',
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
            'business_date' => '2026-07-15',
            'daily_version_id' => $activeVersion->id,
            'activated_at' => now(),
        ]);

        $verification = startVerificationFor(
            $manager,
            $center,
            verificationReadyFrenchCsv([
                completedFrenchDataRow(registrationDate: '15/07/2026', completionDate: '15/07/2026'),
            ]),
        );
        runProcessVerificationJob($verification->token);
        $import = commitVerificationFor($manager, $verification->fresh());

        expect($import->status)->toBe(ImportStatus::Completed);

        $proposed = DailyVersion::query()
            ->where('import_id', $import->id)
            ->orderByDesc('version_number')
            ->firstOrFail();

        expect($proposed->status)->toBe(DailyVersionStatus::Active)
            ->and($proposed->version_number)->toBe(2)
            ->and(ActiveDailySnapshot::query()
                ->withoutCenterScope()
                ->where('center_id', $center->id)
                ->value('daily_version_id'))->toBe($proposed->id);
    } finally {
        Carbon::setTestNow();
    }
});

test('correction import on same calendar day still awaits owner approval', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00', 'Africa/Douala'));

    try {
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
            importMode: ImportMode::Correction,
        );
        runProcessVerificationJob($verification->token);
        $import = commitVerificationFor($manager, $verification->fresh());

        expect($import->status)->toBe(ImportStatus::AwaitingOwnerApproval);

        $proposed = DailyVersion::query()
            ->where('import_id', $import->id)
            ->firstOrFail();

        expect($proposed->status)->toBe(DailyVersionStatus::Proposed)
            ->and(ActiveDailySnapshot::query()
                ->withoutCenterScope()
                ->where('center_id', $center->id)
                ->value('daily_version_id'))->toBe($activeVersion->id);
    } finally {
        Carbon::setTestNow();
    }
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
