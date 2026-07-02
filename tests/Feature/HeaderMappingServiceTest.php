<?php

declare(strict_types=1);

use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Jobs\ProcessVerificationJob;
use App\Modules\CsvVerification\Services\CsvInspectionService;
use App\Modules\CsvVerification\Services\HeaderMappingService;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->seed(HeaderAliasSeeder::class);
});

function mapHeaders(string $headerLine): \App\Modules\CsvVerification\Support\HeaderMappingResult
{
    $inspection = app(CsvInspectionService::class)->inspect(
        storeInspectionFixture('temp/verifications/map.csv', csvFixture($headerLine)),
    );

    return app(HeaderMappingService::class)->map($inspection);
}

function mixedLanguageHeaderLine(): string
{
    $headers = explode(';', frenchCsvHeaderLine());
    $headers[3] = 'Customer';

    return implode(';', $headers);
}

test('header mapping maps french headers to canonical fields', function () {
    $result = mapHeaders(frenchCsvHeaderLine());

    expect($result->isValid())->toBeTrue();
    expect($result->language)->toBe('fr');
    expect($result->mapping)->toHaveCount(10);
    expect($result->mapping['customer_name'])->toBe(3);
    expect($result->mapping['registration_date'])->toBe(0);
});

test('header mapping maps english headers to canonical fields', function () {
    $result = mapHeaders(englishCsvHeaderLine());

    expect($result->isValid())->toBeTrue();
    expect($result->language)->toBe('en');
    expect($result->mapping['licence_plate'])->toBe(6);
    expect($result->mapping['gross_amount'])->toBe(9);
});

test('header mapping rejects mixed french and english headers', function () {
    $result = mapHeaders(mixedLanguageHeaderLine());

    expect($result->isValid())->toBeFalse();
    expect($result->isMixedLanguage)->toBeTrue();
    expect($result->errors)->toContain(__('csv_verification.mapping.mixed_language'));
});

test('header mapping rejects unknown required headers', function () {
    $headers = explode(';', frenchCsvHeaderLine());
    $headers[7] = 'Montant HT Inconnu';
    $result = mapHeaders(implode(';', $headers));

    expect($result->isValid())->toBeFalse();
    expect($result->unknownHeaders)->toContain('Montant HT Inconnu');
    expect($result->errors[0])->toContain('Montant HT Inconnu');
});

test('header mapping suggests close matches for unknown headers', function () {
    $headers = explode(';', frenchCsvHeaderLine());
    $headers[7] = 'Montant Hors Taxes';
    $result = mapHeaders(implode(';', $headers));

    expect($result->isValid())->toBeFalse();
    expect($result->suggestions['Montant Hors Taxes'] ?? [])->toContain('net_amount');
});

test('process verification job fails on mixed language headers', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        csvFixture(mixedLanguageHeaderLine()),
    );

    runProcessVerificationJob($verification->token);

    $verification->refresh();

    expect($verification->status)->toBe(VerificationStatus::Failed);
    expect($verification->error_message)->toContain(__('csv_verification.mapping.mixed_language'));
    expect($verification->validation_result['header_mapping']['is_mixed_language'])->toBeTrue();
});

test('process verification job stores canonical mapping for valid french csv', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([]),
    );

    runProcessVerificationJob($verification->token);

    $verification->refresh();

    expect($verification->status)->toBe(VerificationStatus::Ready);
    expect($verification->source_language)->toBe('fr');
    expect($verification->validation_result['header_mapping']['valid'])->toBeTrue();
    expect($verification->validation_result['header_mapping']['mapping']['customer_name'])->toBe(3);
});
