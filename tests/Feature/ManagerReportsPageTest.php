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
