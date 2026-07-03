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

test('verification error report can be downloaded when invalid rows are detected', function () {
    $verification = runVerificationPipelineForContents(loadCsvFixture('invalid_amount.csv'));

    expect($verification->status)->toBe(VerificationStatus::Ready)
        ->and($verification->row_stats['invalid'])->toBe(1);

    $this->actingAs(\App\Models\User::query()->findOrFail($verification->user_id))
        ->get(route('verifications.errors.download', $verification->token))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload(pathinfo($verification->original_filename, PATHINFO_FILENAME).'-errors.csv');

    expect(ImportError::query()->where('import_verification_id', $verification->id)->count())->toBeGreaterThan(0);
});

test('cashier can download import error report after committing file with invalid rows', function () {
    $verification = runVerificationPipelineForContents(loadCsvFixture('invalid_amount.csv'));
    $cashier = actingAsCashier(
        \App\Modules\Centers\Models\Center::query()->findOrFail($verification->center_id),
    );

    $import = commitVerificationFor($cashier, $verification->fresh());

    expect($import->invalid_count)->toBe(1);

    $response = $this->actingAs($cashier)
        ->get(route('imports.errors.download', $import));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload(pathinfo($import->original_filename, PATHINFO_FILENAME).'-errors.csv');

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain("\xEF\xBB\xBF")
        ->toContain('invalid_amount')
        ->toContain('Source row');
});

test('cashier import error download page shows action on result and detail pages', function () {
    $verification = runVerificationPipelineForContents(loadCsvFixture('invalid_amount.csv'));
    $cashier = actingAsCashier(
        \App\Modules\Centers\Models\Center::query()->findOrFail($verification->center_id),
    );
    $import = commitVerificationFor($cashier, $verification->fresh());

    $this->actingAs($cashier)
        ->get(route('imports.result', $import))
        ->assertOk()
        ->assertSee(__('csv_import.result.actions.download_errors'), false)
        ->assertSee(route('imports.errors.download', $import), false);

    $this->actingAs($cashier)
        ->get(route('imports.show', $import))
        ->assertOk()
        ->assertSee(__('csv_import.detail.actions.download_errors'), false);
});

test('cashier cannot download import error report from another center', function () {
    $verification = runVerificationPipelineForContents(loadCsvFixture('invalid_amount.csv'));
    $import = commitVerificationFor(
        \App\Models\User::query()->findOrFail($verification->user_id),
        $verification->fresh(),
    );

    actingAsCashier();

    $this->get(route('imports.errors.download', $import))
        ->assertNotFound();
});

test('import error download returns not found when import has no invalid rows', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    $this->actingAs($manager)
        ->get(route('imports.errors.download', $import))
        ->assertNotFound();
});

test('verification summary shows error report download when invalid rows exist', function () {
    $verification = runVerificationPipelineForContents(loadCsvFixture('invalid_amount.csv'));
    $user = \App\Models\User::query()->findOrFail($verification->user_id);

    Livewire\Livewire::actingAs($user)
        ->test(\App\Modules\CsvVerification\Livewire\CsvVerificationCard::class)
        ->set('verificationToken', $verification->token)
        ->call('refreshVerification')
        ->assertSee(__('csv_verification.summary.download_errors'), false)
        ->assertSee(route('verifications.errors.download', $verification->token), false);
});
