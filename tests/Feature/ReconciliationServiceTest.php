<?php

declare(strict_types=1);

use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Services\CsvInspectionService;
use App\Modules\CsvVerification\Services\FooterReaderService;
use App\Modules\CsvVerification\Services\HeaderMappingService;
use App\Modules\CsvVerification\Services\ReconciliationService;
use App\Modules\CsvVerification\Support\FooterSummary;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->seed(HeaderAliasSeeder::class);
});

/**
 * @return array{mapping: array<string, int>, delimiter: string, path: string, footer: FooterSummary}
 */
function reconciliationFixture(string $contents): array
{
    $path = storeInspectionFixture('temp/verifications/reconcile.csv', $contents);
    $inspection = app(CsvInspectionService::class)->inspect($path);
    $mapping = app(HeaderMappingService::class)->map($inspection);
    $footerResult = app(FooterReaderService::class)->readFile(
        $path,
        $inspection->delimiter,
        $mapping->mapping,
    );

    return [
        'mapping' => $mapping->mapping,
        'delimiter' => $inspection->delimiter,
        'path' => $path,
        'footer' => $footerResult->summary,
    ];
}

test('reconciliation passes when parsed totals match footer', function () {
    $contents = buildCsvFile(
        frenchCsvHeaderLine(),
        [completedFrenchDataRow(), completedFrenchDataRow(registrationDate: '02/06/2026', completionDate: '03/06/2026')],
        frenchFooterLine(2, 20_000, 3_850, 23_850),
    );
    $fixture = reconciliationFixture($contents);

    $result = app(ReconciliationService::class)->reconcile(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
        $fixture['footer'],
    );

    expect($result->isValid())->toBeTrue();
    expect($result->parsed->toArray())->toBe([
        'count' => 2,
        'ht' => 20_000,
        'vat' => 3_850,
        'ttc' => 23_850,
    ]);
});

test('reconciliation fails when record count does not match footer', function () {
    $contents = buildCsvFile(
        frenchCsvHeaderLine(),
        [completedFrenchDataRow()],
        frenchFooterLine(2, 20_000, 3_850, 23_850),
    );
    $fixture = reconciliationFixture($contents);

    $result = app(ReconciliationService::class)->reconcile(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
        $fixture['footer'],
    );

    expect($result->isValid())->toBeFalse();
    expect($result->countMatches)->toBeFalse();
    expect($result->errors)->toContain(__('csv_verification.reconciliation.count_mismatch', [
        'footer' => 2,
        'parsed' => 1,
    ]));
});

test('reconciliation fails when ht total does not match footer', function () {
    $contents = buildCsvFile(
        frenchCsvHeaderLine(),
        [completedFrenchDataRow()],
        frenchFooterLine(1, 20_000, 1_925, 21_925),
    );
    $fixture = reconciliationFixture($contents);

    $result = app(ReconciliationService::class)->reconcile(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
        $fixture['footer'],
    );

    expect($result->isValid())->toBeFalse();
    expect($result->htMatches)->toBeFalse();
});

test('reconciliation excludes invalid rows from parsed totals', function () {
    $contents = buildCsvFile(
        frenchCsvHeaderLine(),
        [
            completedFrenchDataRow(),
            completedFrenchDataRow(net: '-100', vat: '0', ttc: '-100'),
        ],
        frenchFooterLine(1, 10_000, 1_925, 11_925),
    );
    $fixture = reconciliationFixture($contents);

    $result = app(ReconciliationService::class)->reconcile(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
        $fixture['footer'],
    );

    expect($result->isValid())->toBeTrue();
    expect($result->parsed->count)->toBe(1);
});

test('process verification job stores footer summary and reconciliation on valid csv', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    runProcessVerificationJob($verification->token);

    $verification->refresh();

    expect($verification->status)->toBe(VerificationStatus::Ready);
    expect($verification->footer_summary)->toBe([
        'count' => 1,
        'ht' => 10_000,
        'vat' => 1_925,
        'ttc' => 11_925,
    ]);
    expect($verification->validation_result['reconciliation']['valid'])->toBeTrue();
    expect($verification->validation_result['footer']['valid'])->toBeTrue();
});

test('process verification job fails when footer totals do not reconcile', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        buildCsvFile(
            frenchCsvHeaderLine(),
            [completedFrenchDataRow()],
            frenchFooterLine(1, 20_000, 1_925, 21_925),
        ),
    );

    runProcessVerificationJob($verification->token);

    $verification->refresh();

    expect($verification->status)->toBe(VerificationStatus::Failed);
    expect($verification->validation_result['reconciliation']['valid'])->toBeFalse();
    expect($verification->error_message)->toContain(__('csv_verification.reconciliation.ht_mismatch', [
        'footer' => 20_000,
        'parsed' => 10_000,
    ]));
});
