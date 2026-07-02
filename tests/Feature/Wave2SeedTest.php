<?php

declare(strict_types=1);

use App\Modules\CsvVerification\Models\CsvFormatVersion;
use App\Modules\CsvVerification\Models\HeaderAlias;
use App\Modules\CsvVerification\Support\HeaderNormalizer;
use Database\Seeders\CsvFormatVersionSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('header normalizer applies csv specification matching rules', function () {
    expect(HeaderNormalizer::normalize("  Date\xEF\xBB\xBF Enregistrement  "))->toBe('date enregistrement');
    expect(HeaderNormalizer::normalize('Amount   Ex.   VAT'))->toBe('amount ex. vat');
    expect(HeaderNormalizer::normalize('Regitration date'))->toBe('regitration date');
});

test('wave 2 seed creates active csv format version', function () {
    $this->seed(CsvFormatVersionSeeder::class);

    $format = CsvFormatVersion::query()->where('code', CsvFormatVersionSeeder::CODE)->first();

    expect($format)->not->toBeNull();
    expect($format->is_active)->toBeTrue();
    expect($format->column_count)->toBe(10);
    expect($format->delimiter)->toBe(';');
    expect($format->encoding)->toBe('UTF-8');
});

test('wave 2 seed loads french and english header aliases', function () {
    $this->seed(HeaderAliasSeeder::class);

    $format = CsvFormatVersion::query()->where('code', CsvFormatVersionSeeder::CODE)->firstOrFail();

    $canonicalFields = [
        'registration_date',
        'registration_time',
        'completion_date',
        'customer_name',
        'category_code',
        'inspection_type_code',
        'licence_plate',
        'net_amount',
        'vat_amount',
        'gross_amount',
    ];

    foreach ($canonicalFields as $field) {
        expect(
            HeaderAlias::query()
                ->where('csv_format_version_id', $format->id)
                ->where('language', 'fr')
                ->where('canonical_field', $field)
                ->where('is_active', true)
                ->exists()
        )->toBeTrue("Missing French alias for {$field}");

        expect(
            HeaderAlias::query()
                ->where('csv_format_version_id', $format->id)
                ->where('language', 'en')
                ->where('canonical_field', $field)
                ->where('is_active', true)
                ->exists()
        )->toBeTrue("Missing English alias for {$field}");
    }
});

test('wave 2 seed includes regitration and registration spelling variants', function () {
    $this->seed(HeaderAliasSeeder::class);

    $format = CsvFormatVersion::query()->where('code', CsvFormatVersionSeeder::CODE)->firstOrFail();

    $englishDateHeaders = HeaderAlias::query()
        ->where('csv_format_version_id', $format->id)
        ->where('language', 'en')
        ->where('canonical_field', 'registration_date')
        ->pluck('source_header')
        ->all();

    expect($englishDateHeaders)->toEqualCanonicalizing(['Regitration date', 'Registration date']);

    $englishTimeHeaders = HeaderAlias::query()
        ->where('csv_format_version_id', $format->id)
        ->where('language', 'en')
        ->where('canonical_field', 'registration_time')
        ->pluck('source_header')
        ->all();

    expect($englishTimeHeaders)->toEqualCanonicalizing([
        'Regitration hour',
        'Registration hour',
        'Registration time',
    ]);
});

test('wave 2 seed stores normalized headers for matching', function () {
    $this->seed(HeaderAliasSeeder::class);

    $alias = HeaderAlias::query()
        ->where('source_header', 'Montant Hors Taxe')
        ->firstOrFail();

    expect($alias->normalized_header)->toBe(HeaderNormalizer::normalize('Montant Hors Taxe'));
    expect($alias->language)->toBe('fr');
    expect($alias->canonical_field)->toBe('net_amount');
});

test('wave 2 seed is idempotent', function () {
    $this->seed(HeaderAliasSeeder::class);
    $this->seed(HeaderAliasSeeder::class);

    $format = CsvFormatVersion::query()->where('code', CsvFormatVersionSeeder::CODE)->firstOrFail();

    expect(CsvFormatVersion::query()->where('code', CsvFormatVersionSeeder::CODE)->count())->toBe(1);
    expect(HeaderAlias::query()->where('csv_format_version_id', $format->id)->count())->toBe(23);
});

test('database seeder loads wave 2 csv configuration', function () {
    $this->seed(DatabaseSeeder::class);

    expect(CsvFormatVersion::query()->where('code', CsvFormatVersionSeeder::CODE)->where('is_active', true)->exists())->toBeTrue();
    expect(HeaderAlias::query()->count())->toBe(23);
});
