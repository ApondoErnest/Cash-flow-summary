<?php

declare(strict_types=1);

use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\Dashboards\Enums\DashboardPeriod;
use App\Modules\Reports\Livewire\CenterReport;
use App\Modules\Reports\Services\ReportQueryService;
use App\Modules\Reports\Services\SummaryGenerationService;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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

function managerReportsFixture(): array
{
    Carbon::setTestNow('2026-06-01 14:30:00');

    $center = createTestCenter(attributes: ['name' => 'Manager Center']);
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($manager, $verification->fresh());
    app(SummaryGenerationService::class)->regenerate($center->id, '2026-06-01');

    return [$manager, $center];
}

test('manager reports page shows fixed center header and period totals', function () {
    [$manager, $center] = managerReportsFixture();

    $this->actingAs($manager)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee(__('reports.title'), false)
        ->assertSee(__('reports.page.manager.subtitle', ['center' => $center->name]), false)
        ->assertSee(__('reports.page.manager.center_label'), false)
        ->assertSee($center->name, false)
        ->assertSee(__('reports.stats.total_ttc'), false)
        ->assertSee('11 925,00', false)
        ->assertSee('01/06/2026', false)
        ->assertDontSee(__('reports.description'), false)
        ->assertDontSee(__('reports.export.coming_soon_title'), false);
});

test('manager can access reports page without owner active center session', function () {
    $manager = actingAsManager();

    app(ActiveCenterContextService::class)->clear();

    $this->actingAs($manager)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee($manager->center->name, false);
});

test('manager reports scope to assigned center only', function () {
    $owner = actingAsOwner();
    $assignedCenter = createTestCenter($owner->organization, ['name' => 'Assigned Center']);
    $otherCenter = createTestCenter($owner->organization, ['name' => 'Other Center']);
    $manager = actingAsManager($assignedCenter);

    Carbon::setTestNow('2026-06-01 14:30:00');

    $otherManager = actingAsManager($otherCenter);
    $verification = startVerificationFor(
        $otherManager,
        $otherCenter,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($otherManager, $verification->fresh());
    app(SummaryGenerationService::class)->regenerate($otherCenter->id, '2026-06-01');

    $this->actingAs($manager)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee(__('reports.empty'), false)
        ->assertDontSee('11 925,00', false);
});

test('manager reports period filter changes visible totals', function () {
    [$manager, $center] = managerReportsFixture();

    Livewire::actingAs($manager)
        ->test(CenterReport::class)
        ->assertSee('11 925,00', false)
        ->set('period', DashboardPeriod::Yesterday->value)
        ->assertSee(__('reports.empty'), false)
        ->assertDontSee('11 925,00', false);
});

test('cashier cannot access reports page', function () {
    actingAsCashier();

    $this->get(route('reports.index'))->assertForbidden();
});

test('owner reports page shows active center totals', function () {
    Carbon::setTestNow('2026-06-01 14:30:00');

    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization, ['name' => 'Owner Center']);
    setOwnerActiveCenter($owner, $center);

    $manager = actingAsManager($center);
    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($manager, $verification->fresh());
    app(SummaryGenerationService::class)->regenerate($center->id, '2026-06-01');

    $this->actingAs($owner)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee(__('reports.description'), false)
        ->assertSee('Owner Center', false)
        ->assertSee('11 925,00', false)
        ->assertSee(__('reports.export.coming_soon_title'), false);
});

test('owner without active center is redirected from reports page', function () {
    actingAsOwnerWithoutActiveCenter();

    $this->get(route('reports.index'))
        ->assertRedirect(route('center.select'));
});

