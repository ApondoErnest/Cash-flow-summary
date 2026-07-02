<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\CsvImports\Enums\CompletionStatus;
use App\Modules\CsvImports\Enums\FinancialStatus;
use App\Modules\CsvImports\Enums\ImportRowStatus;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportRow;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\CsvVerification\Jobs\ProcessVerificationJob;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\CsvVerification\Services\CsvInspectionService;
use App\Modules\CsvVerification\Services\CsvParsingService;
use App\Modules\CsvVerification\Services\DuplicatePreviewService;
use App\Modules\CsvVerification\Services\FooterReaderService;
use App\Modules\CsvVerification\Services\HeaderMappingService;
use App\Modules\CsvVerification\Services\ReconciliationService;
use App\Modules\CsvVerification\Services\VerificationService;
use App\Modules\CsvVerification\Support\CsvInspectionResult;
use App\Modules\Normalization\Support\CanonicalRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function frenchCsvHeaderLine(): string
{
    return "Date Enregistrement;Heure d'enregistrement;Date de fin d'inspection;Client;Cat.;Type;Immatriculation;Montant Hors Taxe;Montant de la TVA;Montant TTC";
}

function englishCsvHeaderLine(): string
{
    return 'Registration date;Registration hour;Inspection completion date;Customer;Cat.;Type;Licence plate;Amount Ex. VAT;Amount of VAT;Amount Inc. VAT';
}

function englishCsvHeaderLineTypo(): string
{
    return 'Regitration date;Regitration hour;Inspection completion date;Customer;Cat.;Type;Licence plate;Amount Ex. VAT;Amount of VAT;Amount Inc. VAT';
}

function csvFixturePath(string $filename): string
{
    return base_path('tests/fixtures/csv/'.$filename);
}

function loadCsvFixture(string $filename): string
{
    $path = csvFixturePath($filename);

    if (! is_readable($path)) {
        throw new RuntimeException("CSV fixture not found: {$filename}");
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("CSV fixture could not be read: {$filename}");
    }

    return $contents;
}

/**
 * @return array{mapping: array<string, int>, delimiter: string, path: string, inspection: CsvInspectionResult}
 */
function parseCsvFixture(string $contents, string $relativePath = 'temp/verifications/parse-fixture.csv'): array
{
    $path = storeInspectionFixture($relativePath, $contents);
    $inspection = app(CsvInspectionService::class)->inspect($path);
    $mapping = app(HeaderMappingService::class)->map($inspection);

    return [
        'mapping' => $mapping->mapping,
        'delimiter' => $inspection->delimiter,
        'path' => $path,
        'inspection' => $inspection,
    ];
}

/**
 * @param  list<string>  $dataRows
 */
function reconciledCsv(string $headerLine, array $dataRows, callable $footerLineBuilder): string
{
    $withoutFooter = buildCsvFile($headerLine, $dataRows);
    $fixture = parseCsvFixture($withoutFooter);
    $totals = app(ReconciliationService::class)->summarizeValidRows(
        $fixture['path'],
        $fixture['delimiter'],
        $fixture['mapping'],
    );

    return buildCsvFile(
        $headerLine,
        $dataRows,
        $footerLineBuilder($totals->count, $totals->ht, $totals->vat, $totals->ttc),
    );
}

/**
 * @param  list<string>  $dataRows
 */
function reconciledFrenchCsv(array $dataRows, ?callable $footerLineBuilder = null): string
{
    return reconciledCsv(
        frenchCsvHeaderLine(),
        $dataRows,
        $footerLineBuilder ?? fn (int $count, int $ht, int $vat, int $ttc): string => frenchFooterLine($count, $ht, $vat, $ttc),
    );
}

/**
 * @param  list<string>  $dataRows
 */
function reconciledEnglishCsv(array $dataRows, string $headerLine, ?callable $footerLineBuilder = null): string
{
    return reconciledCsv(
        $headerLine,
        $dataRows,
        $footerLineBuilder ?? fn (int $count, int $ht, int $vat, int $ttc): string => englishFooterLine($count, $ht, $vat, $ttc),
    );
}

function runVerificationPipelineForContents(string $contents): ImportVerification
{
    $center = createTestCenter();
    $manager = actingAsManager($center);
    $verification = startVerificationFor($manager, $center, $contents);
    runProcessVerificationJob($verification->token);

    return $verification->fresh();
}

function csvFixture(string $headerLine, bool $withBom = true): string
{
    $prefix = $withBom ? "\xEF\xBB\xBF" : '';

    return $prefix.$headerLine."\n";
}

function sampleCsvContents(): string
{
    return csvFixture(frenchCsvHeaderLine());
}

