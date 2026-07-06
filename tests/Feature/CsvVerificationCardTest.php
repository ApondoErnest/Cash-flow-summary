<?php

declare(strict_types=1);

use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Enums\ImportMode;
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

function readyVerificationCardComponent(): \Livewire\Features\SupportTesting\Testable
{
    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization);
    setOwnerActiveCenter($owner, $center);

    $component = Livewire::test(CsvVerificationCard::class)
        ->set('csvFile', UploadedFile::fake()->createWithContent(
            'cashflow-june.csv',
            verificationReadyFrenchCsv([completedFrenchDataRow()]),
        ))
        ->call('verify');

    runProcessVerificationJob($component->get('verificationToken'));

    return $component->call('refreshVerification');
}

test('owner import csv page shows active center read-only on verification card', function () {
    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization, ['name' => 'NACHO Douala']);
    setOwnerActiveCenter($owner, $center);

    $this->get(route('imports.create'))
        ->assertOk()
        ->assertSee(__('csv_verification.card.heading'), false)
        ->assertSee(__('csv_verification.card.center_label'), false)
        ->assertSee('NACHO Douala', false)
        ->assertSee('data-mf-csv-verification-card', false);
});

test('owner verification card starts verify using active center not request input', function () {
    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization, ['name' => 'Active Center']);
    setOwnerActiveCenter($owner, $center);

    Livewire::test(CsvVerificationCard::class)
        ->set('csvFile', UploadedFile::fake()->createWithContent('cashflow-june.csv', verificationReadyFrenchCsv([completedFrenchDataRow()])))
        ->call('verify')
        ->assertHasNoErrors()
        ->assertSet('verificationToken', fn (?string $token): bool => is_string($token) && $token !== '');

    Queue::assertPushed(ProcessVerificationJob::class, function (ProcessVerificationJob $job) use ($center): bool {
        return $job->centerId === $center->id;
    });
});

test('manager verification card uses compact layout without duplicate center block', function () {
    $center = createTestCenter(attributes: ['name' => 'Manager Center']);
    actingAsManager($center);

    Livewire::test(CsvVerificationCard::class)
        ->assertSee('mf-csv-verification-card--compact', false)
        ->assertDontSee(__('csv_verification.card.center_label'), false)
        ->assertDontSee('Manager Center', false);
});

test('cashier can select correction import mode', function () {
    $center = createTestCenter();
    $cashier = actingAsCashier($center);

    expect(ImportMode::availableFor($cashier))->toHaveCount(3)
        ->and(collect(ImportMode::availableFor($cashier))->map->value->all())
        ->toBe(['operational', 'historical', 'correction']);

    Livewire::test(CsvVerificationCard::class)
        ->set('importMode', ImportMode::Correction->value)
        ->assertSee(__('csv_verification.import_mode.correction'), false)
        ->assertSee(__('csv_verification.correction.manager_notice'), false);
});

test('owner sees all import modes including correction', function () {
    actingAsOwner();

    Livewire::test(CsvVerificationCard::class)
        ->assertSee(__('csv_verification.import_mode.correction'), false);
});

test('historical import mode exposes notify owner checkbox', function () {
    actingAsOwner();

    Livewire::test(CsvVerificationCard::class)
        ->set('importMode', ImportMode::Historical->value)
        ->assertSet('importMode', ImportMode::Historical->value)
        ->assertSee(__('csv_verification.card.notify_owner_help'), false);
});

test('verify button stays disabled until a file is selected', function () {
    actingAsOwner();

    Livewire::test(CsvVerificationCard::class)
        ->assertSet('csvFile', null)
        ->assertSee(__('csv_verification.card.verify'), false);
});

test('import csv page renders shared verification card component', function () {
    actingAsOwner();

    Livewire::test(ImportCsv::class)
        ->assertSee(__('csv_verification.card.heading'), false)
        ->assertSee('data-mf-csv-verification-card', false);
});

test('verification card tracks verifying phase after verify is clicked', function () {
    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization);
    setOwnerActiveCenter($owner, $center);

    $component = Livewire::test(CsvVerificationCard::class)
        ->set('csvFile', UploadedFile::fake()->createWithContent('cashflow-june.csv', verificationReadyFrenchCsv([completedFrenchDataRow()])))
        ->call('verify')
        ->assertHasNoErrors();

    $token = $component->get('verificationToken');

    expect($token)->not->toBeNull();

    $component->assertSee(__('csv_verification.card.verifying_title'), false);
});

test('verification card shows summary panel after processing completes', function () {
    config(['csv_verification.process_synchronously' => true]);

    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization);
    setOwnerActiveCenter($owner, $center);

    Livewire::test(CsvVerificationCard::class)
        ->set('csvFile', UploadedFile::fake()->createWithContent(
            'cashflow-june.csv',
            verificationReadyFrenchCsv([completedFrenchDataRow()]),
        ))
        ->call('verify')
        ->assertHasNoErrors()
        ->assertSee(__('csv_verification.summary.footer_totals'), false)
        ->assertSee(__('csv_verification.summary.verification_status'), false)
        ->assertSee(__('csv_verification.summary.compact_stats'), false)
        ->assertSee('11 925,00', false)
        ->assertSee(__('csv_verification.card.import'), false)
        ->assertSee(__('csv_verification.card.reject'), false);
});

test('verification card reject clears summary and returns to empty state', function () {
    readyVerificationCardComponent()
        ->call('reject')
        ->assertSet('verificationToken', null)
        ->assertDontSee(__('csv_verification.summary.footer_totals'), false);
});

test('verification card import commits ready verification and redirects to import result', function () {
    $component = readyVerificationCardComponent();

    $token = $component->get('verificationToken');

    $component
        ->call('import');

    $import = Import::query()->latest('id')->firstOrFail();

    $component->assertRedirect(route('imports.result', $import));

    expect(
        \App\Modules\CsvVerification\Models\ImportVerification::query()
            ->where('token', $token)
            ->value('status'),
    )->toBe(VerificationStatus::Imported);
});

test('verification card shows ready state after processing completes', function () {
    readyVerificationCardComponent()
        ->assertSee(__('csv_verification.summary.footer_totals'), false);
});

test('owner cannot verify for a center outside active session via card', function () {
    $owner = actingAsOwner();
    $activeCenter = createTestCenter($owner->organization, ['name' => 'Active Center']);
    setOwnerActiveCenter($owner, $activeCenter);

    $otherCenter = createTestCenter($owner->organization, ['code' => 'OTHER-'.uniqid()]);

    Livewire::test(CsvVerificationCard::class)
        ->assertSee('Active Center', false);

    expect(fn () => startVerificationFor($owner, $otherCenter))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});
