<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\CsvVerification\Models\CsvFormatVersion;
use App\Modules\CsvVerification\Models\HeaderAlias;
use App\Modules\CsvVerification\Support\HeaderNormalizer;
use Illuminate\Database\Seeder;

class HeaderAliasSeeder extends Seeder
{
    /**
     * @var array<string, array<string, list<string>>>
     */
    private const ALIASES = [
        'fr' => [
            'registration_date' => ['Date Enregistrement'],
            'registration_time' => ["Heure d'enregistrement"],
            'completion_date' => ["Date de fin d'inspection"],
            'customer_name' => ['Client'],
            'category_code' => ['Cat.'],
            'inspection_type_code' => ['Type'],
            'licence_plate' => ['Immatriculation'],
            'net_amount' => ['Montant Hors Taxe'],
            'vat_amount' => ['Montant de la TVA'],
            'gross_amount' => ['Montant TTC'],
        ],
        'en' => [
            'registration_date' => ['Regitration date', 'Registration date'],
            'registration_time' => ['Regitration hour', 'Registration hour', 'Registration time'],
            'completion_date' => ['Inspection completion date'],
            'customer_name' => ['Customer'],
            'category_code' => ['Cat.'],
            'inspection_type_code' => ['Type'],
            'licence_plate' => ['Licence plate'],
            'net_amount' => ['Amount Ex. VAT'],
            'vat_amount' => ['Amount of VAT'],
            'gross_amount' => ['Amount Inc. VAT'],
        ],
    ];

    public function run(): void
    {
        $format = CsvFormatVersion::query()
            ->where('code', CsvFormatVersionSeeder::CODE)
            ->first();

        if ($format === null) {
            $this->call(CsvFormatVersionSeeder::class);
            $format = CsvFormatVersion::query()
                ->where('code', CsvFormatVersionSeeder::CODE)
                ->firstOrFail();
        }

        foreach (self::ALIASES as $language => $fields) {
            foreach ($fields as $canonicalField => $sourceHeaders) {
                foreach ($sourceHeaders as $sourceHeader) {
                    HeaderAlias::query()->updateOrCreate(
                        [
                            'csv_format_version_id' => $format->id,
                            'language' => $language,
                            'source_header' => $sourceHeader,
                        ],
                        [
                            'canonical_field' => $canonicalField,
                            'normalized_header' => HeaderNormalizer::normalize($sourceHeader),
                            'is_required' => true,
                            'is_active' => true,
                            'created_by' => null,
                        ],
                    );
                }
            }
        }
    }
}
