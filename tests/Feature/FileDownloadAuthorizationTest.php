<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    config([
        'csv_verification.temp_disk' => 'local',
        'csv_imports.permanent_disk' => 'local',
        'livewire.temporary_file_upload.disk' => 'local',
        'exports.disk' => 'local',
    ]);
    test()->seed(HeaderAliasSeeder::class);
});

test('unsigned download urls are rejected', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    $import = commitVerificationFor($manager, $verification);
    [, , $export] = completedExportFixture();

    $this->actingAs($manager)
        ->get(route('imports.errors.download', $import))
        ->assertForbidden();

    $this->actingAs($manager)
        ->get(route('exports.download', $export))
        ->assertForbidden();
});

test('expired signed download urls are rejected', function () {
    $verification = runVerificationPipelineForContents(loadCsvFixture('invalid_amount.csv'));
    $user = User::query()->findOrFail($verification->user_id);

    $url = URL::temporarySignedRoute(
        'verifications.errors.download',
        now()->subMinute(),
        ['token' => $verification->token],
    );

    $this->actingAs($user)
        ->get($url)
        ->assertForbidden();
});

test('manager can download verification error report for cashier in same center', function () {
    $center = createTestCenter();
    $cashier = actingAsCashier($center);

    $verification = startVerificationFor($cashier, $center, loadCsvFixture('invalid_amount.csv'));
    runProcessVerificationJob($verification->token);
    $verification->refresh();

    expect($verification->status)->toBe(VerificationStatus::Failed);

    $manager = actingAsManager($center);

    $this->actingAs($manager)
        ->get(signedDownloadUrl('verifications.errors.download', ['token' => $verification->token]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('cashier cannot download verification error report started by manager in same center', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    $verification = startVerificationFor($manager, $center, loadCsvFixture('invalid_amount.csv'));
    runProcessVerificationJob($verification->token);

    actingAsCashier($center);

    $this->get(signedDownloadUrl('verifications.errors.download', ['token' => $verification->token]))
        ->assertForbidden();
});

test('owner cannot download verification errors for another center', function () {
    $owner = actingAsOwner();
    $activeCenter = createTestCenter($owner->organization, ['name' => 'Active Center']);
    $otherCenter = createTestCenter($owner->organization, ['name' => 'Other Center']);
    setOwnerActiveCenter($owner, $activeCenter);

    $manager = actingAsManager($otherCenter);
    $verification = startVerificationFor($manager, $otherCenter, loadCsvFixture('invalid_amount.csv'));
    runProcessVerificationJob($verification->token);

    $this->actingAs($owner)
        ->get(signedDownloadUrl('verifications.errors.download', ['token' => $verification->token]))
        ->assertForbidden();
});

test('guest cannot download signed export url', function () {
    [, , $export] = completedExportFixture();

    auth()->logout();

    $this->get(signedDownloadUrl('exports.download', ['exportRequest' => $export->id]))
        ->assertRedirect(route('login', absolute: false));
});
