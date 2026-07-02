<?php

declare(strict_types=1);

use App\Modules\Centers\Services\ActiveCenterSwitchService;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Jobs\ProcessVerificationJob;
use App\Modules\Reports\Jobs\GenerateDailySummaryJob;
use App\Modules\Reports\Models\DailySummary;
use App\Support\Center\CenterContextResolver;
use App\Support\Center\JobCenterContextService;
use Database\Seeders\HeaderAliasSeeder;
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

test('process verification job serializes verification center id on dispatch', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    $verification = startVerificationFor($manager, $center);

    Queue::assertPushed(ProcessVerificationJob::class, function (ProcessVerificationJob $job) use ($verification): bool {
        return $job->token === $verification->token
            && $job->centerId === $verification->center_id;
    });
});

test('process verification job completes when owner active center differs from verification center', function () {
    $owner = actingAsOwner();
    $importCenter = createTestCenter($owner->organization, ['name' => 'Import Center']);
    $otherCenter = createTestCenter($owner->organization, ['name' => 'Switched Center']);

    setOwnerActiveCenter($owner, $importCenter);

    $verification = startVerificationFor(
        $owner,
        $importCenter,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    app(ActiveCenterSwitchService::class)->switch($owner, $otherCenter->id);

    expect(app(CenterContextResolver::class)->resolve($owner)?->centerId)->toBe($otherCenter->id);

    runProcessVerificationJob($verification->token, $importCenter->id);

    $verification->refresh();

    expect($verification->status)->toBe(VerificationStatus::Ready);
    expect($verification->center_id)->toBe($importCenter->id);
});

test('process verification job ignores verification when serialized center id does not match record', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $foreignCenter = createTestCenter();

    runProcessVerificationJob($verification->token, $foreignCenter->id);

    $verification->refresh();

    expect($verification->status)->toBe(VerificationStatus::Pending);
});

test('process verification job releases job center context after handle', function () {
    $owner = actingAsOwner();
    $importCenter = createTestCenter($owner->organization, ['name' => 'Import Center']);
    $otherCenter = createTestCenter($owner->organization, ['name' => 'Switched Center']);

    setOwnerActiveCenter($owner, $importCenter);

    $verification = startVerificationFor(
        $owner,
        $importCenter,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    app(ActiveCenterSwitchService::class)->switch($owner, $otherCenter->id);

    $job = new ProcessVerificationJob($verification->token, $importCenter->id);

    $job->handle(
        app(\App\Modules\CsvVerification\Services\CsvInspectionService::class),
        app(\App\Modules\CsvVerification\Services\HeaderMappingService::class),
        app(\App\Modules\CsvVerification\Services\CsvParsingService::class),
        app(\App\Modules\CsvVerification\Services\FooterReaderService::class),
        app(\App\Modules\CsvVerification\Services\ReconciliationService::class),
        app(\App\Modules\CsvVerification\Services\DuplicatePreviewService::class),
        app(JobCenterContextService::class),
    );

    expect(app(JobCenterContextService::class)->isBound())->toBeFalse();
    expect($verification->fresh()->status)->toBe(VerificationStatus::Ready);
});

test('generate daily summary job regenerates for serialized center when owner session differs', function () {
    $owner = actingAsOwner();
    $importCenter = createTestCenter($owner->organization, ['name' => 'Import Center']);
    $otherCenter = createTestCenter($owner->organization, ['name' => 'Switched Center']);

    setOwnerActiveCenter($owner, $importCenter);

    $verification = startVerificationFor(
        $owner,
        $importCenter,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    runProcessVerificationJob($verification->token, $importCenter->id);

    $import = commitVerificationFor($owner, $verification->fresh());

    app(ActiveCenterSwitchService::class)->switch($owner, $otherCenter->id);

    expect(app(CenterContextResolver::class)->resolve($owner)?->centerId)->toBe($otherCenter->id);

    (new GenerateDailySummaryJob($import->center_id, '2026-06-01'))->handle(
        app(\App\Modules\Reports\Services\SummaryGenerationService::class),
        app(JobCenterContextService::class),
    );

    expect(DailySummary::query()
        ->withoutCenterScope()
        ->where('center_id', $import->center_id)
        ->whereDate('business_date', '2026-06-01')
        ->exists())->toBeTrue();
});
