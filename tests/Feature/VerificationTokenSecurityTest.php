<?php

declare(strict_types=1);

use App\Modules\CsvImports\Services\ImportService;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Livewire\CsvVerificationCard;
use App\Modules\CsvVerification\Services\VerificationService;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    config([
        'csv_verification.temp_disk' => 'local',
        'csv_verification.ttl_minutes' => 120,
        'csv_imports.permanent_disk' => 'local',
        'livewire.temporary_file_upload.disk' => 'local',
    ]);
    test()->seed(HeaderAliasSeeder::class);
});

test('commit rejects unknown verification token', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    expect(fn () => app(ImportService::class)->commitFromVerification(
        $manager,
        (string) Str::uuid(),
    ))->toThrow(InvalidArgumentException::class, __('csv_import.commit.not_found'));
});

test('commit rejects reused verification token after successful import', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    commitVerificationFor($manager, $verification);

    expect(fn () => commitVerificationFor($manager, $verification->fresh()))
        ->toThrow(InvalidArgumentException::class, __('csv_import.commit.already_committed'));
});

test('commit rejects verification token after reject', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    app(VerificationService::class)->reject($manager, $verification->fresh());

    expect($verification->fresh()->status)->toBe(VerificationStatus::Rejected);

    expect(fn () => commitVerificationFor($manager, $verification->fresh()))
        ->toThrow(InvalidArgumentException::class, __('csv_verification.verification.expired'));
});

test('commit rejects expired verification token by ttl', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $verification->update(['expires_at' => now()->subMinute()]);

    expect(fn () => commitVerificationFor($manager, $verification->fresh()))
        ->toThrow(InvalidArgumentException::class, __('csv_verification.verification.expired'));
});

test('commit rejects verification marked expired by cleanup', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $verification->update([
        'status' => VerificationStatus::Expired,
        'expires_at' => now()->subMinute(),
    ]);

    expect(fn () => commitVerificationFor($manager, $verification->fresh()))
        ->toThrow(InvalidArgumentException::class, __('csv_verification.verification.expired'));
});

test('commit rejects cross-user verification token in same center', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $cashier = actingAsCashier(
        \App\Modules\Centers\Models\Center::query()->findOrFail($verification->center_id),
    );

    expect(fn () => commitVerificationFor($cashier, $verification))
        ->toThrow(AuthorizationException::class, __('center.cross_center_forbidden'));
});

test('reject rejects cross-user verification token in same center', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $cashier = actingAsCashier(
        \App\Modules\Centers\Models\Center::query()->findOrFail($verification->center_id),
    );

    expect(fn () => app(VerificationService::class)->reject($cashier, $verification))
        ->toThrow(AuthorizationException::class, __('center.cross_center_forbidden'));
});

test('find for user hides verification token from other users in same center', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);
    $verification = startVerificationFor($manager, $center);
    $service = app(VerificationService::class);

    $otherManager = actingAsManager($center);

    expect($service->findForUser($verification->token, $otherManager, $center->id))->toBeNull();
});

test('livewire import surfaces expired error when token was rejected', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    app(VerificationService::class)->reject($manager, $verification->fresh());

    Livewire::actingAs($manager)
        ->test(CsvVerificationCard::class)
        ->set('verificationToken', $verification->token)
        ->call('import')
        ->assertHasErrors(['import' => __('csv_verification.verification.expired')]);
});

test('livewire refresh clears rejected verification token from card state', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    app(VerificationService::class)->reject($manager, $verification->fresh());

    Livewire::actingAs($manager)
        ->test(CsvVerificationCard::class)
        ->set('verificationToken', $verification->token)
        ->call('refreshVerification')
        ->assertSet('verificationToken', null);
});
