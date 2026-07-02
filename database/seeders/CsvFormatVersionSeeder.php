<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\CsvVerification\Models\CsvFormatVersion;
use Illuminate\Database\Seeder;

class CsvFormatVersionSeeder extends Seeder
{
    public const CODE = 'cashflow_csv_v1';

    public function run(): void
    {
        CsvFormatVersion::query()->updateOrCreate(
            ['code' => self::CODE],
            [
                'name' => 'Cash Flow CSV',
                'version' => '1.0.0',
                'column_count' => 10,
                'delimiter' => ';',
                'encoding' => 'UTF-8',
                'is_active' => true,
            ],
        );
    }
}
