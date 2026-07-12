<?php

declare(strict_types=1);

use App\Modules\Dashboards\Enums\DashboardPeriod;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\ActiveDailySnapshot;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\Reports\Models\DailySummary;
use App\Modules\Reports\Services\ReportQueryService;
use App\Modules\Reports\Services\SummaryGenerationService;
use Database\Seeders\HeaderAliasSeeder;
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
        'livewire.temporary_file_upload.disk' => 'local',
    ]);
    test()->seed(HeaderAliasSeeder::class);
});

function reportQueryFixture(): array
{
    Carbon::setTestNow('2026-06-01 14:30:00');

    $center = createTestCenter(attributes: ['name' => 'Report Center']);
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($manager, $verification->fresh());
    app(SummaryGenerationService::class)->regenerate($center->id, '2026-06-01');

    return [$center, $manager];
}

test('report query service aggregates active snapshot totals for period', function () {
    [$center] = reportQueryFixture();

    $report = app(ReportQueryService::class)->buildCenterReport(
        center: $center,
        period: DashboardPeriod::Month,
    );

    expect($report->centerName)->toBe('Report Center')
        ->and($report->recordCount)->toBe(1)
        ->and($report->daysWithData)->toBe(1)
        ->and($report->totalTtc)->toBe('11,925.00')
        ->and($report->dailyRows)->toHaveCount(1)
        ->and($report->dailyRows[0]->businessDate)->toBe('01/06/2026')
        ->and($report->hasData)->toBeTrue();
});

test('report query service ignores stale daily summary linked to superseded version', function () {
    [$center] = reportQueryFixture();

    $activeVersion = DailyVersion::query()
        ->withoutCenterScope()
        ->where('center_id', $center->id)
        ->where('status', DailyVersionStatus::Active)
        ->firstOrFail();

    $supersededVersion = DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2026-06-01',
        'version_number' => 99,
        'dataset_hash' => hash('sha256', 'superseded-inflated-dataset'),
        'record_count' => 2,
        'total_ht' => '30000.00',
        'total_vat' => '5775.00',
        'total_ttc' => '35775.00',
        'status' => DailyVersionStatus::Superseded,
    ]);

    DailySummary::query()
        ->withoutCenterScope()
        ->where('center_id', $center->id)
        ->whereDate('business_date', '2026-06-01')
        ->update([
            'daily_version_id' => $supersededVersion->id,
            'record_count' => 2,
            'total_ht' => '30000.00',
            'total_vat' => '5775.00',
            'total_ttc' => '35775.00',
        ]);

    expect(ActiveDailySnapshot::query()
        ->withoutCenterScope()
        ->where('center_id', $center->id)
        ->whereDate('business_date', '2026-06-01')
        ->value('daily_version_id'))->toBe($activeVersion->id);

    $report = app(ReportQueryService::class)->buildCenterReport(
        center: $center,
        period: DashboardPeriod::Month,
    );

    expect($report->recordCount)->toBe(1)
        ->and($report->totalTtc)->toBe('11,925.00')
        ->and($report->totalHt)->toBe('10,000.00');
});

test('report query service excludes proposed revision totals until snapshot activates', function () {
    Carbon::setTestNow('2026-06-01 14:30:00');

    [, $proposed, $activeVersion, , $manager] = revisionApprovalFixture();

    $proposed->forceFill([
        'record_count' => 3,
        'total_ht' => '50000.00',
        'total_vat' => '9625.00',
        'total_ttc' => '59625.00',
    ])->save();

    expect($proposed->fresh()->status)->toBe(DailyVersionStatus::Proposed);
    expect(ActiveDailySnapshot::query()
        ->withoutCenterScope()
        ->where('center_id', $activeVersion->center_id)
        ->whereDate('business_date', '2026-06-01')
        ->value('daily_version_id'))->toBe($activeVersion->id);

    $report = app(ReportQueryService::class)->buildCenterReport(
        center: $manager->center,
        period: DashboardPeriod::Month,
    );

    expect($report->recordCount)->toBe(1)
        ->and($report->totalTtc)->toBe('11,925.00')
        ->and($report->daysWithData)->toBe(1);
});

test('report query service returns empty report when no active snapshots in period', function () {
    Carbon::setTestNow('2026-06-01 14:30:00');

    $center = createTestCenter(attributes: ['name' => 'Empty Center']);
    actingAsManager($center);

    $report = app(ReportQueryService::class)->buildCenterReport(
        center: $center,
        period: DashboardPeriod::Month,
    );

    expect($report->hasData)->toBeFalse()
        ->and($report->recordCount)->toBe(0)
        ->and($report->daysWithData)->toBe(0)
        ->and($report->dailyRows)->toBe([]);
});
