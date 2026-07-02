<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CsvImports\Enums\DayComparisonResult;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Livewire\ImportResultPage;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportDayComparison;
use App\Modules\CsvImports\Services\ImportResultService;
use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use App\Modules\WhatsApp\Models\WhatsappMessage;
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

test('import result page shows completed import summary for manager', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    $this->actingAs($manager)
        ->get(route('imports.result', $import))
        ->assertOk()
        ->assertSee(__('csv_import.result.title'), false)
        ->assertSee($import->original_filename, false)
        ->assertSee(__('csv_import.result.stats.new_unique'), false)
        ->assertSee('11 925,00', false)
        ->assertSee(__('csv_import.result.actions.import_another'), false);
});

test('import result livewire component authorizes active center access', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    Livewire::test(ImportResultPage::class, ['import' => $import])
        ->assertSee(__('csv_import.result.headline.completed'), false)
        ->assertSee(__('csv_import.result.whatsapp.not_applicable'), false);
});

test('owner cannot view import result for another center', function () {
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
        ->get(route('imports.result', $import))
        ->assertNotFound();
});

test('verification card import redirects to import result page', function () {
    $component = readyVerificationCardComponent();

    $component->call('import');

    $import = Import::query()->latest('id')->firstOrFail();

    $component->assertRedirect(route('imports.result', $import));
});

test('import result service aggregates day impact and whatsapp status', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    ImportDayComparison::query()
        ->withoutCenterScope()
        ->where('import_id', $import->id)
        ->update(['comparison_result' => DayComparisonResult::Unchanged]);

    WhatsappMessage::query()->create([
        'idempotency_key' => 'result-test-'.uniqid(),
        'center_id' => $import->center_id,
        'import_id' => $import->id,
        'event_type' => 'historical_import',
        'recipient_phone' => '+237600000000',
        'template_name' => 'historical_import',
        'payload_summary' => [
            'center_name' => $import->center->name,
            'row_count' => 1,
        ],
        'status' => WhatsappMessageStatus::Sent,
    ]);

    $result = app(ImportResultService::class)->build($import->fresh());

    expect($result->sourceRows)->toBe(1)
        ->and($result->newUnique)->toBe(1)
        ->and($result->activeDays)->toBe(0)
        ->and($result->unchangedDays)->toBe(1)
        ->and($result->footerTtc)->toBe('11 925,00')
        ->and($result->whatsappStatus)->toBe(__('csv_import.result.whatsapp.sent'))
        ->and($result->statusVariant)->toBe('success');
});

test('import result page shows awaiting approval state when revisions are pending', function () {
    [$import, , , , $manager] = revisionApprovalFixture();

    $this->actingAs($manager)
        ->get(route('imports.result', $import))
        ->assertOk()
        ->assertSee(__('csv_import.result.headline.awaiting_owner_approval'), false)
        ->assertSee(__('csv_import.result.stats.revisions_pending'), false);
});
