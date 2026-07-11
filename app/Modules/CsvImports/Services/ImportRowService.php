<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Services;

use App\Modules\CsvImports\Enums\ImportRowStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportRow;
use App\Modules\CsvVerification\Enums\CsvRowStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\CsvVerification\Services\CsvInspectionService;
use App\Modules\CsvVerification\Services\CsvParsingService;
use App\Modules\CsvVerification\Services\HeaderMappingService;
use App\Modules\Normalization\Services\NormalizationService;
use App\Modules\Normalization\Services\SimilarityFingerprintService;
use RuntimeException;

final class ImportRowService
{
    public function __construct(
        private readonly CsvParsingService $csvParsingService,
        private readonly CsvInspectionService $csvInspectionService,
        private readonly HeaderMappingService $headerMappingService,
        private readonly NormalizationService $normalizationService,
        private readonly SimilarityFingerprintService $similarityFingerprintService,
        private readonly ImportErrorRecorderService $errorRecorder,
    ) {}

    public function persistRows(Import $import, ImportVerification $verification, string $filePath): int
    {
        $inspection = $this->csvInspectionService->inspect($filePath);

        if (! $inspection->isValid()) {
            throw new RuntimeException('Committed CSV file failed inspection.');
        }

        $mapping = $this->headerMappingService->map($inspection);

        if (! $mapping->isValid()) {
            throw new RuntimeException('Committed CSV file failed header mapping.');
        }

        $delimiter = $verification->delimiter ?? $inspection->delimiter;
        $policyVersion = $this->normalizationService->policyVersion();
        $fallbackBusinessDate = $verification->actual_period_start?->toDateString() ?? now()->toDateString();
        $chunkSize = max(1, (int) config('csv_imports.row_insert_chunk_size', 500));
        $now = now()->toDateTimeString();
        $persisted = 0;

        /** @var list<array<string, mixed>> $buffer */
        $buffer = [];
        /** @var list<\App\Modules\CsvVerification\Support\ParsedCsvRow> $invalidInBuffer */
        $invalidInBuffer = [];

        $flush = function () use (&$buffer, &$invalidInBuffer, $import): void {
            if ($buffer === []) {
                return;
            }

            ImportRow::query()->insert($buffer);

            foreach ($invalidInBuffer as $invalidRow) {
                $this->errorRecorder->recordFromParsedRow(
                    row: $invalidRow,
                    importId: (int) $import->id,
                    importVerificationId: null,
                );
            }

            $buffer = [];
            $invalidInBuffer = [];
        };

        foreach ($this->csvParsingService->streamRows($filePath, $delimiter, $mapping->mapping) as $row) {
            if ($row->status === CsvRowStatus::Invalid) {
                $buffer[] = [
                    'import_id' => $import->id,
                    'center_id' => $import->center_id,
                    'source_row_number' => $row->rowNumber,
                    'business_date' => $row->registrationDate ?? $fallbackBusinessDate,
                    'original_values' => json_encode($row->rawValues, JSON_THROW_ON_ERROR),
                    'canonical_values' => json_encode([], JSON_THROW_ON_ERROR),
                    'raw_row_checksum' => $row->rawRowChecksum(),
                    'exact_canonical_hash' => $row->rawRowChecksum(),
                    'similarity_fingerprint' => null,
                    'normalization_policy_version' => $policyVersion,
                    'master_record_id' => null,
                    'row_status' => ImportRowStatus::Invalid->value,
                    'duplicate_type' => null,
                    'duplicate_of_import_row_id' => null,
                    'validation_errors' => json_encode($row->errors, JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $invalidInBuffer[] = $row;
            } else {
                $canonical = $this->normalizationService->normalizeParsedRow($row);

                $buffer[] = [
                    'import_id' => $import->id,
                    'center_id' => $import->center_id,
                    'source_row_number' => $row->rowNumber,
                    'business_date' => $row->registrationDate ?? $fallbackBusinessDate,
                    'original_values' => json_encode($row->rawValues, JSON_THROW_ON_ERROR),
                    'canonical_values' => json_encode($canonical->canonicalFields(), JSON_THROW_ON_ERROR),
                    'raw_row_checksum' => $row->rawRowChecksum(),
                    'exact_canonical_hash' => $canonical->exactCanonicalHash(),
                    'similarity_fingerprint' => $this->similarityFingerprintService->fingerprint($canonical),
                    'normalization_policy_version' => $policyVersion,
                    'master_record_id' => null,
                    'row_status' => ImportRowStatus::New->value,
                    'duplicate_type' => null,
                    'duplicate_of_import_row_id' => null,
                    'validation_errors' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $persisted++;

            if (count($buffer) >= $chunkSize) {
                $flush();
            }
        }

        $flush();

        return $persisted;
    }
}
