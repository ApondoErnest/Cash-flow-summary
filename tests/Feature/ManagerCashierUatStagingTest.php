<?php

declare(strict_types=1);

use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Jobs\ProcessVerificationJob;
use App\Modules\CsvVerification\Livewire\CsvVerificationCard;
use App\Modules\CsvVerification\Models\ImportVerification;
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

describe('Manager UAT staging journey (Step 107)', function () {
    test('manager dashboard shows assigned center without owner switcher', function () {
        $center = createTestCenter(attributes: ['name' => 'UAT Manager Center']);
        $manager = actingAsManager($center);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.manager.title', ['center' => 'UAT Manager Center']), false)
            ->assertSee(__('navigation.shell.assigned_center'), false)
            ->assertDontSee(__('navigation.shell.switch_center'), false)
            ->assertDontSee('Administration', false);
    });

    test('manager completes verify import and reject flow for assigned center', function () {
        $center = createTestCenter(attributes: ['name' => 'Manager Import Center']);
        actingAsManager($center);

        $this->get(route('imports.create'))
            ->assertOk()
            ->assertSee('Manager Import Center', false)
            ->assertSee(__('csv_verification.card.assigned_center_label'), false);

        $fixtureContents = loadCsvFixture('sample_fr_production_footer.csv');

        $component = Livewire::test(CsvVerificationCard::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('manager-uat.csv', $fixtureContents))
            ->call('verify');

        runProcessVerificationJob($component->get('verificationToken'));

        $rejectToken = $component->call('refreshVerification')->get('verificationToken');

        Livewire::test(CsvVerificationCard::class)
            ->set('verificationToken', $rejectToken)
            ->call('reject')
            ->assertSet('verificationToken', null);

        expect(ImportVerification::query()->where('token', $rejectToken)->value('status'))
            ->toBe(VerificationStatus::Rejected);
        expect(Import::query()->count())->toBe(0);

        $importComponent = Livewire::test(CsvVerificationCard::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('manager-uat-import.csv', $fixtureContents))
            ->call('verify');

        runProcessVerificationJob($importComponent->get('verificationToken'));

        $importComponent
            ->call('refreshVerification')
            ->assertSee(__('csv_verification.summary.footer_totals'), false)
            ->assertSee('11,925.00', false)
            ->call('import');

        $import = Import::query()->latest('id')->firstOrFail();

        expect($import->center_id)->toBe($center->id)
            ->and($import->status)->toBe(ImportStatus::Completed);

        $importComponent->assertRedirect(route('imports.result', $import));
    });

    test('manager correction import awaits owner approval', function () {
        $center = createTestCenter(attributes: ['name' => 'Correction UAT Center']);
        actingAsManager($center);

        DailyVersion::query()->create([
            'center_id' => $center->id,
            'business_date' => '2026-06-01',
            'version_number' => 1,
            'dataset_hash' => hash('sha256', 'uat-active-dataset'),
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
                'manager-correction.csv',
                loadCsvFixture('sample_fr_production_footer.csv'),
            ))
            ->call('verify');

        runProcessVerificationJob($component->get('verificationToken'));

        $component
            ->call('refreshVerification')
            ->assertSee(__('csv_verification.card.submit_correction'), false)
            ->call('import');

        $import = Import::query()->latest('id')->firstOrFail();

        expect($import->import_mode)->toBe(ImportMode::Correction)
            ->and($import->status)->toBe(ImportStatus::AwaitingOwnerApproval);
    });

    test('manager receives 403 when tampering center_id on operational routes', function () {
        actingAsManager();
        $otherCenter = createTestCenter();

        $this->get(route('imports.create', ['center_id' => $otherCenter->id]))
            ->assertForbidden();

        $this->get(route('imports.index', ['center_id' => $otherCenter->id]))
            ->assertForbidden();
    });
});

describe('Cashier UAT staging journey (Step 107)', function () {
    test('cashier dashboard and navigation stay compact without owner switcher', function () {
        $center = createTestCenter(attributes: ['name' => 'UAT Cashier Center']);
        $cashier = actingAsCashier($center);

        $this->actingAs($cashier)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.cashier.title', ['center' => 'UAT Cashier Center']), false)
            ->assertSee(__('dashboard.cashier.center_label'), false)
            ->assertSee(__('dashboard.actions.import_csv'), false)
            ->assertDontSee(__('navigation.shell.switch_center'), false)
            ->assertDontSee(__('navigation.shell.active_center'), false);

        $this->actingAs($cashier)
            ->get(route('dashboard'))
            ->assertSee(route('imports.create'), false)
            ->assertSee(route('imports.index'), false)
            ->assertDontSee(route('reports.index'), false)
            ->assertDontSee(route('records.index'), false);
    });

    test('cashier completes verify import flow with footer totals visible', function () {
        $center = createTestCenter(attributes: ['name' => 'Cashier Import Center']);
        actingAsCashier($center);

        $this->get(route('imports.create'))
            ->assertOk()
            ->assertSee(__('csv_verification.page.cashier.title'), false)
            ->assertSee('Cashier Import Center', false)
            ->assertSee('mf-csv-verification-card--compact', false);

        $fixtureContents = loadCsvFixture('sample_fr_production_footer.csv');

        $component = Livewire::test(CsvVerificationCard::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('cashier-uat.csv', $fixtureContents))
            ->call('verify');

        runProcessVerificationJob($component->get('verificationToken'));

        Queue::assertPushed(ProcessVerificationJob::class, fn (ProcessVerificationJob $job): bool => $job->centerId === $center->id);

        $component
            ->call('refreshVerification')
            ->assertSee(__('csv_verification.summary.footer_totals'), false)
            ->assertSee('11,925.00', false)
            ->call('import');

        $import = Import::query()->latest('id')->firstOrFail();

        expect($import->center_id)->toBe($center->id)
            ->and($import->status)->toBe(ImportStatus::Completed);

        $component->assertRedirect(route('imports.result', $import));
    });

    test('cashier receives 403 when tampering center_id on operational routes', function () {
        actingAsCashier();
        $otherCenter = createTestCenter();

        $this->get(route('imports.create', ['center_id' => $otherCenter->id]))
            ->assertForbidden();

        $this->get(route('imports.index', ['center_id' => $otherCenter->id]))
            ->assertForbidden();
    });
});
