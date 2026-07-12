<?php

declare(strict_types=1);

use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\CsvVerification\Services\VerificationService;
use App\Modules\Dashboards\Services\CashierDashboardService;
use App\Modules\Reports\Services\SummaryGenerationService;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

function cashierDashboardFixture(): array
{
    Carbon::setTestNow('2026-06-01 14:30:00');

    $center = createTestCenter(attributes: ['name' => 'Cashier Center']);
    $cashier = actingAsCashier($center);

    $verification = startVerificationFor(
        $cashier,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($cashier, $verification->fresh());
    app(SummaryGenerationService::class)->regenerate($center->id, '2026-06-01');

    return [$cashier, $center];
}

test('cashier dashboard shows fixed center header today stats and import action', function () {
    [$cashier, $center] = cashierDashboardFixture();

    $this->actingAs($cashier)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('dashboard.cashier.title', ['center' => $center->name]), false)
        ->assertSee(__('dashboard.cashier.subtitle', ['date' => '01/06/2026']), false)
        ->assertSee(__('dashboard.cashier.center_label'), false)
        ->assertSee(__('dashboard.cashier.stats.today_ttc'), false)
        ->assertSee(__('dashboard.cashier.stats.yesterday_ttc'), false)
        ->assertSee(__('dashboard.cashier.stats.active_records_today'), false)
        ->assertSee('11,925.00', false)
        ->assertSee('cashflow-june.csv', false)
        ->assertSee(__('dashboard.actions.import_csv'), false)
        ->assertDontSee(__('dashboard.staff.placeholder_title'), false)
        ->assertDontSee(__('dashboard.manager.stats.week_ttc'), false)
        ->assertDontSee(__('dashboard.sections.revenue_trend'), false);
});

test('cashier can access dashboard without owner active center session', function () {
    $cashier = actingAsCashier();

    app(ActiveCenterContextService::class)->clear();

    $this->actingAs($cashier)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($cashier->center->name, false);
});

test('cashier dashboard scopes data to assigned center only', function () {
    Carbon::setTestNow('2026-06-01 14:30:00');

    $owner = actingAsOwner();
    $assignedCenter = createTestCenter($owner->organization, ['name' => 'Assigned Center']);
    $otherCenter = createTestCenter($owner->organization, ['name' => 'Other Center']);
    $cashier = actingAsCashier($assignedCenter);

    $otherCashier = actingAsCashier($otherCenter);
    $verification = startVerificationFor(
        $otherCashier,
        $otherCenter,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($otherCashier, $verification->fresh());
    app(SummaryGenerationService::class)->regenerate($otherCenter->id, '2026-06-01');

    $this->actingAs($cashier);

    $dashboard = app(CashierDashboardService::class)->build($assignedCenter);

    expect($dashboard->centerName)->toBe('Assigned Center')
        ->and($dashboard->todayTtc)->toBe('0.00')
        ->and($dashboard->recentImports)->toBe([]);
});

test('cashier dashboard shows submission status card', function () {
    [$cashier] = cashierDashboardFixture();

    $this->actingAs($cashier)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('dashboard.cashier.submission_title'), false)
        ->assertSee(trans_choice('dashboard.cashier.missing_days_count', 14, ['count' => 14]), false);
});

test('cashier dashboard computes yesterday ttc from daily summaries', function () {
    Carbon::setTestNow('2026-06-02 14:30:00');

    $cashier = actingAsCashier();
    $center = $cashier->center;

    $verification = startVerificationFor(
        $cashier,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($cashier, $verification->fresh());
    app(SummaryGenerationService::class)->regenerate($center->id, '2026-06-01');

    $dashboard = app(CashierDashboardService::class)->build($center);

    expect($dashboard->todayTtc)->toBe('0.00')
        ->and($dashboard->yesterdayTtc)->toBe('11,925.00');
});

test('cashier dashboard limits recent imports to three rows', function () {
    Carbon::setTestNow('2026-06-01 14:30:00');

    $cashier = actingAsCashier();
    $center = $cashier->center;

    foreach (range(1, 4) as $index) {
        $rows = array_fill(0, $index, completedFrenchDataRow());
        $file = UploadedFile::fake()->createWithContent(
            "import-{$index}.csv",
            verificationReadyFrenchCsv($rows),
        );
        $verification = app(VerificationService::class)->start(
            user: $cashier,
            center: $center,
            file: $file,
            importMode: ImportMode::Operational,
        );
        runProcessVerificationJob($verification->token);
        commitVerificationFor($cashier, $verification->fresh());
    }

    $dashboard = app(CashierDashboardService::class)->build($center);

    expect($dashboard->recentImports)->toHaveCount(3)
        ->and(collect($dashboard->recentImports)->pluck('filename')->all())
        ->toBe(['import-4.csv', 'import-3.csv', 'import-2.csv']);
});
