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
        $persisted = 0;

        foreach ($this->csvParsingService->streamRows($filePath, $delimiter, $mapping->mapping) as $row) {
            if ($row->status === CsvRowStatus::Invalid) {
                $importRow = ImportRow::query()->create([
                    'import_id' => $import->id,
                    'center_id' => $import->center_id,
                    'source_row_number' => $row->rowNumber,
                    'business_date' => $row->registrationDate ?? $fallbackBusinessDate,
                    'original_values' => $row->rawValues,
                    'canonical_values' => [],
                    'raw_row_checksum' => $row->rawRowChecksum(),
                    'exact_canonical_hash' => $row->rawRowChecksum(),
                    'normalization_policy_version' => $policyVersion,
                    'row_status' => ImportRowStatus::Invalid,
                    'validation_errors' => $row->errors,
                ]);

                $this->errorRecorder->recordFromImportRow($importRow);
            } else {
                $canonical = $this->normalizationService->normalizeParsedRow($row);

                ImportRow::query()->create([
                    'import_id' => $import->id,
                    'center_id' => $import->center_id,
                    'source_row_number' => $row->rowNumber,
                    'business_date' => $row->registrationDate ?? $fallbackBusinessDate,
                    'original_values' => $row->rawValues,
                    'canonical_values' => $canonical->canonicalFields(),
                    'raw_row_checksum' => $row->rawRowChecksum(),
                    'exact_canonical_hash' => $canonical->exactCanonicalHash(),
                    'similarity_fingerprint' => $this->similarityFingerprintService->fingerprint($canonical),
                    'normalization_policy_version' => $policyVersion,
                    'row_status' => ImportRowStatus::New,
                ]);
            }

            $persisted++;
        }

        return $persisted;
    }
}