function startVerificationFor(User $user, Center $center, ?string $contents = null, ImportMode $importMode = ImportMode::Operational): ImportVerification
{
    $file = UploadedFile::fake()->createWithContent(
        'cashflow-june.csv',
        $contents ?? sampleCsvContents(),
    );

    return app(VerificationService::class)->start(
        user: $user,
        center: $center,
        file: $file,
        importMode: $importMode,
    );
}

function storeInspectionFixture(string $relativePath, string $contents): string
{
    Storage::disk('local')->put($relativePath, $contents);

    return Storage::disk('local')->path($relativePath);
}

function runProcessVerificationJob(string $token, ?int $centerId = null): void
{
    if ($centerId === null) {
        $centerId = (int) ImportVerification::query()
            ->withoutCenterScope()
            ->where('token', $token)
            ->value('center_id');
    }

    (new ProcessVerificationJob($token, $centerId))->handle(
        app(CsvInspectionService::class),
        app(HeaderMappingService::class),
        app(CsvParsingService::class),
        app(FooterReaderService::class),
        app(ReconciliationService::class),
        app(DuplicatePreviewService::class),
        app(\App\Support\Center\JobCenterContextService::class),
    );
}

/**
 * @param  list<string>  $dataRows
 */
function buildCsvFile(string $headerLine, array $dataRows, ?string $footerLine = null): string
{
    $lines = ["\xEF\xBB\xBF".$headerLine, ...$dataRows];

    if ($footerLine !== null) {
        $lines[] = $footerLine;
    }

    return implode("\n", $lines)."\n";
}

/**
 * @param  list<string>  $dataRows
 */
function verificationReadyFrenchCsv(array $dataRows = [], ?string $footerLine = null): string
{
    if ($footerLine === null) {
        $count = count($dataRows);
        $footerLine = frenchFooterLine(
            count: $count,
            ht: $count * 10_000,
            vat: $count * 1_925,
            ttc: $count * 11_925,
        );
    }

    return buildCsvFile(frenchCsvHeaderLine(), $dataRows, $footerLine);
}

function completedFrenchDataRow(
    string $registrationDate = '01/06/2026',
    string $completionDate = '02/06/2026',
    string $net = '10 000',
    string $vat = '1 925',
    string $ttc = '11 925',
): string {
    return implode(';', [
        $registrationDate,
        '10:30',
        $completionDate,
        'ACME SARL',
        'VL',
        'C',
        'LT-123-AB',
        $net,
        $vat,
        $ttc,
    ]);
}

function frenchFooterLine(int $count, int $ht, int $vat, int $ttc): string
{
    return sprintf(
        ";Nombre total d'inspections :;%s;;;;Total :;%s;%s;%s",
        number_format($count, 0, '', ' '),
        number_format($ht, 0, '', ' '),
        number_format($vat, 0, '', ' '),
        number_format($ttc, 0, '', ' '),
    );
}

function englishFooterLine(int $count, int $ht, int $vat, int $ttc): string
{
    return sprintf(
        ';Total number of inspections :;%s;;;;Total :;%s;%s;%s',
        number_format($count, 0, '', ' '),
        number_format($ht, 0, '', ' '),
        number_format($vat, 0, '', ' '),
        number_format($ttc, 0, '', ' '),
    );
}

function frenchDataRow(
    string $registrationDate,
    string $registrationTime,
    string $completionDate,
    string $customerName,
    string $categoryCode,
    string $inspectionTypeCode,
    string $licencePlate,
    string $net,
    string $vat,
    string $ttc,
): string {
    return implode(';', [
        $registrationDate,
        $registrationTime,
        $completionDate,
        $customerName,
        $categoryCode,
        $inspectionTypeCode,
        $licencePlate,
        $net,
        $vat,
        $ttc,
    ]);
}

function realPatternDataRows(): array
{
    return [
        frenchDataRow('31/12/2024', '16:28', '31/12/2024', 'SAHABO IBRAHIM', 'B1', 'C', 'CH 480919', '12 998', '2 502', '15 500'),
        frenchDataRow('31/12/2024', '15:23', '31/12/2024', 'OUSMANOU SALMANE', 'D', 'C', 'CH 08 B 245', '22 000', '4 235', '26 235'),
        frenchDataRow('31/12/2024', '14:02', '-', 'LOXEA', 'B1', 'C', 'LT 459 IS', '0', '0', '0'),
        frenchDataRow('28/12/2024', '15:54', '28/12/2024', 'SG CAMEROUN LOC ETS', 'D', 'CV', 'LTTR 404 AI', '0', '0', '0'),
        frenchDataRow('24/12/2024', '10:05', '24/12/2024', 'KHALIFA ABDALLAH', 'D', 'C', '18 P 6649 A', '22 000', '4 235', '26 235'),
        frenchDataRow('01/01/2024', '10:03', '02/01/2024', 'OUSSOUMANOU FAROUCK', 'D', 'C', 'LTSR 330 AO', '22 000', '4 235', '26 235'),
        frenchDataRow('03/12/2024', '10:19', '-', 'TAMBEKOU NGOUEKO', 'B', 'C', '460854', '0', '0', '0'),
        frenchDataRow('28/12/2024', '11:50', '-', 'oumarou djobdi', 'D', 'C', 'EN 462 AT', '0', '0', '0'),
        frenchDataRow('22/01/2024', '14:49', '22/01/2024', 'MAMOUDOU SINATA', 'B', 'CV', 'NO 043 AS', '0', '0', '0'),
        frenchDataRow('05/01/2024', '09:06', '05/01/2024', 'HCR', 'B1', 'CV', 'IT 25111 RC', '0', '0', '0'),
        frenchDataRow('22/01/2024', '14:59', '-', 'DEUDEUTCHEU CARINE', 'A', 'C', 'EN 241 AS', '0', '0', '0'),
        frenchDataRow('01/06/2026', '10:30', '02/06/2026', 'ACME SARL', 'VL', 'C', 'LT-123-AB', '10 000', '1 925', '11 925'),
    ];
}

