<?php

declare(strict_types=1);

use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Jobs\ProcessVerificationJob;
use App\Modules\CsvVerification\Livewire\CsvVerificationCard;
use App\Modules\CsvVerification\Livewire\ImportCsv;
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

function readyManagerVerificationCardComponent(): \Livewire\Features\SupportTesting\Testable
{
    $center = createTestCenter(attributes: ['name' => 'Manager Center']);
    $manager = actingAsManager($center);

    $component = Livewire::test(CsvVerificationCard::class)
        ->set('csvFile', UploadedFile::fake()->createWithContent(
            'cashflow-june.csv',
            verificationReadyFrenchCsv([completedFrenchDataRow()]),
        ))
        ->call('verify');

    runProcessVerificationJob($component->get('verificationToken'));

    return $component->call('refreshVerification');
}

test('manager import csv page shows fixed center header and shared verification card', function () {
    $center = createTestCenter(attributes: ['name' => 'NACHO Douala']);
    actingAsManager($center);

    $this->get(route('imports.create'))
        ->assertOk()
        ->assertSee(__('csv_verification.page.manager.title'), false)
        ->assertSee(__('csv_verification.page.manager.subtitle', ['center' => 'NACHO Douala']), false)
        ->assertSee(__('csv_verification.card.assigned_center_label'), false)
        ->assertSee('NACHO Douala', false)
        ->assertSee(__('csv_verification.card.heading'), false)
        ->assertSee('data-mf-csv-verification-card', false);
});

test('manager can access import csv page without owner active center session', function () {
    $manager = actingAsManager();

    app(ActiveCenterContextService::class)->clear();

    $this->actingAs($manager)
        ->get(route('imports.create'))
        ->assertOk()
        ->assertSee($manager->center->name, false);
});

test('manager verification card starts verify using assigned center', function () {
    $center = createTestCenter(attributes: ['name' => 'Assigned Center']);
    actingAsManager($center);

    Livewire::test(CsvVerificationCard::class)
        ->set('csvFile', UploadedFile::fake()->createWithContent('cashflow-june.csv', verificationReadyFrenchCsv([completedFrenchDataRow()])))
        ->call('verify')
        ->assertHasNoErrors()
        ->assertSet('verificationToken', fn (?string $token): bool => is_string($token) && $token !== '');

    Queue::assertPushed(ProcessVerificationJob::class, function (ProcessVerificationJob $job) use ($center): bool {
        return $job->centerId === $center->id;
    });
});

test('manager import csv page completes verify import reject flow', function () {
    readyManagerVerificationCardComponent()
        ->assertSee(__('csv_verification.summary.footer_totals'), false)
        ->assertSee(__('csv_verification.card.import'), false)
        ->assertSee(__('csv_verification.card.reject'), false);
});

test('manager verification card import commits ready verification and redirects to import result', function () {
    $component = readyManagerVerificationCardComponent();

    $token = $component->get('verificationToken');

    $component->call('import');

    $import = Import::query()->latest('id')->firstOrFail();

    $component->assertRedirect(route('imports.result', $import));

    expect(
        \App\Modules\CsvVerification\Models\ImportVerification::query()
            ->where('token', $token)
            ->value('status'),
    )->toBe(VerificationStatus::Imported);
});

test('manager with tampered center_id on import csv route is blocked', function () {
    actingAsManager();
    $otherCenter = createTestCenter();

    $this->get(route('imports.create', ['center_id' => $otherCenter->id]))
        ->assertForbidden();
});

test('manager import csv page renders shared verification card component', function () {
    actingAsManager();

    Livewire::test(ImportCsv::class)
        ->assertSee(__('csv_verification.page.manager.title'), false)
        ->assertSee('data-mf-csv-verification-card', false);
});
