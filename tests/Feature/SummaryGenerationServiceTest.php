<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\DailyVersions\Services\RevisionService;
use App\Modules\Reports\Jobs\GenerateDailySummaryJob;
use App\Modules\Reports\Models\DailySummary;
use App\Modules\Reports\Models\SummaryBreakdown;
use App\Modules\Reports\Services\SummaryGenerationService;
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

test('summary generation service builds daily summary and breakdowns from active snapshot', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    $summary = app(SummaryGenerationService::class)->regenerate($import->center_id, '2026-06-01');

    expect($summary)->not->toBeNull();
    expect($summary->record_count)->toBe(1);
    expect($summary->total_ht)->toBe('10000.00');
    expect($summary->total_vat)->toBe('1925.00');
    expect($summary->total_ttc)->toBe('11925.00');
    expect($summary->generated_at)->not->toBeNull();

    $breakdowns = SummaryBreakdown::query()->where('daily_summary_id', $summary->id)->get();
    expect($breakdowns)->toHaveCount(2);
    expect($breakdowns->pluck('breakdown_key')->sort()->values()->all())->toBe([
        'category_code',
        'inspection_type_code',
    ]);
    expect($breakdowns->firstWhere('breakdown_key', 'category_code')?->breakdown_value)->toBe('VL');
    expect($breakdowns->firstWhere('breakdown_key', 'inspection_type_code')?->breakdown_value)->toBe('C');
});

test('summary generation service replaces existing cache row on regeneration', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);
    $service = app(SummaryGenerationService::class);

    $first = $service->regenerate($import->center_id, '2026-06-01');
    $second = $service->regenerate($import->center_id, '2026-06-01');

    expect($second?->id)->toBe($first?->id);
    expect(DailySummary::query()->where('center_id', $import->center_id)->count())->toBe(1);
    expect(SummaryBreakdown::query()->where('daily_summary_id', $first?->id)->count())->toBe(2);
});

test('summary generation service returns null when no active snapshot exists', function () {
    $summary = app(SummaryGenerationService::class)->regenerate(999, '2026-06-01');

    expect($summary)->toBeNull();
});

test('generate daily summary job delegates to summary generation service', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    (new GenerateDailySummaryJob($import->center_id, '2026-06-01'))
        ->handle(
            app(SummaryGenerationService::class),
            app(\App\Support\Center\JobCenterContextService::class),
        );

    expect(DailySummary::query()
        ->withoutCenterScope()
        ->where('center_id', $import->center_id)
        ->whereDate('business_date', '2026-06-01')
        ->exists())->toBeTrue();
});

test('import commit dispatches summary generation for activated days', function () {
    Queue::fake();

    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    Queue::assertPushed(GenerateDailySummaryJob::class, function (GenerateDailySummaryJob $job) use ($import): bool {
        return $job->centerId === $import->center_id
            && $job->businessDate === '2026-06-01';
    });
});

test('owner approval dispatches summary regeneration for revised day', function () {
    Queue::fake();

    [, $proposed, , $owner] = revisionApprovalFixture();

    app(RevisionService::class)->approve($owner, $proposed);

    Queue::assertPushed(GenerateDailySummaryJob::class, function (GenerateDailySummaryJob $job) use ($proposed): bool {
        return $job->centerId === $proposed->center_id
            && $job->businessDate === '2026-06-01';
    });
});
