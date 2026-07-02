<?php

declare(strict_types=1);

use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->seed(HeaderAliasSeeder::class);
});

test('generate csv fixture catalogue files', function () {
    $directory = csvFixturePath('.');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $fixtures = [
        'sample_fr_valid.csv' => reconciledFrenchCsv([
            completedFrenchDataRow(),
            completedFrenchDataRow(registrationDate: '02/06/2026', completionDate: '03/06/2026'),
        ]),
        'sample_fr_production_footer.csv' => reconciledFrenchCsv([completedFrenchDataRow()]),
        'sample_en_valid.csv' => reconciledEnglishCsv([completedFrenchDataRow()], englishCsvHeaderLineTypo()),
        'sample_real_patterns.csv' => reconciledFrenchCsv(realPatternDataRows()),
        'duplicate_in_file.csv' => reconciledFrenchCsv([
            completedFrenchDataRow(),
            completedFrenchDataRow(),
        ]),
        'duplicate_historical.csv' => reconciledFrenchCsv([completedFrenchDataRow()]),
        'all_duplicate.csv' => reconciledFrenchCsv([
            completedFrenchDataRow(),
            completedFrenchDataRow(),
        ]),
        'missing_footer.csv' => buildCsvFile(frenchCsvHeaderLine(), [completedFrenchDataRow()]),
        'missing_header.csv' => "\xEF\xBB\xBF".completedFrenchDataRow()."\n",
        'invalid_date.csv' => reconciledFrenchCsv([
            completedFrenchDataRow(registrationDate: 'not-a-date'),
            completedFrenchDataRow(registrationDate: '02/06/2026', completionDate: '03/06/2026'),
        ]),
        'invalid_amount.csv' => reconciledFrenchCsv([
            completedFrenchDataRow(net: '-100', vat: '0', ttc: '-100'),
            completedFrenchDataRow(),
        ]),
        'financial_mismatch.csv' => buildCsvFile(
            frenchCsvHeaderLine(),
            [completedFrenchDataRow()],
            frenchFooterLine(1, 99_999, 99_999, 99_999),
        ),
        'zero_value_rows.csv' => reconciledFrenchCsv([
            completedFrenchDataRow(net: '0', vat: '0', ttc: '0'),
            frenchDataRow('01/06/2026', '10:30', '-', 'ACME', 'B', 'CV', 'LT 1', '0', '0', '0'),
        ]),
        'mixed_headers.csv' => buildCsvFile(
            "Date Enregistrement;Registration hour;Date de fin d'inspection;Client;Cat.;Type;Immatriculation;Montant Hors Taxe;Montant de la TVA;Montant TTC",
            [completedFrenchDataRow()],
            frenchFooterLine(1, 10_000, 1_925, 11_925),
        ),
        'probable_duplicate_customer.csv' => reconciledFrenchCsv([
            completedFrenchDataRow(),
            frenchDataRow('01/06/2026', '10:30', '02/06/2026', 'Acme Sarl Ltd', 'VL', 'C', 'LT-123-AB', '10 000', '1 925', '11 925'),
        ]),
        'multi_day_period.csv' => reconciledFrenchCsv([
            completedFrenchDataRow(registrationDate: '01/01/2024', completionDate: '01/01/2024'),
            completedFrenchDataRow(registrationDate: '31/12/2024', completionDate: '31/12/2024'),
        ]),
    ];

    foreach ($fixtures as $filename => $contents) {
        file_put_contents(csvFixturePath($filename), $contents);
    }

    expect($fixtures)->toHaveCount(16);
})->skip('One-off fixture generator — run manually when catalogue changes');
