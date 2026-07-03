<?php

declare(strict_types=1);

use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Livewire\ImportResultPage;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Livewire\CsvVerificationCard;
use App\Modules\CsvVerification\Livewire\ImportCsv;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\ActiveDailySnapshot;
use App\Modules\DailyVersions\Models\DailyVersion;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

function readyManagerCorrectionComponent(): \Livewire\Features\SupportTesting\Testable
{
    $center = createTestCenter(attributes: ['name' => 'Correction Center']);
    $manager = actingAsManager($center);

    DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2026-06-01',
        'version_number' => 1,
        'dataset_hash' => hash('sha256', 'stale-active-dataset'),
        'record_count' => 1,
        'total_ht' => '10000.00',
        'total_vat' => '1925.00',
        'total_ttc' => '11925.00',
        'status' => DailyVersionStatus::Active,
    ]);

    $activeVersion = DailyVersion::query()->where('center_id', $center->id)->firstOrFail();

    ActiveDailySnapshot::query()->create([
        'center_id' => $center->id,
        'business_date' => '2026-06-01',
        'daily_version_id' => $activeVersion->id,
        'activated_at' => now(),
    ]);

    $component = Livewire::test(CsvVerificationCard::class)
        ->set('importMode', ImportMode::Correction->value)
        ->set('csvFile', UploadedFile::fake()->createWithContent(
            'cashflow-june.csv',
            verificationReadyFrenchCsv([completedFrenchDataRow()]),
        ))
        ->call('verify');

    runProcessVerificationJob($component->get('verificationToken'));

    return $component->call('refreshVerification');
}

test('manager import csv page shows correction guidance when correction mode is selected', function () {
    $center = createTestCenter(attributes: ['name' => 'NACHO Douala']);
    actingAsManager($center);

    $this->get(route('imports.create'))
        ->assertOk()
        ->assertDontSee(__('csv_verification.page.manager.correction_help'), false);

    Livewire::test(CsvVerificationCard::class)
        ->set('importMode', ImportMode::Correction->value)
        ->assertSee(__('csv_verification.correction.manager_notice'), false);
});

test('manager sees correction mode guidance on verification card', function () {
    actingAsManager();

    Livewire::test(CsvVerificationCard::class)
        ->set('importMode', ImportMode::Correction->value)
        ->assertSee(__('csv_verification.import_mode.correction'), false)
        ->assertSee(__('csv_verification.correction.manager_notice'), false);
});

test('manager can submit correction import that awaits owner approval', function () {
    $component = readyManagerCorrectionComponent();

    $component
        ->assertSee(__('csv_verification.correction.manager_submit_notice'), false)
        ->assertSee(__('csv_verification.card.submit_correction'), false);

    $token = $component->get('verificationToken');
    $component->call('import');

    $import = Import::query()->latest('id')->firstOrFail();

    expect($import->import_mode)->toBe(ImportMode::Correction)
        ->and($import->status)->toBe(ImportStatus::AwaitingOwnerApproval);

    expect(
        AuditLog::query()
            ->where('event', 'correction.submitted')
            ->where('resource_id', $import->id)
            ->exists(),
    )->toBeTrue();

    expect(
        \App\Modules\CsvVerification\Models\ImportVerification::query()
            ->where('token', $token)
            ->value('status'),
    )->toBe(VerificationStatus::Imported);

    $component->assertRedirect(route('imports.result', $import));
});

test('manager correction result page shows submission headline and revisions link', function () {
    $component = readyManagerCorrectionComponent();
    $component->call('import');

    $import = Import::query()->latest('id')->firstOrFail();
    $manager = auth()->user();

    $this->actingAs($manager)
        ->get(route('imports.result', $import))
        ->assertOk()
        ->assertSee(__('csv_import.result.headline.correction_submitted'), false)
        ->assertSee(__('csv_import.result.correction.manager_follow_up'), false)
        ->assertSee(__('csv_import.result.actions.view_revisions'), false);

    Livewire::actingAs($manager)
        ->test(ImportResultPage::class, ['import' => $import])
        ->assertSee(__('csv_import.result.headline.correction_submitted'), false);
});

test('cashier can submit correction import that awaits owner approval', function () {
    $center = createTestCenter(attributes: ['name' => 'Correction Center']);
    $cashier = actingAsCashier($center);

    DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2026-06-01',
        'version_number' => 1,
        'dataset_hash' => hash('sha256', 'stale-active-dataset-cashier'),
        'record_count' => 1,
        'total_ht' => '10000.00',
        'total_vat' => '1925.00',
        'total_ttc' => '11925.00',
        'status' => DailyVersionStatus::Active,
    ]);

    $activeVersion = DailyVersion::query()->where('center_id', $center->id)->firstOrFail();

    ActiveDailySnapshot::query()->create([
        'center_id' => $center->id,
        'business_date' => '2026-06-01',
        'daily_version_id' => $activeVersion->id,
        'activated_at' => now(),
    ]);

    $component = Livewire::test(CsvVerificationCard::class)
        ->set('importMode', ImportMode::Correction->value)
        ->set('csvFile', UploadedFile::fake()->createWithContent(
            'cashflow-june.csv',
            verificationReadyFrenchCsv([completedFrenchDataRow()]),
        ))
        ->call('verify');

    runProcessVerificationJob($component->get('verificationToken'));
    $component->call('refreshVerification')
        ->assertSee(__('csv_verification.card.submit_correction'), false);

    $component->call('import');

    $import = Import::query()->latest('id')->firstOrFail();

    expect($import->import_mode)->toBe(ImportMode::Correction)
        ->and($import->status)->toBe(ImportStatus::AwaitingOwnerApproval);

    $this->actingAs($cashier)
        ->get(route('imports.result', $import))
        ->assertOk()
        ->assertSee(__('csv_import.result.headline.correction_submitted'), false)
        ->assertSee(__('csv_import.result.correction.manager_follow_up'), false)
        ->assertDontSee(__('csv_import.result.actions.view_revisions'), false);
});

test('manager import csv page renders compact verification card component', function () {
    actingAsManager();

    Livewire::test(ImportCsv::class)
        ->assertSee(__('csv_verification.page.manager.title'), false)
        ->assertSee('data-mf-csv-verification-card', false)
        ->assertSee('mf-csv-verification-card--compact', false);
});
