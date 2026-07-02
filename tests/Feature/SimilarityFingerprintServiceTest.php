<?php

declare(strict_types=1);

use App\Modules\CsvVerification\Enums\CsvRowStatus;
use App\Modules\CsvVerification\Support\ParsedCsvRow;
use App\Modules\Normalization\Services\NormalizationService;
use App\Modules\Normalization\Services\SimilarityFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('similarity fingerprint ignores customer name differences', function () {
    $service = app(SimilarityFingerprintService::class);
    $normalization = app(NormalizationService::class);

    $first = $normalization->normalizeParsedRow(new ParsedCsvRow(
        rowNumber: 2,
        rawValues: [],
        registrationDate: '2026-06-01',
        registrationTime: '10:30:00',
        completionDate: '2026-06-02',
        customerName: 'ACME SARL',
        categoryCode: 'VL',
        inspectionTypeCode: 'C',
        licencePlate: 'LT-123-AB',
        netAmount: 10_000,
        vatAmount: 1_925,
        grossAmount: 11_925,
        status: CsvRowStatus::Completed,
    ));

    $second = $normalization->normalizeParsedRow(new ParsedCsvRow(
        rowNumber: 3,
        rawValues: [],
        registrationDate: '2026-06-01',
        registrationTime: '10:30:00',
        completionDate: '2026-06-02',
        customerName: 'Acme Sarl Ltd',
        categoryCode: 'VL',
        inspectionTypeCode: 'C',
        licencePlate: 'LT-123-AB',
        netAmount: 10_000,
        vatAmount: 1_925,
        grossAmount: 11_925,
        status: CsvRowStatus::Completed,
    ));

    expect($service->fingerprint($first))->toBe($service->fingerprint($second));
    expect($first->exactCanonicalHash())->not->toBe($second->exactCanonicalHash());
});

test('similarity fingerprint changes when licence plate differs', function () {
    $service = app(SimilarityFingerprintService::class);
    $normalization = app(NormalizationService::class);

    $first = $normalization->normalizeParsedRow(new ParsedCsvRow(
        rowNumber: 2,
        rawValues: [],
        registrationDate: '2026-06-01',
        registrationTime: '10:30:00',
        completionDate: '2026-06-02',
        customerName: 'ACME SARL',
        categoryCode: 'VL',
        inspectionTypeCode: 'C',
        licencePlate: 'LT-123-AB',
        netAmount: 10_000,
        vatAmount: 1_925,
        grossAmount: 11_925,
        status: CsvRowStatus::Completed,
    ));

    $second = $normalization->normalizeParsedRow(new ParsedCsvRow(
        rowNumber: 3,
        rawValues: [],
        registrationDate: '2026-06-01',
        registrationTime: '10:30:00',
        completionDate: '2026-06-02',
        customerName: 'ACME SARL',
        categoryCode: 'VL',
        inspectionTypeCode: 'C',
        licencePlate: 'LT-456-CD',
        netAmount: 10_000,
        vatAmount: 1_925,
        grossAmount: 11_925,
        status: CsvRowStatus::Completed,
    ));

    expect($service->fingerprint($first))->not->toBe($service->fingerprint($second));
});
