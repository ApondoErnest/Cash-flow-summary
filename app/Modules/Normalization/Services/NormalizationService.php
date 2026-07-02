<?php

declare(strict_types=1);

namespace App\Modules\Normalization\Services;

use App\Modules\CsvVerification\Support\ParsedCsvRow;
use App\Modules\Normalization\NormalizationPolicy;
use App\Modules\Normalization\Support\CanonicalRecord;

final class NormalizationService
{
    public function policyVersion(): string
    {
        return NormalizationPolicy::VERSION;
    }

    public function normalizeParsedRow(ParsedCsvRow $row): CanonicalRecord
    {
        return new CanonicalRecord(
            registrationDate: $row->registrationDate,
            registrationTime: $row->registrationTime,
            completionDate: $row->completionDate,
            customerName: $this->normalizeCustomerName($row->customerName),
            categoryCode: $this->normalizeCategoryCode($row->categoryCode),
            inspectionTypeCode: $this->normalizeInspectionTypeCode($row->inspectionTypeCode),
            licencePlate: $this->normalizeLicencePlate($row->licencePlate),
            netAmount: $row->netAmount,
            vatAmount: $row->vatAmount,
            grossAmount: $row->grossAmount,
        );
    }

    public function normalizeLicencePlate(string $value): string
    {
        $value = mb_strtoupper($this->transportCleanup($value), 'UTF-8');

        return preg_replace('/[^A-Z0-9]/u', '', $value) ?? '';
    }

    public function normalizeCustomerName(string $value): string
    {
        $value = $this->transportCleanup($value);
        $value = str_replace(['’', '‘', '`', '´'], "'", $value);
        $value = str_replace(["\u{2013}", "\u{2014}"], '-', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtoupper(trim($value), 'UTF-8');
    }

    public function normalizeCategoryCode(string $value): string
    {
        return $this->transportCleanup($value);
    }

    public function normalizeInspectionTypeCode(string $value): string
    {
        return mb_strtoupper($this->transportCleanup($value), 'UTF-8');
    }

    private function transportCleanup(string $value): string
    {
        $value = preg_replace('/\x{FEFF}|\xEF\xBB\xBF/u', '', $value) ?? $value;
        $value = trim($value);

        if (str_starts_with($value, '"') && str_ends_with($value, '"') && strlen($value) >= 2) {
            $value = substr($value, 1, -1);
        }

        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;

        return trim($value);
    }
}
