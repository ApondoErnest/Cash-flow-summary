<?php

declare(strict_types=1);

use App\Modules\CsvImports\Models\ImportError;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

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

test('verification error report can be downloaded when invalid rows hard-fail verification', function () {
    $verification = runVerificationPipelineForContents(loadCsvFixture('invalid_amount.csv'));

    expect($verification->status)->toBe(VerificationStatus::Failed)
        ->and($verification->row_stats['invalid'])->toBe(1);

    $this->actingAs(\App\Models\User::query()->findOrFail($verification->user_id))
        ->get(signedDownloadUrl('verifications.errors.download', ['token' => $verification->token]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload(pathinfo($verification->original_filename, PATHINFO_FILENAME).'-errors.csv');

    expect(ImportError::query()->where('import_verification_id', $verification->id)->count())->toBeGreaterThan(0);
});

test('cashier can download import error report when import has stored errors', function () {
    $center = createTestCenter();
    $cashier = actingAsCashier($center);

    $verification = startVerificationFor($cashier, $center, verificationReadyFrenchCsv([completedFrenchDataRow()]));
    runProcessVerificationJob($verification->token);
    $import = commitVerificationFor($cashier, $verification->fresh());

    ImportError::query()->create([
        'import_id' => $import->id,
        'import_verification_id' => $verification->id,
        'source_row_number' => 2,
        'field' => 'net_amount',
        'error_code' => 'negative_amount',
        'message' => 'Negative amount in net_amount.',
        'original_value' => '-100',
        'raw_row' => 'row',
    ]);

    $import->update(['invalid_count' => 1]);

    $response = $this->actingAs($cashier)
        ->get(signedDownloadUrl('imports.errors.download', ['import' => $import->id]));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload(pathinfo($import->original_filename, PATHINFO_FILENAME).'-errors.csv');

    $csv = $response->getContent();

    expect($csv)
        ->toContain("\xEF\xBB\xBF")
        ->toContain('negative_amount')
        ->toContain('Source row');
});

test('cashier import error download page shows action on result and detail pages', function () {
    $center = createTestCenter();
    $cashier = actingAsCashier($center);

    $verification = startVerificationFor($cashier, $center, verificationReadyFrenchCsv([completedFrenchDataRow()]));
    runProcessVerificationJob($verification->token);
    $import = commitVerificationFor($cashier, $verification->fresh());

    ImportError::query()->create([
        'import_id' => $import->id,
        'import_verification_id' => $verification->id,
        'source_row_number' => 2,
        'field' => 'net_amount',
        'error_code' => 'negative_amount',
        'message' => 'Negative amount in net_amount.',
        'original_value' => '-100',
        'raw_row' => 'row',
    ]);

    $import->update(['invalid_count' => 1]);

    $this->actingAs($cashier)
        ->get(route('imports.result', $import))
        ->assertOk()
        ->assertSee(__('csv_import.result.actions.download_errors'), false);

    $this->actingAs($cashier)
        ->get(route('imports.show', $import))
        ->assertOk()
        ->assertSee(__('csv_import.detail.actions.download_errors'), false);
});

test('cashier cannot download import error report from another center', function () {
    $verification = runVerificationPipelineForContents(verificationReadyFrenchCsv([completedFrenchDataRow()]));
    $import = commitVerificationFor(
        \App\Models\User::query()->findOrFail($verification->user_id),
        $verification->fresh(),
    );

    ImportError::query()->create([
        'import_id' => $import->id,
        'import_verification_id' => $verification->id,
        'source_row_number' => 2,
        'field' => 'net_amount',
        'error_code' => 'negative_amount',
        'message' => 'Negative amount in net_amount.',
        'original_value' => '-100',
        'raw_row' => 'row',
    ]);

    actingAsCashier();

    $this->get(signedDownloadUrl('imports.errors.download', ['import' => $import->id]))
        ->assertNotFound();
});

test('import error download returns not found when import has no invalid rows', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    $this->actingAs($manager)
        ->get(signedDownloadUrl('imports.errors.download', ['import' => $import->id]))
        ->assertNotFound();
});

test('verification failed card shows error report download when invalid rows exist', function () {
    $verification = runVerificationPipelineForContents(loadCsvFixture('invalid_amount.csv'));
    $user = \App\Models\User::query()->findOrFail($verification->user_id);

    Livewire\Livewire::actingAs($user)
        ->test(\App\Modules\CsvVerification\Livewire\CsvVerificationCard::class)
        ->set('verificationToken', $verification->token)
        ->call('refreshVerification')
        ->assertSee(__('csv_verification.summary.download_errors'), false)
        ->assertSee('signature=', false);
});
