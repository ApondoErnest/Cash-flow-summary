<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Services;

use App\Modules\CsvVerification\Enums\CsvRowStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\CsvVerification\Support\CsvFooterDetector;
use App\Modules\CsvVerification\Support\CsvParseResult;
use App\Modules\CsvVerification\Support\CsvParseSummary;
use App\Modules\CsvVerification\Support\HeaderMappingResult;
use App\Modules\CsvVerification\Support\ParsedCsvRow;
use Generator;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class CsvParsingService
{
    /**
     * @param  array<string, int>  $mapping
     */
    public function parseFile(string $filePath, string $delimiter, array $mapping): CsvParseResult
    {
        $summary = new CsvParseSummary();
        $invalidRows = [];

        foreach ($this->streamRows($filePath, $delimiter, $mapping) as $row) {
            $summary->add($row);

            if ($row->status === CsvRowStatus::Invalid) {
                $invalidRows[] = $row;
            }
        }

        return new CsvParseResult($summary, $invalidRows);
    }

    public function parseVerification(ImportVerification $verification, HeaderMappingResult $mapping): CsvParseResult
    {
        if (! $mapping->isValid()) {
            throw new RuntimeException('Header mapping must be valid before parsing rows.');
        }

        $disk = (string) config('csv_verification.temp_disk', 'local');

        if (! Storage::disk($disk)->exists($verification->temp_storage_path)) {
            throw new RuntimeException('Verification temp file is missing.');
        }

        $delimiter = $verification->delimiter ?? ';';

        return $this->parseFile(
            Storage::disk($disk)->path($verification->temp_storage_path),
            $delimiter,
            $mapping->mapping,
        );
    }

    /**
     * @param  array<string, int>  $mapping
     * @return Generator<int, ParsedCsvRow>
     */
    public function streamRows(string $filePath, string $delimiter, array $mapping): Generator
    {
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV file for parsing.');
        }

        try {
            $this->skipHeaderRow($handle);

            $lineNumber = 1;

            while (($line = fgets($handle)) !== false) {
                $lineNumber++;

                if ($this->shouldSkipLine($line)) {
                    continue;
                }

                if (CsvFooterDetector::isFooterLine($line)) {
                    break;
                }

                $cells = str_getcsv(rtrim($line, "\r\n"), $delimiter, '"', '\\');

                yield $this->parseRow($cells, $mapping, $lineNumber);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string>  $cells
     * @param  array<string, int>  $mapping
     */
    private function parseRow(array $cells, array $mapping, int $rowNumber): ParsedCsvRow
    {
        $rawValues = [];

        foreach ($mapping as $field => $index) {
            $rawValues[$field] = trim($cells[$index] ?? '');
        }

        $errors = [];
        $registrationDate = $this->parseDate($rawValues['registration_date'] ?? '', $errors, 'registration_date');
        $registrationTime = $this->parseTime($rawValues['registration_time'] ?? '', $errors, 'registration_time');
        $completionDate = $this->parseOptionalDate($rawValues['completion_date'] ?? '');
        $netAmount = $this->parseAmount($rawValues['net_amount'] ?? '', $errors, 'net_amount');
        $vatAmount = $this->parseAmount($rawValues['vat_amount'] ?? '', $errors, 'vat_amount');
        $grossAmount = $this->parseAmount($rawValues['gross_amount'] ?? '', $errors, 'gross_amount');

        if ($registrationDate === null) {
            $errors[] = __('csv_verification.parsing.invalid_registration_date');
        }

        if ($netAmount !== null && $vatAmount !== null && $grossAmount !== null
            && ($netAmount + $vatAmount) !== $grossAmount) {
            $errors[] = __('csv_verification.parsing.amount_mismatch');
        }

        $status = match (true) {
            $errors !== [] => CsvRowStatus::Invalid,
            $completionDate === null => CsvRowStatus::Unfinished,
            default => CsvRowStatus::Completed,
        };

        return new ParsedCsvRow(
            rowNumber: $rowNumber,
            rawValues: $rawValues,
            registrationDate: $registrationDate,
            registrationTime: $registrationTime,
            completionDate: $completionDate,
            customerName: $rawValues['customer_name'] ?? '',
            categoryCode: $rawValues['category_code'] ?? '',
            inspectionTypeCode: $rawValues['inspection_type_code'] ?? '',
            licencePlate: $rawValues['licence_plate'] ?? '',
            netAmount: $netAmount,
            vatAmount: $vatAmount,
            grossAmount: $grossAmount,
            status: $status,
            errors: $errors,
        );
    }

    /**
     * @param  resource  $handle
     */
    private function skipHeaderRow($handle): void
    {
        $header = fgets($handle);

        if ($header === false) {
            throw new RuntimeException('CSV file is missing a header row.');
        }
    }

    private function shouldSkipLine(string $line): bool
    {
        return trim($line) === '';
    }

    /**
     * @param  list<string>  $errors
     */
    private function parseDate(string $value, array &$errors, string $field): ?string
    {
        $parsed = $this->parseOptionalDate($value);

        if ($parsed === null && trim($value) !== '' && trim($value) !== '-') {
            $errors[] = __('csv_verification.parsing.invalid_date', ['field' => $field]);
        }

        return $parsed;
    }

    private function parseOptionalDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || $value === '-') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j/n/Y', 'j-n-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date === false) {
                continue;
            }

            $warnings = $date->getLastErrors();

            if (($warnings['warning_count'] ?? 0) === 0 && ($warnings['error_count'] ?? 0) === 0) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $errors
     */
    private function parseTime(string $value, array &$errors, string $field): ?string
    {
        $value = trim($value);

        if ($value === '' || $value === '-') {
            return null;
        }

        foreach (['H:i:s', 'H:i', 'G:i:s', 'G:i'] as $format) {
            $time = \DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($time === false) {
                continue;
            }

            $warnings = $time->getLastErrors();

            if (($warnings['warning_count'] ?? 0) === 0 && ($warnings['error_count'] ?? 0) === 0) {
                return $time->format('H:i:s');
            }
        }

        $errors[] = __('csv_verification.parsing.invalid_time', ['field' => $field]);

        return null;
    }

    /**
     * @param  list<string>  $errors
     */
    private function parseAmount(string $value, array &$errors, string $field): ?int
    {
        $normalized = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim($value));

        if ($normalized === '') {
            $errors[] = __('csv_verification.parsing.invalid_amount', ['field' => $field]);

            return null;
        }

        if (! preg_match('/^-?\d+$/', $normalized)) {
            $errors[] = __('csv_verification.parsing.invalid_amount', ['field' => $field]);

            return null;
        }

        $amount = (int) $normalized;

        if ($amount < 0) {
            $errors[] = __('csv_verification.parsing.negative_amount', ['field' => $field]);

            return null;
        }

        return $amount;
    }
}
