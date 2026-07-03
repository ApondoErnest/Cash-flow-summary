<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Services;

use App\Modules\CsvImports\Models\ImportError;
use App\Modules\CsvImports\Models\ImportRow;
use App\Modules\CsvVerification\Enums\CsvRowStatus;
use App\Modules\CsvVerification\Support\ParsedCsvRow;

final class ImportErrorRecorderService
{
    public function clearForVerification(int $importVerificationId): void
    {
        ImportError::query()
            ->where('import_verification_id', $importVerificationId)
            ->delete();
    }

    public function recordFromParsedRow(
        ParsedCsvRow $row,
        ?int $importId = null,
        ?int $importVerificationId = null,
    ): void {
        if ($row->status !== CsvRowStatus::Invalid || $row->errors === []) {
            return;
        }

        $rawRow = $this->formatRawRow($row->rawValues);

        foreach ($row->errors as $message) {
            [$field, $errorCode, $originalValue] = $this->resolveErrorContext($row, $message);

            ImportError::query()->create([
                'import_id' => $importId,
                'import_verification_id' => $importVerificationId,
                'source_row_number' => $row->rowNumber,
                'field' => $field,
                'error_code' => $errorCode,
                'message' => $message,
                'original_value' => $originalValue,
                'raw_row' => $rawRow,
            ]);
        }
    }

    public function recordFromImportRow(ImportRow $row): void
    {
        $errors = $row->validation_errors ?? [];

        if ($errors === []) {
            return;
        }

        $rawRow = $this->formatRawRow($row->original_values ?? []);

        foreach ($errors as $message) {
            ImportError::query()->create([
                'import_id' => $row->import_id,
                'import_verification_id' => null,
                'source_row_number' => $row->source_row_number,
                'field' => $this->inferFieldFromMessage($message),
                'error_code' => $this->inferErrorCode($message),
                'message' => $message,
                'original_value' => $this->firstOriginalValue($row->original_values ?? []),
                'raw_row' => $rawRow,
            ]);
        }
    }

    public function attachVerificationErrorsToImport(int $importVerificationId, int $importId): void
    {
        ImportError::query()
            ->where('import_verification_id', $importVerificationId)
            ->whereNull('import_id')
            ->update(['import_id' => $importId]);
    }

    /**
     * @param  array<string, string>  $rawValues
     */
    private function formatRawRow(array $rawValues): string
    {
        return implode(';', array_values($rawValues));
    }

    /**
     * @param  array<string, string>  $rawValues
     * @return array{0: ?string, 1: string, 2: ?string}
     */
    private function resolveErrorContext(ParsedCsvRow $row, string $message): array
    {
        $field = $this->inferFieldFromMessage($message);
        $errorCode = $this->inferErrorCode($message);
        $originalValue = $field !== null
            ? ($row->rawValues[$field] ?? null)
            : $this->firstOriginalValue($row->rawValues);

        return [$field, $errorCode, $originalValue !== '' ? $originalValue : null];
    }

    private function inferFieldFromMessage(string $message): ?string
    {
        foreach ([
            'registration_date' => ['registration date', 'date d\'enregistrement', 'registration_date'],
            'registration_time' => ['registration time', 'registration_time'],
            'net_amount' => ['net_amount', 'net amount', 'montant ht'],
            'vat_amount' => ['vat_amount', 'vat amount', 'montant tva'],
            'gross_amount' => ['gross_amount', 'gross amount', 'montant ttc'],
        ] as $field => $needles) {
            $haystack = strtolower($message);

            foreach ($needles as $needle) {
                if (str_contains($haystack, strtolower($needle))) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function inferErrorCode(string $message): string
    {
        $normalized = strtolower($message);

        return match (true) {
            str_contains($normalized, 'negative') => 'negative_amount',
            str_contains($normalized, 'amount mismatch') || str_contains($normalized, 'montants') => 'amount_mismatch',
            str_contains($normalized, 'invalid amount') || str_contains($normalized, 'montant') => 'invalid_amount',
            str_contains($normalized, 'invalid date') || str_contains($normalized, 'date') => 'invalid_date',
            str_contains($normalized, 'invalid time') => 'invalid_time',
            str_contains($normalized, 'registration date') => 'invalid_registration_date',
            default => 'row_validation',
        };
    }

    /**
     * @param  array<string, string>  $rawValues
     */
    private function firstOriginalValue(array $rawValues): ?string
    {
        foreach ($rawValues as $value) {
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
