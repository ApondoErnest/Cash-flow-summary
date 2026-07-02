<?php

declare(strict_types=1);

use App\Modules\CsvVerification\Services\CsvInspectionService;
use App\Modules\CsvVerification\Services\FooterReaderService;
use App\Modules\CsvVerification\Services\HeaderMappingService;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->seed(HeaderAliasSeeder::class);
});

/**
 * @return array{mapping: array<string, int>, delimiter: string, path: string}
 */
function footerFixture(string $contents): array
{
    $path = storeInspectionFixture('temp/verifications/footer.csv', $contents);
    $inspection = app(CsvInspectionService::class)->inspect($path);
    $mapping = app(HeaderMappingService::class)->map($inspection);

    return [
        'mapping' => $mapping->mapping,
        'delimiter' => $inspection->delimiter,
        'path' => $path,
    ];
}

test('footer reader extracts french footer totals', function () {
    $contents = buildCsvFile(
        frenchCsvHeaderLine(),
        [completedFrenchDataRow(), completedFrenchDataRow(registrationDate: '02/06/2026', completionDate: '03/06/2026')],
        frenchFooterLine(2, 20_000, 3_850, 23_850),
    );
    $fixture = footerFixture($contents);

    $result = app(FooterReaderService::class)->readFile(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
    );

    expect($result->isValid())->toBeTrue();
    expect($result->summary?->toArray())->toBe([
        'count' => 2,
        'ht' => 20_000,
        'vat' => 3_850,
        'ttc' => 23_850,
    ]);
});

test('footer reader extracts english footer totals', function () {
    $contents = buildCsvFile(
        englishCsvHeaderLine(),
        [completedFrenchDataRow()],
        englishFooterLine(1, 10_000, 1_925, 11_925),
    );
    $fixture = footerFixture($contents);

    $result = app(FooterReaderService::class)->readFile(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
    );

    expect($result->isValid())->toBeTrue();
    expect($result->summary?->count)->toBe(1);
    expect($result->summary?->ttc)->toBe(11_925);
});

test('footer reader fails when footer row is missing', function () {
    $contents = buildCsvFile(frenchCsvHeaderLine(), [completedFrenchDataRow()]);
    $fixture = footerFixture($contents);

    $result = app(FooterReaderService::class)->readFile(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
    );

    expect($result->isValid())->toBeFalse();
    expect($result->errors)->toContain(__('csv_verification.footer.missing'));
});