test('owner reports scope to active center only', function () {
    Carbon::setTestNow('2026-06-01 14:30:00');

    $owner = actingAsOwner();
    $activeCenter = createTestCenter($owner->organization, ['name' => 'Active Center']);
    $otherCenter = createTestCenter($owner->organization, ['name' => 'Other Center']);
    setOwnerActiveCenter($owner, $activeCenter);

    $otherManager = actingAsManager($otherCenter);
    $verification = startVerificationFor(
        $otherManager,
        $otherCenter,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($otherManager, $verification->fresh());
    app(SummaryGenerationService::class)->regenerate($otherCenter->id, '2026-06-01');

    $this->actingAs($owner)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Active Center', false)
        ->assertSee(__('reports.empty'), false)
        ->assertDontSee('11 925,00', false);
});

test('owner reports custom period applies selected date range', function () {
    Carbon::setTestNow('2026-06-01 14:30:00');

    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization, ['name' => 'Custom Period Center']);
    setOwnerActiveCenter($owner, $center);

    $manager = actingAsManager($center);
    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($manager, $verification->fresh());
    app(SummaryGenerationService::class)->regenerate($center->id, '2026-06-01');

    Livewire::actingAs($owner)
        ->test(CenterReport::class)
        ->call('openCustomPeriodModal')
        ->set('customFromDate', '2026-06-01')
        ->set('customToDate', '2026-06-01')
        ->call('applyCustomPeriod')
        ->assertSee('11 925,00', false)
        ->assertSee('01/06/2026', false);
});

test('reports page ignores stale daily summary totals in ui', function () {
    [$manager, $center] = managerReportsFixture();

    $activeVersion = \App\Modules\DailyVersions\Models\DailyVersion::query()
        ->withoutCenterScope()
        ->where('center_id', $center->id)
        ->where('status', \App\Modules\DailyVersions\Enums\DailyVersionStatus::Active)
        ->firstOrFail();

    $supersededVersion = \App\Modules\DailyVersions\Models\DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2026-06-01',
        'version_number' => 99,
        'dataset_hash' => hash('sha256', 'superseded-inflated-dataset'),
        'record_count' => 2,
        'total_ht' => '30000.00',
        'total_vat' => '5775.00',
        'total_ttc' => '35775.00',
        'status' => \App\Modules\DailyVersions\Enums\DailyVersionStatus::Superseded,
    ]);

    \App\Modules\Reports\Models\DailySummary::query()
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

    expect(\App\Modules\DailyVersions\Models\ActiveDailySnapshot::query()
        ->withoutCenterScope()
        ->where('center_id', $center->id)
        ->whereDate('business_date', '2026-06-01')
        ->value('daily_version_id'))->toBe($activeVersion->id);

    $this->actingAs($manager)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('11 925,00', false)
        ->assertDontSee('35 775,00', false);
});

test('reports page excludes proposed revision totals until snapshot activates', function () {
    Carbon::setTestNow('2026-06-01 14:30:00');

    [, $proposed, $activeVersion, $owner] = revisionApprovalFixture();

    $proposed->forceFill([
        'record_count' => 3,
        'total_ht' => '50000.00',
        'total_vat' => '9625.00',
        'total_ttc' => '59625.00',
    ])->save();

    $this->actingAs($owner)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('11 925,00', false)
        ->assertDontSee('59 625,00', false);
});

test('reports page shows missing submission callout for period gaps', function () {
    [$manager, $center] = managerReportsFixture();

    $this->actingAs($manager)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee(__('reports.missing_submissions.description', ['period' => __('dashboard.period.month')]), false);
});

test('report query service aggregates daily summaries for period', function () {
    Carbon::setTestNow('2026-06-01 14:30:00');

    [$manager, $center] = managerReportsFixture();

    $report = app(ReportQueryService::class)->buildCenterReport(
        center: $center,
        period: DashboardPeriod::Month,
    );

    expect($report->centerName)->toBe('Manager Center')
        ->and($report->recordCount)->toBe(1)
        ->and($report->daysWithData)->toBe(1)
        ->and($report->totalTtc)->toBe('11 925,00')
        ->and($report->dailyRows)->toHaveCount(1)
        ->and($report->dailyRows[0]->businessDate)->toBe('01/06/2026');
});
