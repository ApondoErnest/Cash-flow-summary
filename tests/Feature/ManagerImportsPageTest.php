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

test('manager imports list page shows fixed center header and import history', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);
    $centerName = $manager->center->name;

    $this->actingAs($manager)
        ->get(route('imports.index'))
        ->assertOk()
        ->assertSee(__('csv_import.list.title'), false)
        ->assertSee(__('csv_import.page.manager.list.subtitle', ['center' => $centerName]), false)
        ->assertSee(__('csv_import.page.manager.center_label'), false)
        ->assertSee($centerName, false)
        ->assertSee($import->original_filename, false)
        ->assertSee(__('csv_import.list.view_detail'), false)
        ->assertDontSee(__('csv_import.list.description'), false);
});

test('manager can access imports list without owner active center session', function () {
    $manager = actingAsManager();

    app(ActiveCenterContextService::class)->clear();

    $this->actingAs($manager)
        ->get(route('imports.index'))
        ->assertOk()
        ->assertSee($manager->center->name, false);
});

test('manager imports list scopes to assigned center only', function () {
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

    $this->actingAs($manager)
        ->get(route('imports.index'))
        ->assertOk()
        ->assertSee(__('csv_import.list.empty'), false)
        ->assertDontSee('cashflow-june.csv', false);
});

test('manager cannot view import detail from another center', function () {
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
    $import = commitVerificationFor($otherManager, $verification->fresh());

    $this->actingAs($manager)
        ->get(route('imports.show', $import))
        ->assertNotFound();
});

test('manager import detail page shows metadata day comparisons and center banner', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);
    $centerName = $manager->center->name;

    $this->actingAs($manager)
        ->get(route('imports.show', $import))
        ->assertOk()
        ->assertSee(__('csv_import.page.manager.center_label'), false)
        ->assertSee($centerName, false)
        ->assertSee(__('csv_import.detail.metadata'), false)
        ->assertSee(__('csv_import.detail.day_comparisons_title'), false)
        ->assertSee(__('csv_import.detail.comparison.new'), false)
        ->assertSee('11 925,00', false);
});

test('manager import detail livewire component loads assigned center import', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    Livewire::actingAs($manager)
        ->test(ImportDetail::class, ['import' => $import])
        ->assertSee($import->original_filename, false)
        ->assertSee($manager->center->name, false)
        ->assertSee(__('csv_import.result.stats.source_rows'), false);
});

test('manager with tampered center_id on imports routes is blocked', function () {
    actingAsManager();
    $otherCenter = createTestCenter();

    $this->get(route('imports.index', ['center_id' => $otherCenter->id]))
        ->assertForbidden();
});

test('manager imports list filters remain available', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    commitVerificationFor($manager, $verification);

    Livewire::actingAs($manager)
        ->test(ImportList::class)
        ->set('search', 'cashflow-june')
        ->assertSee('cashflow-june.csv', false);
});
