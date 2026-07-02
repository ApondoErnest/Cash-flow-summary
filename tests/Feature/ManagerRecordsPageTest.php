<?php

declare(strict_types=1);

use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\CsvImports\Enums\CompletionStatus;
use App\Modules\CsvImports\Livewire\RecordsExplorer;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
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

function managerRecordsFixture(): array
{
    $center = createTestCenter(attributes: ['name' => 'Manager Center']);
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($manager, $verification->fresh());

    return [$manager, $center];
}

test('manager records page shows fixed center header and searchable ledger', function () {
    [$manager, $center] = managerRecordsFixture();

    $this->actingAs($manager)
        ->get(route('records.index'))
        ->assertOk()
        ->assertSee(__('records.title'), false)
        ->assertSee(__('records.page.manager.subtitle', ['center' => $center->name]), false)
        ->assertSee(__('records.page.manager.center_label'), false)
        ->assertSee($center->name, false)
        ->assertSee('ACME', false)
        ->assertSee(__('records.view_detail'), false)
        ->assertDontSee(__('records.description'), false);
});

test('manager can access records page without owner active center session', function () {
    $manager = actingAsManager();

    app(ActiveCenterContextService::class)->clear();

    $this->actingAs($manager)
        ->get(route('records.index'))
        ->assertOk()
        ->assertSee($manager->center->name, false);
});

test('manager records list scopes to assigned center only', function () {
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
        ->get(route('records.index'))
        ->assertOk()
        ->assertSee(__('records.empty'), false)
        ->assertDontSee('ACME', false);
});

test('manager cannot select record from another center', function () {
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

    $recordId = MasterCashFlowRecord::query()->withoutCenterScope()->value('id');

    Livewire::actingAs($manager)
        ->test(RecordsExplorer::class)
        ->call('selectRecord', $recordId)
        ->assertSet('selectedRecordId', null);
});

test('manager records search filters by customer name', function () {
    [$manager] = managerRecordsFixture();

    Livewire::actingAs($manager)
        ->test(RecordsExplorer::class)
        ->set('search', 'ACME')
        ->assertSee('ACME', false)
        ->set('search', 'NOT-FOUND-XYZ')
        ->assertSee(__('records.empty'), false);
});

test('manager records explorer select record shows detail panel with import link', function () {
    [$manager] = managerRecordsFixture();

    $recordId = MasterCashFlowRecord::query()->value('id');

    Livewire::actingAs($manager)
        ->test(RecordsExplorer::class)
        ->call('selectRecord', $recordId)
        ->assertSet('selectedRecordId', $recordId)
        ->assertSee(__('records.detail_title'), false)
        ->assertSee('11 925,00', false)
        ->assertSee(__('records.view_first_import', ['filename' => 'cashflow-june.csv']), false);
});

test('manager records explorer filters by completion status', function () {
    [$manager] = managerRecordsFixture();

    Livewire::actingAs($manager)
        ->test(RecordsExplorer::class)
        ->set('completionFilter', CompletionStatus::Completed->value)
        ->assertDontSee(__('records.empty'), false)
        ->set('completionFilter', CompletionStatus::Unfinished->value)
        ->assertSee(__('records.empty'), false);
});

test('manager with tampered center_id on records route is blocked', function () {
    actingAsManager();
    $otherCenter = createTestCenter();

    $this->get(route('records.index', ['center_id' => $otherCenter->id]))
        ->assertForbidden();
});
