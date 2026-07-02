<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Jobs\ProcessVerificationJob;
use App\Modules\CsvVerification\Services\CsvInspectionService;
use App\Modules\CsvVerification\Services\VerificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    config(['csv_verification.ttl_minutes' => 120]);
    test()->seed(\Database\Seeders\HeaderAliasSeeder::class);
});

test('verification service creates tokenized record with ttl and pending status', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    $verification = startVerificationFor($manager, $center);

    expect($verification->token)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    expect($verification->status)->toBe(VerificationStatus::Pending);
    expect($verification->user_id)->toBe($manager->id);
    expect($verification->center_id)->toBe($center->id);
    expect($verification->original_filename)->toBe('cashflow-june.csv');
    expect($verification->expires_at->greaterThan(now()->addMinutes(119)))->toBeTrue();
    expect($verification->expires_at->lessThanOrEqualTo(now()->addMinutes(120)))->toBeTrue();
});

test('verification service stores temp file on private disk and records hash metadata', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);
    $contents = sampleCsvContents();

    $verification = startVerificationFor($manager, $center, $contents);

    Storage::disk('local')->assertExists($verification->temp_storage_path);
    expect(Storage::disk('local')->get($verification->temp_storage_path))->toBe($contents);
    expect($verification->file_hash)->toBe(hash('sha256', $contents));
    expect($verification->file_size)->toBe(strlen($contents));
    expect($verification->temp_storage_path)->toStartWith('temp/verifications/');
});

test('verification service dispatches process verification job', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    $verification = startVerificationFor($manager, $center);

    Queue::assertPushed(ProcessVerificationJob::class, function (ProcessVerificationJob $job) use ($verification): bool {
        return $job->token === $verification->token;
    });
});

test('verification service allows owner to verify for active center only', function () {
    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization);
    setOwnerActiveCenter($owner, $center);

    $verification = startVerificationFor($owner, $center);

    expect($verification->center_id)->toBe($center->id);

    $otherCenter = createTestCenter($owner->organization, ['code' => 'OTHER-'.uniqid()]);

    expect(fn () => startVerificationFor($owner, $otherCenter))
        ->toThrow(AuthorizationException::class);
});

test('verification service rejects manager verifying for another center', function () {
    $center = createTestCenter();
    $otherCenter = createTestCenter($center->organization, ['code' => 'ALT-'.uniqid()]);
    actingAsManager($center);

    expect(fn () => startVerificationFor(auth()->user(), $otherCenter))
        ->toThrow(AuthorizationException::class);
});

test('verification service finds token scoped to user and center', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);
    $service = app(VerificationService::class);

    $verification = startVerificationFor($manager, $center);

    expect($service->findForUser($verification->token, $manager, $center->id)?->is($verification))->toBeTrue();
    expect($service->findForUser($verification->token, $manager, $center->id + 1))->toBeNull();

    $otherManager = actingAsManager($center);
    expect($service->findForUser($verification->token, $otherManager, $center->id))->toBeNull();
});

test('verification service ttl follows configured minutes', function () {
    config(['csv_verification.ttl_minutes' => 45]);

    expect(app(VerificationService::class)->ttlMinutes())->toBe(45);
});

test('process verification job runs inspection and keeps processing on valid csv', function () {
    Queue::fake();
    $center = createTestCenter();
    $manager = actingAsManager($center);
    $verification = startVerificationFor($manager, $center, verificationReadyFrenchCsv([]));

    runProcessVerificationJob($verification->token);

    expect($verification->fresh()->status)->toBe(VerificationStatus::Ready);
    expect($verification->fresh()->source_language)->toBe('fr');
});
