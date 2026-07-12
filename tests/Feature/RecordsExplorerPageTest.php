<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CsvImports\Enums\CompletionStatus;
use App\Modules\CsvImports\Enums\FinancialStatus;
use App\Modules\CsvImports\Livewire\RecordsExplorer;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\CsvImports\Services\RecordExplorerService;
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

test('records explorer page shows master records for manager after import', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    commitVerificationFor($manager, $verification);

    $this->actingAs($manager)
        ->get(route('records.index'))
        ->assertOk()
        ->assertSee(__('records.title'), false)
        ->assertSee(__('records.view_detail'), false);
});

test('records explorer search filters by customer name', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    commitVerificationFor($manager, $verification);

    Livewire::actingAs($manager)
        ->test(RecordsExplorer::class)
        ->set('search', 'ACME')
        ->assertSee('ACME', false)
        ->set('search', 'NOT-FOUND-XYZ')
        ->assertSee(__('records.empty'), false);
});

test('records explorer filters by completion status', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    commitVerificationFor($manager, $verification);

    Livewire::actingAs($manager)
        ->test(RecordsExplorer::class)
        ->set('completionFilter', CompletionStatus::Completed->value)
        ->assertDontSee(__('records.empty'), false)
        ->set('completionFilter', CompletionStatus::Unfinished->value)
        ->assertSee(__('records.empty'), false);
});

test('records explorer select record shows detail panel', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    commitVerificationFor($manager, $verification);

    $recordId = MasterCashFlowRecord::query()->value('id');

    Livewire::actingAs($manager)
        ->test(RecordsExplorer::class)
        ->call('selectRecord', $recordId)
        ->assertSet('selectedRecordId', $recordId)
        ->assertSee(__('records.detail_title'), false)
        ->assertSee('11,925.00', false);
});

test('owner cannot select record from another center', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    commitVerificationFor($manager, $verification);

    $recordId = MasterCashFlowRecord::query()->withoutCenterScope()->value('id');

    $owner = User::query()
        ->where('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->firstOrFail();

    $otherCenter = createTestCenter($owner->organization, ['code' => 'OTHER-'.uniqid()]);
    setOwnerActiveCenter($owner, $otherCenter);

    Livewire::actingAs($owner)
        ->test(RecordsExplorer::class)
        ->call('selectRecord', $recordId)
        ->assertSet('selectedRecordId', null);
});

test('record explorer service maps row and detail data', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    $record = MasterCashFlowRecord::query()->firstOrFail();
    $service = app(RecordExplorerService::class);

    $row = $service->toRow($record);
    $detail = $service->toDetail($record->fresh());

    expect($row->grossAmount)->toBe('11,925.00')
        ->and($row->completionStatusVariant)->toBe('success')
        ->and($detail->firstImportId)->toBe($import->id)
        ->and($detail->financialStatusLabel)->toBe(__('records.status.financial.revenue'));
});

test('records explorer filters by financial status', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    commitVerificationFor($manager, $verification);

    Livewire::actingAs($manager)
        ->test(RecordsExplorer::class)
        ->set('financialFilter', FinancialStatus::Revenue->value)
        ->assertDontSee(__('records.empty'), false)
        ->set('financialFilter', FinancialStatus::ZeroValue->value)
        ->assertSee(__('records.empty'), false);
});
