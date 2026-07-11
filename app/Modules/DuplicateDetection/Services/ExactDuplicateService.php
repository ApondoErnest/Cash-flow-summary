<?php

declare(strict_types=1);

namespace App\Modules\DuplicateDetection\Services;

use App\Modules\CsvImports\Models\ImportRow;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\DuplicateDetection\Support\ExactDuplicateKind;
use App\Modules\DuplicateDetection\Support\ExactDuplicateMatch;
use App\Modules\Normalization\Support\CanonicalRecord;

final class ExactDuplicateService
{
    /**
     * @param  array<string, ImportRow>  $firstRowByHashInImport
     * @param  array<string, MasterCashFlowRecord>|null  $historicalByHash
     */
    public function matchForImportRow(
        ImportRow $row,
        int $centerId,
        array $firstRowByHashInImport,
        ?array $historicalByHash = null,
    ): ExactDuplicateMatch {
        $canonical = $this->canonicalFromImportRow($row);
        $hash = $row->exact_canonical_hash;

        if (isset($firstRowByHashInImport[$hash])) {
            $firstRow = $firstRowByHashInImport[$hash];

            if ($this->canonicalFieldsMatch($firstRow, $row)) {
                $firstRow->loadMissing('masterRecord');

                return new ExactDuplicateMatch(
                    kind: ExactDuplicateKind::WithinFile,
                    masterRecord: $firstRow->masterRecord,
                    withinFileSourceRow: $firstRow,
                );
            }
        }

        $historical = $historicalByHash !== null
            ? $this->matchFromHistoricalMap($canonical, $historicalByHash)
            : $this->findHistoricalMatch(
                centerId: $centerId,
                canonical: $canonical,
                policyVersion: $row->normalization_policy_version,
            );

        if ($historical !== null) {
            return new ExactDuplicateMatch(
                kind: ExactDuplicateKind::Historical,
                masterRecord: $historical,
            );
        }

        return new ExactDuplicateMatch(kind: ExactDuplicateKind::None);
    }

    /**
     * @param  array<string, MasterCashFlowRecord>  $historicalByHash
     */
    private function matchFromHistoricalMap(
        CanonicalRecord $canonical,
        array $historicalByHash,
    ): ?MasterCashFlowRecord {
        $record = $historicalByHash[$canonical->exactCanonicalHash()] ?? null;

        if ($record === null) {
            return null;
        }

        if (! $this->masterRecordMatchesCanonical($record, $canonical)) {
            return null;
        }

        return $record;
    }

    public function findHistoricalMatch(
        int $centerId,
        CanonicalRecord $canonical,
        string $policyVersion,
    ): ?MasterCashFlowRecord {
        $record = MasterCashFlowRecord::query()
            ->withoutCenterScope()
            ->where('center_id', $centerId)
            ->where('normalization_policy_version', $policyVersion)
            ->where('exact_canonical_hash', $canonical->exactCanonicalHash())
            ->first();

        if ($record === null) {
            return null;
        }

        if (! $this->masterRecordMatchesCanonical($record, $canonical)) {
            return null;
        }

        return $record;
    }

    public function canonicalFromImportRow(ImportRow $row): CanonicalRecord
    {
        return CanonicalRecord::fromCanonicalValues(
            $row->canonical_values ?? [],
            $row->normalization_policy_version,
        );
    }

    public function canonicalFieldsMatch(ImportRow $first, ImportRow $second): bool
    {
        return $this->canonicalFromImportRow($first)->canonicalFields()
            === $this->canonicalFromImportRow($second)->canonicalFields();
    }

    private function masterRecordMatchesCanonical(
        MasterCashFlowRecord $record,
        CanonicalRecord $canonical,
    ): bool {
        $recordCanonical = CanonicalRecord::fromCanonicalValues([
            'registration_date' => $record->registration_date?->toDateString(),
            'registration_time' => $record->registration_time,
            'completion_date' => $record->completion_date?->toDateString(),
            'customer_name' => $record->customer_name_normalized,
            'category_code' => $record->category_code,
            'inspection_type_code' => $record->inspection_type_code,
            'licence_plate' => $record->licence_plate_normalized,
            'net_amount' => (int) round((float) $record->net_amount),
            'vat_amount' => (int) round((float) $record->vat_amount),
            'gross_amount' => (int) round((float) $record->gross_amount),
        ], $record->normalization_policy_version);

        return $recordCanonical->canonicalFields() === $canonical->canonicalFields();
    }
}
