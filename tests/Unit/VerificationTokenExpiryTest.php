<?php

declare(strict_types=1);

use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\CsvVerification\Services\VerificationService;
use Illuminate\Support\Carbon;

test('verification token expiry treats rejected status and expired status as invalid', function () {
    $service = app(VerificationService::class);

    $rejected = new ImportVerification([
        'status' => VerificationStatus::Rejected,
        'expires_at' => now()->addHour(),
    ]);

    $expired = new ImportVerification([
        'status' => VerificationStatus::Expired,
        'expires_at' => now()->addHour(),
    ]);

    expect($service->isExpired($rejected))->toBeTrue()
        ->and($service->isExpired($expired))->toBeTrue();
});

test('verification token expiry treats past ttl as invalid while ready future ttl remains valid', function () {
    Carbon::setTestNow('2026-06-01 12:00:00');

    $service = app(VerificationService::class);

    $ready = new ImportVerification([
        'status' => VerificationStatus::Ready,
        'expires_at' => now()->addHour(),
    ]);

    $pastTtl = new ImportVerification([
        'status' => VerificationStatus::Ready,
        'expires_at' => now()->subMinute(),
    ]);

    expect($service->isExpired($ready))->toBeFalse()
        ->and($service->isExpired($pastTtl))->toBeTrue();
});

test('verification token expiry ignores imported status when ttl has not passed', function () {
    $service = app(VerificationService::class);

    $imported = new ImportVerification([
        'status' => VerificationStatus::Imported,
        'expires_at' => now()->addHour(),
    ]);

    expect($service->isExpired($imported))->toBeFalse();
});
