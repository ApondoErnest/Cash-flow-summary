<?php

declare(strict_types=1);

use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\Dashboards\Enums\DashboardPeriod;
use App\Modules\Dashboards\Enums\DashboardTrendGranularity;
use App\Modules\Dashboards\Livewire\Dashboard;
use App\Modules\Dashboards\Services\OwnerDashboardService;
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

test('owner dashboard shows financial totals from active snapshots without daily summaries', function () {
    Carbon::setTestNow('2026-07-06 12:00:00');

    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization, ['name' => 'Cente1']);
    setOwnerActiveCenter($owner, $center);
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($manager, $verification->fresh());

    actingAsOwner($center);

    $dashboard = app(OwnerDashboardService::class)->build(
        center: $center,
        period: DashboardPeriod::Year,
        trendGranularity: DashboardTrendGranularity::Daily,
    );

    expect($dashboard->totalTtc)->toBe('11 925,00')
        ->and($dashboard->totalHt)->toBe('10 000,00')
        ->and($dashboard->totalVat)->toBe('1 925,00');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('reports.stats.total_ttc'), false)
        ->assertSee(__('reports.stats.total_ht'), false)
        ->assertSee(__('reports.stats.total_vat'), false)
        ->assertSee('11 925,00', false);
});

test('owner dashboard shows category and cv counts from active records', function () {
    Carbon::setTestNow('2026-07-06 12:00:00');

    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization);
    setOwnerActiveCenter($owner, $center);
    $manager = actingAsManager($center);

    $rows = [
        frenchDataRow('01/06/2026', '10:00', '02/06/2026', 'Client A', 'A', 'C', 'LT-001', '10 000', '1 925', '11 925'),
        frenchDataRow('01/06/2026', '11:00', '02/06/2026', 'Client B', 'B', 'C', 'LT-002', '10 000', '1 925', '11 925'),
        frenchDataRow('01/06/2026', '12:00', '-', 'Client B1', 'B1', 'CV', 'LT-003', '0', '0', '0'),
        frenchDataRow('02/06/2026', '09:00', '02/06/2026', 'Client D', 'D', 'CV', 'LT-004', '0', '0', '0'),
        frenchDataRow('02/06/2026', '10:00', '02/06/2026', 'Client C', 'C', 'C', 'LT-005', '10 000', '1 925', '11 925'),
    ];

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv($rows, frenchFooterLine(5, 30_000, 5_775, 35_775)),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($manager, $verification->fresh());

    actingAsOwner($center);

    $dashboard = app(OwnerDashboardService::class)->build(
        center: $center,
        period: DashboardPeriod::Year,
        trendGranularity: DashboardTrendGranularity::Daily,
    );

    expect(collect($dashboard->categoryCounts)->mapWithKeys(
        static fn ($item): array => [$item->code => $item->count],
    )->all())->toBe([
        'A' => 1,
        'B' => 1,
        'B1' => 1,
        'C' => 1,
        'D' => 1,
    ])->and($dashboard->cvInspectionCount)->toBe(2);
});

test('owner dashboard shows selected center title and primary stats from active snapshots', function () {
    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization, ['name' => 'NACHO Yaounde']);
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

    actingAsOwner($center);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('NACHO Yaounde Cash-Flow Dashboard', false)
        ->assertSee('11 925,00', false)
        ->assertSee(__('dashboard.stats.unique_records'), false)
        ->assertSee('cashflow-june.csv', false);
});

test('owner dashboard scopes stats to active center not other organization centers', function () {
    $owner = actingAsOwner();
    $activeCenter = createTestCenter($owner->organization, ['name' => 'Active Center']);
    $otherCenter = createTestCenter($owner->organization, ['name' => 'Other Center']);

    $manager = actingAsManager($otherCenter);
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    commitVerificationFor($manager, $verification);
    app(SummaryGenerationService::class)->regenerate($otherCenter->id, '2026-06-01');

    actingAsOwner($activeCenter);

    $dashboard = app(OwnerDashboardService::class)->build(
        center: $activeCenter,
        period: DashboardPeriod::Month,
        trendGranularity: DashboardTrendGranularity::Daily,
    );

    expect($dashboard->centerName)->toBe('Active Center');
    expect($dashboard->totalTtc)->toBe('0,00');
    expect($dashboard->recentImports)->toBe([]);
});

