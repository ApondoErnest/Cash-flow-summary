<?php

declare(strict_types=1);

use App\Modules\CsvVerification\Enums\CsvRowStatus;
use App\Modules\CsvVerification\Support\ParsedCsvRow;
use App\Modules\Normalization\NormalizationPolicy;
use App\Modules\Normalization\Services\NormalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('normalization service applies field specific v1 licence plate rules', function () {
    $service = app(NormalizationService::class);

    expect($service->normalizeLicencePlate('AB-123'))->toBe('AB123');
    expect($service->normalizeLicencePlate('ab 123'))->toBe('AB123');
    expect($service->normalizeLicencePlate('AB 123'))->toBe('AB123');
    expect($service->normalizeLicencePlate('lt.456/xy'))->toBe('LT456XY');
});

test('normalization service applies field specific v1 customer name rules', function () {
    $service = app(NormalizationService::class);

    expect($service->normalizeCustomerName('  acme   sarl  '))->toBe('ACME SARL');
    expect($service->normalizeCustomerName("O'Connor"))->toBe("O'CONNOR");
    expect($service->normalizeCustomerName('Société – Demo'))->toBe('SOCIÉTÉ - DEMO');
});

test('normalization service preserves category code display with trim only', function () {
    $service = app(NormalizationService::class);

    expect($service->normalizeCategoryCode(' VL '))->toBe('VL');
    expect($service->normalizeCategoryCode('Cat.'))->toBe('Cat.');
});

test('normalization service uppercases inspection type code', function () {
    $service = app(NormalizationService::class);

    expect($service->normalizeInspectionTypeCode(' cv '))->toBe('CV');
    expect($service->normalizeInspectionTypeCode('c'))->toBe('C');
});

test('normalization service normalizes parsed csv rows into canonical records', function () {
    $service = app(NormalizationService::class);

    $row = new ParsedCsvRow(
        rowNumber: 2,
        rawValues: [
            'registration_date' => '01/06/2026',
            'registration_time' => '10:30',
            'completion_date' => '02/06/2026',
            'customer_name' => 'ACME SARL',
            'category_code' => 'VL',
            'inspection_type_code' => 'C',
            'licence_plate' => 'LT-123-AB',
            'net_amount' => '10 000',
            'vat_amount' => '1 925',
            'gross_amount' => '11 925',
        ],
        registrationDate: '2026-06-01',
        registrationTime: '10:30:00',
        completionDate: '2026-06-02',
        customerName: 'ACME SARL',
        categoryCode: 'VL',
        inspectionTypeCode: 'C',
        licencePlate: 'LT-123-AB',
        netAmount: 10000,
        vatAmount: 1925,
        grossAmount: 11925,
        status: CsvRowStatus::Completed,
    );

    $canonical = $service->normalizeParsedRow($row);

    expect($canonical->licencePlate)->toBe('LT123AB');
    expect($canonical->customerName)->toBe('ACME SARL');
    expect($canonical->registrationDate)->toBe('2026-06-01');
    expect($canonical->normalizationPolicyVersion)->toBe(NormalizationPolicy::VERSION);
});

test('normalization service produces deterministic exact canonical hash', function () {
    $service = app(NormalizationService::class);

    $row = new ParsedCsvRow(
        rowNumber: 2,
        rawValues: [],
        registrationDate: '2026-06-01',
        registrationTime: '10:30:00',
        completionDate: '2026-06-02',
        customerName: 'ACME SARL',
        categoryCode: 'VL',
        inspectionTypeCode: 'C',
        licencePlate: 'AB-123',
        netAmount: 10000,
        vatAmount: 1925,
        grossAmount: 11925,
        status: CsvRowStatus::Completed,
    );

    $first = $service->normalizeParsedRow($row);
    $second = $service->normalizeParsedRow(new ParsedCsvRow(
        rowNumber: 3,
        rawValues: [],
        registrationDate: '2026-06-01',
        registrationTime: '10:30:00',
        completionDate: '2026-06-02',
        customerName: '  acme   sarl  ',
        categoryCode: 'VL',
        inspectionTypeCode: 'c',
        licencePlate: 'ab 123',
        netAmount: 10000,
        vatAmount: 1925,
        grossAmount: 11925,
        status: CsvRowStatus::Completed,
    ));

    expect($first->exactCanonicalHash())->toBe($second->exactCanonicalHash());
    expect($first->exactCanonicalHash())->toHaveLength(64);
});

test('process verification job records normalization policy in validation result', function () {
    Storage::fake('local');
    test()->seed(\Database\Seeders\HeaderAliasSeeder::class);

    $center = createTestCenter();
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    runProcessVerificationJob($verification->token);

    $verification->refresh();

    expect($verification->validation_result['normalization']['policy'])->toBe(NormalizationPolicy::VERSION);
    expect($verification->validation_result['normalization']['normalized_rows'])->toBe(1);
});
