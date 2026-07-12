<?php

declare(strict_types=1);

use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\CsvImports\Livewire\ImportDetail;
use App\Modules\CsvImports\Livewire\ImportList;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

test('cashier imports list page shows compact header and import history', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $cashier = actingAsCashier($manager->center);
    $import = commitVerificationFor($manager, $verification);
    $centerName = $cashier->center->name;

    $this->actingAs($cashier)
        ->get(route('imports.index'))
        ->assertOk()
        ->assertSee(__('csv_import.list.title'), false)
        ->assertSee('Recent imports for your assigned center', false)
        ->assertSee(__('csv_import.page.staff.center_label'), false)
        ->assertSee($centerName, false)
        ->assertSee($import->original_filename, false)
        ->assertSee(__('csv_import.list.view_detail'), false)
        ->assertSee('mf-import-list--compact', false)
        ->assertDontSee(__('csv_import.list.description'), false);
});

test('cashier can access imports list without owner active center session', function () {
    $cashier = actingAsCashier();

    app(ActiveCenterContextService::class)->clear();

    $this->actingAs($cashier)
        ->get(route('imports.index'))
        ->assertOk()
        ->assertSee($cashier->center->name, false);
});

test('cashier imports list scopes to assigned center only', function () {
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

    $this->actingAs($cashier)
        ->get(route('imports.index'))
        ->assertOk()
        ->assertSee(__('csv_import.list.empty'), false)
        ->assertDontSee('cashflow-june.csv', false);
});

test('cashier cannot view import detail from another center', function () {
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
    $import = commitVerificationFor($otherCashier, $verification->fresh());

    $this->actingAs($cashier)
        ->get(route('imports.show', $import))
        ->assertNotFound();
});

test('cashier import detail page shows metadata day comparisons and center banner', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $cashier = actingAsCashier($manager->center);
    $import = commitVerificationFor($manager, $verification);
    $centerName = $cashier->center->name;

    $this->actingAs($cashier)
        ->get(route('imports.show', $import))
        ->assertOk()
        ->assertSee(__('csv_import.page.staff.center_label'), false)
        ->assertSee($centerName, false)
        ->assertSee(__('csv_import.detail.metadata'), false)
        ->assertSee(__('csv_import.detail.day_comparisons_title'), false)
        ->assertSee(__('csv_import.detail.comparison.new'), false)
        ->assertSee('11,925.00', false);
});

test('cashier import detail livewire component loads assigned center import', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $cashier = actingAsCashier($manager->center);
    $import = commitVerificationFor($manager, $verification);

    Livewire::actingAs($cashier)
        ->test(ImportDetail::class, ['import' => $import])
        ->assertSee($import->original_filename, false)
        ->assertSee($cashier->center->name, false)
        ->assertSee(__('csv_import.result.stats.source_rows'), false);
});

test('cashier with tampered center_id on imports routes is blocked', function () {
    actingAsCashier();
    $otherCenter = createTestCenter();

    $this->get(route('imports.index', ['center_id' => $otherCenter->id]))
        ->assertForbidden();
});

test('cashier imports list filters remain available', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $cashier = actingAsCashier($manager->center);
    commitVerificationFor($manager, $verification);

    Livewire::actingAs($cashier)
        ->test(ImportList::class)
        ->set('search', 'cashflow-june')
        ->assertSee('cashflow-june.csv', false);
});
