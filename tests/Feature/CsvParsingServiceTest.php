<?php

declare(strict_types=1);

use App\Modules\CsvVerification\Enums\CsvRowStatus;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Services\CsvInspectionService;
use App\Modules\CsvVerification\Services\CsvParsingService;
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
function parseFixture(string $contents): array
{
    $path = storeInspectionFixture('temp/verifications/parse.csv', $contents);
    $inspection = app(CsvInspectionService::class)->inspect($path);
    $mapping = app(HeaderMappingService::class)->map($inspection);

    return [
        'mapping' => $mapping->mapping,
        'delimiter' => $inspection->delimiter,
        'path' => $path,
    ];
}

test('csv parsing streams business rows and skips footer line', function () {
    $contents = buildCsvFile(
        frenchCsvHeaderLine(),
        [
            completedFrenchDataRow(),
            completedFrenchDataRow(registrationDate: '02/06/2026', completionDate: '03/06/2026'),
        ],
        frenchFooterLine(2, 20_000, 3_850, 23_850),
    );

    $fixture = parseFixture($contents);
    $rows = iterator_to_array(app(CsvParsingService::class)->streamRows(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
    ));

    expect($rows)->toHaveCount(2);
    expect($rows[0]->status)->toBe(CsvRowStatus::Completed);
    expect($rows[0]->netAmount)->toBe(10000);
    expect($rows[0]->grossAmount)->toBe(11925);
});

test('csv parsing strips thousands separators from amounts', function () {
    $contents = buildCsvFile(frenchCsvHeaderLine(), [completedFrenchDataRow()]);
    $fixture = parseFixture($contents);

    $row = app(CsvParsingService::class)->streamRows(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
    )->current();

    expect($row->netAmount)->toBe(10000);
    expect($row->vatAmount)->toBe(1925);
});

test('csv parsing marks dash completion date as unfinished', function () {
    $rowLine = implode(';', [
        '01/06/2026',
        '10:30',
        '-',
        'ACME SARL',
        'VL',
        'C',
        'LT-123-AB',
        '0',
        '0',
        '0',
    ]);
    $contents = buildCsvFile(frenchCsvHeaderLine(), [$rowLine]);
    $fixture = parseFixture($contents);

    $row = app(CsvParsingService::class)->streamRows(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
    )->current();

    expect($row->status)->toBe(CsvRowStatus::Unfinished);
    expect($row->completionDate)->toBeNull();
});

test('csv parsing rejects negative amounts as invalid rows', function () {
    $contents = buildCsvFile(frenchCsvHeaderLine(), [
        completedFrenchDataRow(net: '-100', vat: '0', ttc: '-100'),
    ]);
    $fixture = parseFixture($contents);

    $row = app(CsvParsingService::class)->streamRows(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
    )->current();

    expect($row->status)->toBe(CsvRowStatus::Invalid);
    expect($row->errors)->toContain(__('csv_verification.parsing.negative_amount', ['field' => 'net_amount']));
});

test('csv parsing rejects rows where ht plus vat does not equal ttc', function () {
    $contents = buildCsvFile(frenchCsvHeaderLine(), [
        completedFrenchDataRow(net: '10 000', vat: '1 000', ttc: '11 925'),
    ]);
    $fixture = parseFixture($contents);

    $row = app(CsvParsingService::class)->streamRows(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
    )->current();

    expect($row->status)->toBe(CsvRowStatus::Invalid);
    expect($row->errors)->toContain(__('csv_verification.parsing.amount_mismatch'));
});

test('csv parsing tracks actual registration period across valid rows', function () {
    $contents = buildCsvFile(frenchCsvHeaderLine(), [
        completedFrenchDataRow(registrationDate: '01/01/2024', completionDate: '01/01/2024'),
        completedFrenchDataRow(registrationDate: '31/12/2024', completionDate: '31/12/2024'),
    ]);
    $fixture = parseFixture($contents);

    $summary = app(CsvParsingService::class)->parseFile(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
    )->summary;

    expect($summary->toActualPeriod())->toBe([
        'start' => '2024-01-01',
        'end' => '2024-12-31',
    ]);
});

test('csv parsing summary counts completed unfinished zero and invalid rows', function () {
    $contents = buildCsvFile(frenchCsvHeaderLine(), [
        completedFrenchDataRow(),
        completedFrenchDataRow(net: '0', vat: '0', ttc: '0'),
        implode(';', ['01/06/2026', '10:30', '-', 'ACME', 'VL', 'CV', 'LT-1', '0', '0', '0']),
        completedFrenchDataRow(net: '-1', vat: '0', ttc: '-1'),
    ]);
    $fixture = parseFixture($contents);
    $result = app(CsvParsingService::class)->parseFile(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
    );

    expect($result->summary->toRowStats())->toBe([
        'completed' => 2,
        'unfinished' => 1,
        'zero' => 1,
        'invalid' => 1,
        'total_rows' => 4,
    ]);
});

test('csv parsing stores raw row checksum on parsed rows', function () {
    $contents = buildCsvFile(frenchCsvHeaderLine(), [completedFrenchDataRow()]);
    $fixture = parseFixture($contents);

    $row = app(CsvParsingService::class)->streamRows(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
    )->current();

    expect($row->rawRowChecksum())->toHaveLength(64);
});

test('process verification job stores row stats after parsing', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        buildCsvFile(frenchCsvHeaderLine(), [completedFrenchDataRow()], frenchFooterLine(1, 10_000, 1_925, 11_925)),
    );

    runProcessVerificationJob($verification->token);

    $verification->refresh();

    expect($verification->status)->toBe(VerificationStatus::Ready);
    expect($verification->row_stats)->toBe([
        'completed' => 1,
        'unfinished' => 0,
        'zero' => 0,
        'invalid' => 0,
        'total_rows' => 1,
    ]);
    expect($verification->validation_result['parsing']['row_stats']['completed'])->toBe(1);
});