test('owner dashboard defaults to today period on first load', function () {
    actingAsOwner();

    Livewire::test(Dashboard::class)
        ->assertSet('period', 'today')
        ->assertSet('fromDate', null)
        ->assertSet('toDate', null);

    expect(session('owner.filters.dashboard_period'))->toBe('today');
});

test('owner dashboard period filter is persisted in session', function () {
    actingAsOwner();

    Livewire::test(Dashboard::class)
        ->set('period', 'week')
        ->assertSet('period', 'week');

    expect(session('owner.filters.dashboard_period'))->toBe('week');
});

test('owner dashboard custom period applies selected date range', function () {
    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization);
    setOwnerActiveCenter($owner, $center);
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($manager, $verification->fresh());
    app(\App\Modules\Reports\Services\SummaryGenerationService::class)->regenerate($center->id, '2026-06-01');

    actingAsOwner($center);

    Livewire::test(Dashboard::class)
        ->set('customFromDate', '2026-06-01')
        ->set('customToDate', '2026-06-01')
        ->call('applyCustomPeriod')
        ->assertSet('period', 'custom')
        ->assertSet('fromDate', '2026-06-01')
        ->assertSet('toDate', '2026-06-01')
        ->assertSee('11 925,00', false);

    expect(session('owner.filters.dashboard_period'))->toBe('custom');
    expect(session('owner.filters.dashboard_period_from'))->toBe('2026-06-01');
    expect(session('owner.filters.dashboard_period_to'))->toBe('2026-06-01');
});

test('selecting custom period opens calendar modal without changing active filter until apply', function () {
    actingAsOwner();

    Livewire::test(Dashboard::class)
        ->set('period', 'custom')
        ->assertSet('showCustomPeriodModal', true)
        ->assertSet('period', 'today');
});

test('applied custom period modal reopens when custom range is selected again', function () {
    actingAsOwner();

    Livewire::test(Dashboard::class)
        ->set('customFromDate', '2026-06-01')
        ->set('customToDate', '2026-06-10')
        ->call('applyCustomPeriod')
        ->assertSet('period', 'custom')
        ->call('openCustomPeriodModal')
        ->assertSet('showCustomPeriodModal', true)
        ->assertSet('customFromDate', '2026-06-01')
        ->assertSet('customToDate', '2026-06-10');
});

test('owner dashboard custom period modal uses themed date picker fields', function () {
    actingAsOwner();

    Livewire::test(Dashboard::class)
        ->call('openCustomPeriodModal')
        ->assertSee(__('dashboard.period.fields.from'), false)
        ->assertSee(__('dashboard.period.fields.to'), false)
        ->assertSee('data-mf-date-picker', false);
});

test('owner dashboard shows revision pending alert when import awaits approval', function () {
    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization);
    setOwnerActiveCenter($owner, $center);

    Import::query()->create([
        'center_id' => $center->id,
        'uploaded_by' => $owner->id,
        'import_mode' => ImportMode::Operational,
        'source_language' => 'fr',
        'original_filename' => 'pending.csv',
        'storage_path' => 'imports/'.$center->id.'/pending.csv',
        'file_hash' => hash('sha256', 'pending-import'),
        'file_size' => 100,
        'status' => ImportStatus::AwaitingOwnerApproval,
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('revision approval', false);
});

test('cashier dashboard shows compact dashboard instead of owner dashboard', function () {
    actingAsCashier();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('dashboard.cashier.stats.today_ttc'), false)
        ->assertDontSee('Cash-Flow Dashboard', false);
});
