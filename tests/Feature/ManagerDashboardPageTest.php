<?php

declare(strict_types=1);

use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\Dashboards\Enums\DashboardTrendGranularity;
use App\Modules\Dashboards\Livewire\Dashboard;
use App\Modules\Dashboards\Services\ManagerDashboardService;
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
    ]);
    test()->seed(HeaderAliasSeeder::class);
});

test('manager dashboard shows fixed center title and primary stats', function () {
    Carbon::setTestNow('2026-06-01 14:30:00');

    $manager = actingAsManager(createTestCenter(null, ['name' => 'NACHO Douala']));
    $center = $manager->center;

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($manager, $verification->fresh());
    app(SummaryGenerationService::class)->regenerate($center->id, '2026-06-01');

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('dashboard.manager.title', ['center' => 'NACHO Douala']), false)
        ->assertSee(__('dashboard.manager.stats.today_ttc'), false)
        ->assertSee(__('dashboard.manager.stats.yesterday_ttc'), false)
        ->assertSee(__('dashboard.manager.stats.year_ttc'), false)
        ->assertSee('11 925,00', false)
        ->assertSee('cashflow-june.csv', false)
        ->assertDontSee('Cash-Flow Dashboard', false);
});

test('manager dashboard does not show owner period picker or export actions', function () {
    actingAsManager();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(__('dashboard.actions.export'), false)
        ->assertDontSee('mf-dashboard-period-filter', false);
});

test('manager dashboard shows correction pending alert for awaiting approval imports', function () {
    $manager = actingAsManager();
    $center = $manager->center;

    Import::query()->create([
        'center_id' => $center->id,
        'uploaded_by' => $manager->id,
        'import_mode' => ImportMode::Operational,
        'source_language' => 'fr',
        'original_filename' => 'pending.csv',
        'storage_path' => 'imports/'.$center->id.'/pending.csv',
        'file_hash' => hash('sha256', 'pending-manager-import'),
        'file_size' => 100,
        'status' => ImportStatus::AwaitingOwnerApproval,
    ]);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(trans_choice('dashboard.manager.alerts.correction_pending', 1, ['count' => 1]), false);
});

test('cashier dashboard shows compact stats instead of manager dashboard', function () {
    actingAsCashier();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('dashboard.cashier.submission_title'), false)
        ->assertDontSee(__('dashboard.manager.stats.week_ttc'), false)
        ->assertDontSee(__('dashboard.sections.revenue_trend'), false);
});

test('manager dashboard scopes data to assigned center only', function () {
    Carbon::setTestNow('2026-06-01 14:30:00');

    $owner = actingAsOwner();
    $assignedCenter = createTestCenter($owner->organization, ['name' => 'Assigned Center']);
    $otherCenter = createTestCenter($owner->organization, ['name' => 'Other Center']);
    $manager = actingAsManager($assignedCenter);

    $otherManager = actingAsManager($otherCenter);
    $verification = startVerificationFor(
        $otherManager,
        $otherCenter,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($otherManager, $verification->fresh());
    app(SummaryGenerationService::class)->regenerate($otherCenter->id, '2026-06-01');

    $this->actingAs($manager);

    $dashboard = app(ManagerDashboardService::class)->build(
        center: $assignedCenter,
        trendGranularity: DashboardTrendGranularity::Daily,
    );

    expect($dashboard->centerName)->toBe('Assigned Center');
    expect($dashboard->todayTtc)->toBe('0,00');
    expect($dashboard->recentImports)->toBe([]);
});

test('manager dashboard computes yesterday and year ttc from daily summaries', function () {
    Carbon::setTestNow('2026-06-02 14:30:00');

    $manager = actingAsManager();
    $center = $manager->center;

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($manager, $verification->fresh());
    app(SummaryGenerationService::class)->regenerate($center->id, '2026-06-01');

    $dashboard = app(ManagerDashboardService::class)->build(
        center: $center,
        trendGranularity: DashboardTrendGranularity::Daily,
    );

    expect($dashboard->todayTtc)->toBe('0,00');
    expect($dashboard->yesterdayTtc)->toBe('11 925,00');
    expect($dashboard->yearTtc)->toBe('11 925,00');
});

test('manager dashboard trend granularity can be changed', function () {
    actingAsManager();

    Livewire::test(Dashboard::class)
        ->assertSet('trend', 'daily')
        ->set('trend', 'weekly')
        ->assertSet('trend', 'weekly');
});
