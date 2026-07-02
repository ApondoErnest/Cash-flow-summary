<?php

declare(strict_types=1);

use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\CsvVerification\Services\VerificationCleanupService;
use App\Modules\CsvVerification\Services\VerificationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    config(['csv_verification.cleanup_batch_size' => 100]);
    test()->seed(\Database\Seeders\HeaderAliasSeeder::class);
});

test('verification cleanup expires due pending and ready records and deletes temp files', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    $pending = startVerificationFor($manager, $center);
    $ready = startVerificationFor($manager, $center, verificationReadyFrenchCsv([completedFrenchDataRow()]));
    runProcessVerificationJob($ready->token);

    $pending->update(['expires_at' => now()->subMinute()]);
    $ready->update(['expires_at' => now()->subMinute(), 'status' => VerificationStatus::Ready]);

    $result = app(VerificationCleanupService::class)->run();

    expect($result->expired)->toBe(2);
    expect($result->filesDeleted)->toBe(2);
    expect($pending->fresh()->status)->toBe(VerificationStatus::Expired);
    expect($ready->fresh()->status)->toBe(VerificationStatus::Expired);
    Storage::disk('local')->assertMissing($pending->temp_storage_path);
    Storage::disk('local')->assertMissing($ready->temp_storage_path);
});

test('verification cleanup does not expire imported rejected or already expired records', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);
    $service = app(VerificationCleanupService::class);

    foreach ([VerificationStatus::Imported, VerificationStatus::Rejected, VerificationStatus::Expired] as $status) {
        $verification = startVerificationFor($manager, $center);
        $verification->update([
            'expires_at' => now()->subMinute(),
            'status' => $status,
        ]);
    }

    $result = $service->run();

    expect($result->expired)->toBe(0);
    expect(ImportVerification::query()->where('status', VerificationStatus::Imported)->count())->toBe(1);
    expect(ImportVerification::query()->where('status', VerificationStatus::Rejected)->count())->toBe(1);
    expect(ImportVerification::query()->where('status', VerificationStatus::Expired)->count())->toBe(1);
});

test('verification cleanup removes orphaned temp files for rejected records', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);
    $verification = startVerificationFor($manager, $center);

    $verification->update([
        'status' => VerificationStatus::Rejected,
        'rejected_at' => now(),
    ]);

    Storage::disk('local')->assertExists($verification->temp_storage_path);

    $result = app(VerificationCleanupService::class)->run();

    expect($result->orphanFilesDeleted)->toBe(1);
    Storage::disk('local')->assertMissing($verification->temp_storage_path);
});

test('verification service reject deletes temp file marks rejected and writes audit log', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);
    $verification = startVerificationFor($manager, $center, verificationReadyFrenchCsv([completedFrenchDataRow()]));

    runProcessVerificationJob($verification->token);
    $verification = $verification->fresh();

    expect($verification->status)->toBe(VerificationStatus::Ready);

    $rejected = app(VerificationService::class)->reject($manager, $verification);

    expect($rejected->status)->toBe(VerificationStatus::Rejected);
    expect($rejected->rejected_at)->not->toBeNull();
    Storage::disk('local')->assertMissing($verification->temp_storage_path);

    $audit = AuditLog::query()->where('event', 'verification.rejected')->first();

    expect($audit)->not->toBeNull();
    expect($audit->new_values)->toBe([
        'token' => $verification->token,
        'filename' => $verification->original_filename,
    ]);
    expect($audit->new_values)->not->toHaveKey('contents');
});

test('verification service reject refuses pending processing imported and expired records', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);
    $service = app(VerificationService::class);

    foreach ([VerificationStatus::Pending, VerificationStatus::Processing, VerificationStatus::Imported] as $status) {
        $verification = startVerificationFor($manager, $center);
        $verification->update(['status' => $status]);

        expect(fn () => $service->reject($manager, $verification->fresh()))
            ->toThrow(InvalidArgumentException::class);
    }

    $expired = startVerificationFor($manager, $center);
    $expired->update([
        'status' => VerificationStatus::Ready,
        'expires_at' => now()->subMinute(),
    ]);

    expect(fn () => $service->reject($manager, $expired->fresh()))
        ->toThrow(InvalidArgumentException::class, __('csv_verification.verification.expired'));
});

test('csv verification cleanup command runs scheduled cleanup service', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);
    $verification = startVerificationFor($manager, $center);
    $verification->update(['expires_at' => now()->subMinute()]);

    Artisan::call('csv-verification:cleanup');

    expect(Artisan::output())->toContain('Expired 1 verification(s)');
    expect($verification->fresh()->status)->toBe(VerificationStatus::Expired);
});

test('csv verification cleanup command is scheduled every fifteen minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($scheduledEvent) => str_contains((string) ($scheduledEvent->command ?? ''), 'csv-verification:cleanup'));

    expect($event)->not->toBeNull();
    expect($event->expression)->toBe('*/15 * * * *');
});