function seedMasterLedgerExactHash(
    int $centerId,
    string $exactHash,
    string $policyVersion = 'field_specific_v1',
    ?CanonicalRecord $canonical = null,
    array $originalValues = [],
): void {
    $center = Center::query()->findOrFail($centerId);

    $user = User::query()
        ->where('center_id', $centerId)
        ->first();

    if ($user === null) {
        $user = User::query()->create([
            'organization_id' => $center->organization_id,
            'center_id' => $centerId,
            'name' => 'Ledger Seed User',
            'username' => 'ledger-seed-'.uniqid(),
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    $import = Import::query()->create([
        'center_id' => $centerId,
        'uploaded_by' => $user->id,
        'import_mode' => ImportMode::Operational,
        'source_language' => 'fr',
        'original_filename' => 'seed-ledger.csv',
        'storage_path' => 'imports/'.$centerId.'/seed-ledger.csv',
        'file_hash' => hash('sha256', 'seed-ledger-'.uniqid()),
        'file_size' => 1024,
        'status' => ImportStatus::Completed,
    ]);

    $canonicalFields = $canonical?->canonicalFields() ?? [];
    $businessDate = (string) ($canonicalFields['registration_date'] ?? '2024-06-15');

    $importRow = ImportRow::query()->create([
        'import_id' => $import->id,
        'center_id' => $centerId,
        'source_row_number' => 1,
        'business_date' => $businessDate,
        'original_values' => $originalValues,
        'canonical_values' => $canonicalFields,
        'raw_row_checksum' => hash('sha256', 'seed-row-'.uniqid()),
        'exact_canonical_hash' => $exactHash,
        'normalization_policy_version' => $policyVersion,
        'row_status' => ImportRowStatus::Accepted,
    ]);

    MasterCashFlowRecord::query()->create([
        'center_id' => $centerId,
        'registration_date' => $businessDate,
        'registration_time' => (string) ($canonicalFields['registration_time'] ?? '09:30:00'),
        'completion_date' => $canonicalFields['completion_date'] ?? null,
        'customer_name' => (string) ($originalValues['customer_name'] ?? $canonicalFields['customer_name'] ?? 'Seed Customer'),
        'customer_name_normalized' => (string) ($canonicalFields['customer_name'] ?? 'seed customer'),
        'category_code' => (string) ($canonicalFields['category_code'] ?? 'CAT1'),
        'inspection_type_code' => (string) ($canonicalFields['inspection_type_code'] ?? 'VIS'),
        'licence_plate' => (string) ($originalValues['licence_plate'] ?? 'LT-123-AB'),
        'licence_plate_normalized' => (string) ($canonicalFields['licence_plate'] ?? 'LT123AB'),
        'net_amount' => number_format((int) ($canonicalFields['net_amount'] ?? 10000), 2, '.', ''),
        'vat_amount' => number_format((int) ($canonicalFields['vat_amount'] ?? 1925), 2, '.', ''),
        'gross_amount' => number_format((int) ($canonicalFields['gross_amount'] ?? 11925), 2, '.', ''),
        'completion_status' => ($canonicalFields['completion_date'] ?? null) === null
            ? CompletionStatus::Unfinished
            : CompletionStatus::Completed,
        'financial_status' => ((int) ($canonicalFields['net_amount'] ?? 10000)) === 0
            && ((int) ($canonicalFields['vat_amount'] ?? 1925)) === 0
            && ((int) ($canonicalFields['gross_amount'] ?? 11925)) === 0
            ? FinancialStatus::ZeroValue
            : FinancialStatus::Revenue,
        'exact_canonical_hash' => $exactHash,
        'normalization_policy_version' => $policyVersion,
        'first_import_id' => $import->id,
        'first_import_row_id' => $importRow->id,
        'first_seen_at' => now(),
    ]);
}
