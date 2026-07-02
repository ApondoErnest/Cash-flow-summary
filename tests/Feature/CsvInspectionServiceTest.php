<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Jobs\ProcessVerificationJob;
use App\Modules\CsvVerification\Services\CsvInspectionService;
use App\Modules\CsvVerification\Services\VerificationService;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->seed(HeaderAliasSeeder::class);
});

test('csv inspection detects utf-8 bom semicolon delimiter and french language', function () {
    $path = storeInspectionFixture(
        'temp/verifications/fr-sample.csv',
        csvFixture(frenchCsvHeaderLine()),
    );

    $result = app(CsvInspectionService::class)->inspect($path);

    expect($result->isValid())->toBeTrue();
    expect($result->encoding)->toBe('UTF-8');
    expect($result->hasBom)->toBeTrue();
    expect($result->delimiter)->toBe(';');
    expect($result->language)->toBe('fr');
    expect($result->columnCount)->toBe(10);
    expect($result->headers[0])->toBe('Date Enregistrement');
});

test('csv inspection detects english language headers', function () {
    $path = storeInspectionFixture(
        'temp/verifications/en-sample.csv',
        csvFixture(englishCsvHeaderLine()),
    );

    $result = app(CsvInspectionService::class)->inspect($path);

    expect($result->isValid())->toBeTrue();
    expect($result->language)->toBe('en');
    expect($result->headers[3])->toBe('Customer');
});

test('csv inspection accepts regitration spelling variant as english', function () {
    $headerLine = 'Regitration date;Regitration hour;Inspection completion date;Customer;Cat.;Type;Licence plate;Amount Ex. VAT;Amount of VAT;Amount Inc. VAT';
    $path = storeInspectionFixture(
        'temp/verifications/en-regitration.csv',
        csvFixture($headerLine),
    );

    $result = app(CsvInspectionService::class)->inspect($path);

    expect($result->isValid())->toBeTrue();
    expect($result->language)->toBe('en');
});

test('csv inspection rejects missing bom', function () {
    $path = storeInspectionFixture(
        'temp/verifications/no-bom.csv',
        csvFixture(frenchCsvHeaderLine(), withBom: false),
    );

    $result = app(CsvInspectionService::class)->inspect($path);

    expect($result->isValid())->toBeFalse();
    expect($result->errors)->toContain(__('csv_verification.inspection.missing_bom'));
});

test('csv inspection rejects comma delimiter', function () {
    $path = storeInspectionFixture(
        'temp/verifications/comma.csv',
        csvFixture(str_replace(';', ',', frenchCsvHeaderLine())),
    );

    $result = app(CsvInspectionService::class)->inspect($path);

    expect($result->isValid())->toBeFalse();
    expect($result->errors)->toContain(__('csv_verification.inspection.invalid_delimiter'));
});

test('csv inspection rejects invalid utf-8 content', function () {
    $path = storeInspectionFixture(
        'temp/verifications/invalid-encoding.csv',
        "\xEF\xBB\xBF".frenchCsvHeaderLine()."\n"."\xC3\x28",
    );

    $result = app(CsvInspectionService::class)->inspect($path);

    expect($result->isValid())->toBeFalse();
    expect($result->errors)->toContain(__('csv_verification.inspection.invalid_encoding'));
});

test('csv inspection rejects incorrect column count', function () {
    $path = storeInspectionFixture(
        'temp/verifications/short-header.csv',
        csvFixture('Date Enregistrement;Client;Cat.'),
    );

    $result = app(CsvInspectionService::class)->inspect($path);

    expect($result->isValid())->toBeFalse();
    expect($result->errors)->toContain(__('csv_verification.inspection.invalid_column_count', ['count' => 10]));
});

test('process verification job persists successful inspection metadata', function () {
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
    expect($verification->encoding)->toBe('UTF-8');
    expect($verification->delimiter)->toBe(';');
    expect($verification->source_language)->toBe('fr');
    expect($verification->validation_result['inspection']['valid'])->toBeTrue();
    expect($verification->validation_result['header_mapping']['valid'])->toBeTrue();
});

test('process verification job marks failed inspection on invalid csv', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        csvFixture(frenchCsvHeaderLine(), withBom: false),
    );

    runProcessVerificationJob($verification->token);

    $verification->refresh();

    expect($verification->status)->toBe(VerificationStatus::Failed);
    expect($verification->error_message)->toContain(__('csv_verification.inspection.missing_bom'));
    expect($verification->validation_result['inspection']['valid'])->toBeFalse();
});
