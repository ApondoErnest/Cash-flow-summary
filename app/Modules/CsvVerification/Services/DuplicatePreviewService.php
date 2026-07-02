<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Services;

use App\Modules\CsvVerification\Enums\CsvRowStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\CsvVerification\Support\DuplicatePreviewResult;
use App\Modules\CsvVerification\Support\HeaderMappingResult;
use App\Modules\Normalization\Services\NormalizationService;
use App\Modules\Normalization\Services\SimilarityFingerprintService;
use App\Modules\Normalization\Support\CanonicalRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class DuplicatePreviewService
{
    public function __construct(
        private readonly CsvParsingService $csvParsingService,
        private readonly NormalizationService $normalizationService,
        private readonly SimilarityFingerprintService $similarityFingerprintService,
    ) {}

    public function previewVerification(
        ImportVerification $verification,
        HeaderMappingResult $mapping,
        string $filePath,
    ): DuplicatePreviewResult {
        if (! $mapping->isValid()) {
            throw new RuntimeException('Header mapping must be valid before duplicate preview.');
        }

        return $this->previewFile(
            filePath: $filePath,
            delimiter: $verification->delimiter ?? ';',
            mapping: $mapping->mapping,
            centerId: $verification->center_id,
            policyVersion: $this->normalizationService->policyVersion(),
        );
    }

    /**
     * @param  array<string, int>  $mapping
     */
    public function previewFile(
        string $filePath,
        string $delimiter,
        array $mapping,
        int $centerId,
        string $policyVersion,
    ): DuplicatePreviewResult {
        $historicalHashes = $this->historicalExactHashes($centerId, $policyVersion);

        /** @var array<string, CanonicalRecord> $firstCanonicalByHash */
        $firstCanonicalByHash = [];
        /** @var array<string, true> $fingerprintsSeen */
        $fingerprintsSeen = [];

        $exact = 0;
        $probable = 0;
        $newUnique = 0;
        $normalizedRows = 0;

        foreach ($this->csvParsingService->streamRows($filePath, $delimiter, $mapping) as $row) {
            if ($row->status === CsvRowStatus::Invalid) {
                continue;
            }

            $canonical = $this->normalizationService->normalizeParsedRow($row);
            $normalizedRows++;

            $exactHash = $canonical->exactCanonicalHash();

            if ($this->isExactDuplicate($exactHash, $canonical, $firstCanonicalByHash, $historicalHashes)) {
                $exact++;

                continue;
            }

            $fingerprint = $this->similarityFingerprintService->fingerprint($canonical);

            if (isset($fingerprintsSeen[$fingerprint])) {
                $probable++;
            } else {
                $fingerprintsSeen[$fingerprint] = true;
            }

            $firstCanonicalByHash[$exactHash] ??= $canonical;
            $newUnique++;
        }

        return new DuplicatePreviewResult(
            exact: $exact,
            probable: $probable,
            newUnique: $newUnique,
            normalizedRows: $normalizedRows,
        );
    }

    /**
     * @param  array<string, CanonicalRecord>  $firstCanonicalByHash
     * @param  array<string, true>  $historicalHashes
     */
    private function isExactDuplicate(
        string $exactHash,
        CanonicalRecord $canonical,
        array $firstCanonicalByHash,
        array $historicalHashes,
    ): bool {
        if (isset($firstCanonicalByHash[$exactHash])) {
            return $this->canonicalFieldsMatch($firstCanonicalByHash[$exactHash], $canonical);
        }

        return isset($historicalHashes[$exactHash]);
    }

    private function canonicalFieldsMatch(CanonicalRecord $first, CanonicalRecord $candidate): bool
    {
        return $first->canonicalFields() === $candidate->canonicalFields();
    }

    /**
     * @return array<string, true>
     */
    private function historicalExactHashes(int $centerId, string $policyVersion): array
    {
        if (! Schema::hasTable('master_cash_flow_records')) {
            return [];
        }

        return DB::table('master_cash_flow_records')
            ->where('center_id', $centerId)
            ->where('normalization_policy_version', $policyVersion)
            ->pluck('exact_canonical_hash')
            ->mapWithKeys(static fn (string $hash): array => [$hash => true])
            ->all();
    }
}
