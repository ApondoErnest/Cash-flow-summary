<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Livewire\ImportDetail;
use App\Modules\CsvImports\Livewire\ImportList;
use App\Modules\CsvImports\Services\ImportListService;
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

test('imports list page shows active center imports for manager', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    $this->actingAs($manager)
        ->get(route('imports.index'))
        ->assertOk()
        ->assertSee(__('csv_import.list.title'), false)
        ->assertSee($import->original_filename, false)
        ->assertSee(__('csv_import.list.view_detail'), false)
        ->assertSee('data-mf-date-picker', false);
});

test('imports list filters by filename search', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    commitVerificationFor($manager, $verification);

    Livewire::actingAs($manager)
        ->test(ImportList::class)
        ->set('search', 'cashflow-june')
        ->assertSee('cashflow-june.csv', false)
        ->set('search', 'missing-file')
        ->assertSee(__('csv_import.list.empty'), false);
});

test('imports list filters by status', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    commitVerificationFor($manager, $verification);

    Livewire::actingAs($manager)
        ->test(ImportList::class)
        ->set('statusFilter', ImportStatus::Completed->value)
        ->assertSee('cashflow-june.csv', false)
        ->set('statusFilter', ImportStatus::Failed->value)
        ->assertSee(__('csv_import.list.empty'), false);
});

test('owner cannot view imports list without active center', function () {
    $owner = actingAsOwnerWithoutActiveCenter();

    $this->actingAs($owner)
        ->get(route('imports.index'))
        ->assertRedirect(route('center.select'));
});

test('owner cannot view import from another center on detail page', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    $owner = User::query()
        ->where('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->firstOrFail();

    $otherCenter = createTestCenter($owner->organization, ['code' => 'OTHER-'.uniqid()]);
    setOwnerActiveCenter($owner, $otherCenter);

    $this->actingAs($owner)
        ->get(route('imports.show', $import))
        ->assertNotFound();
});

test('import detail page shows metadata and day comparisons', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    $this->actingAs($manager)
        ->get(route('imports.show', $import))
        ->assertOk()
        ->assertSee(__('csv_import.detail.metadata'), false)
        ->assertSee(__('csv_import.detail.day_comparisons_title'), false)
        ->assertSee(__('csv_import.detail.comparison.new'), false)
        ->assertSee('11 925,00', false);
});

test('import detail livewire component authorizes center scoped import', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    Livewire::actingAs($manager)
        ->test(ImportDetail::class, ['import' => $import])
        ->assertSee($import->original_filename, false)
        ->assertSee(__('csv_import.result.stats.source_rows'), false);
});

test('import list service maps import rows for table display', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    $row = app(ImportListService::class)->toListRow($import->fresh());

    expect($row->filename)->toBe('cashflow-june.csv')
        ->and($row->totalTtc)->toBe('11 925,00')
        ->and($row->statusVariant)->toBe('success');
});

test('import result page links to import detail', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    $this->actingAs($manager)
        ->get(route('imports.result', $import))
        ->assertOk()
        ->assertSee(route('imports.show', $import), false);
});
