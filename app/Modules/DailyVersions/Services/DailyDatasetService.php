<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Services;

use App\Modules\CsvImports\Enums\ImportRowStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportRow;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\DailyVersions\Support\DailyDataset;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

final class DailyDatasetService
{
    /**
     * @return list<string>
     */
    public function datesWithRows(Import $import): array
    {
        return ImportRow::query()
            ->withoutCenterScope()
            ->where('import_id', $import->id)
            ->where('row_status', '!=', ImportRowStatus::Invalid)
            ->whereNotNull('master_record_id')
            ->distinct()
            ->orderBy('business_date')
            ->pluck('business_date')
            ->map(static fn ($date): string => $date->toDateString())
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function coveredPeriodDates(Import $import): array
    {
        if ($import->actual_period_start === null || $import->actual_period_end === null) {
            return [];
        }

        $period = CarbonPeriod::create(
            $import->actual_period_start->toDateString(),
            $import->actual_period_end->toDateString(),
        );

        $dates = [];

        foreach ($period as $date) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    public function buildFromImport(Import $import, string $businessDate): DailyDataset
    {
        /** @var list<int> $masterRecordIds */
        $masterRecordIds = ImportRow::query()
            ->withoutCenterScope()
            ->where('import_id', $import->id)
            ->whereDate('business_date', $businessDate)
            ->whereNotNull('master_record_id')
            ->where('row_status', '!=', ImportRowStatus::Invalid)
            ->pluck('master_record_id')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $this->buildFromMasterIds(
            centerId: $import->center_id,
            businessDate: $businessDate,
            masterRecordIds: $masterRecordIds,
        );
    }

    /**
     * @param  list<int>  $masterRecordIds
     */
    public function buildFromMasterIds(
        int $centerId,
        string $businessDate,
        array $masterRecordIds,
    ): DailyDataset {
        /** @var Collection<int, MasterCashFlowRecord> $records */
        $records = MasterCashFlowRecord::query()
            ->withoutCenterScope()
            ->whereIn('id', $masterRecordIds)
            ->orderBy('exact_canonical_hash')
            ->get();

        $hashes = $records
            ->pluck('exact_canonical_hash')
            ->values()
            ->all();

        $datasetHash = hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR));

        $totalHt = 0.0;
        $totalVat = 0.0;
        $totalTtc = 0.0;

        foreach ($records as $record) {
            $totalHt += (float) $record->net_amount;
            $totalVat += (float) $record->vat_amount;
            $totalTtc += (float) $record->gross_amount;
        }

        return new DailyDataset(
            centerId: $centerId,
            businessDate: $businessDate,
            masterRecordIds: $masterRecordIds,
            datasetHash: $datasetHash,
            recordCount: count($masterRecordIds),
            totalHt: $this->money($totalHt),
            totalVat: $this->money($totalVat),
            totalTtc: $this->money($totalTtc),
        );
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
