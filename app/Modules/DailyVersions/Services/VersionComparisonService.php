<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Services;

use App\Modules\CsvImports\Enums\DayComparisonResult;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportDayComparison;
use App\Modules\DailyVersions\Models\ActiveDailySnapshot;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\DailyVersions\Support\DailyDataset;
use App\Modules\DailyVersions\Support\VersionComparisonProcessResult;

final class VersionComparisonService
{
    public function __construct(
        private readonly DailyDatasetService $dailyDatasetService,
    ) {}

    public function processImport(Import $import): VersionComparisonProcessResult
    {
        $newDays = 0;
        $unchangedDays = 0;
        $revisionRequiredDays = 0;
        $coveredWithoutRowsDays = 0;

        $datesWithRows = $this->dailyDatasetService->datesWithRows($import);

        foreach ($datesWithRows as $businessDate) {
            $proposed = $this->dailyDatasetService->buildFromImport($import, $businessDate);
            $result = $this->compareDataset($import, $businessDate, $proposed);

            match ($result) {
                DayComparisonResult::New => $newDays++,
                DayComparisonResult::Unchanged => $unchangedDays++,
                DayComparisonResult::RevisionRequired => $revisionRequiredDays++,
                default => null,
            };
        }

        $rowDateLookup = array_fill_keys($datesWithRows, true);

        foreach ($this->dailyDatasetService->coveredPeriodDates($import) as $businessDate) {
            if (isset($rowDateLookup[$businessDate])) {
                continue;
            }

            $this->persistComparison(
                import: $import,
                businessDate: $businessDate,
                result: DayComparisonResult::CoveredWithoutRows,
                proposed: null,
            );

            $coveredWithoutRowsDays++;
        }

        return new VersionComparisonProcessResult(
            newDays: $newDays,
            unchangedDays: $unchangedDays,
            revisionRequiredDays: $revisionRequiredDays,
            coveredWithoutRowsDays: $coveredWithoutRowsDays,
        );
    }

    private function compareDataset(
        Import $import,
        string $businessDate,
        DailyDataset $proposed,
    ): DayComparisonResult {
        $existingVersion = $this->activeVersionForDate($import->center_id, $businessDate);

        $result = match (true) {
            $existingVersion === null && ! $proposed->isEmpty() => DayComparisonResult::New,
            $existingVersion === null => DayComparisonResult::Invalid,
            $existingVersion->dataset_hash === $proposed->datasetHash => DayComparisonResult::Unchanged,
            default => DayComparisonResult::RevisionRequired,
        };

        $this->persistComparison(
            import: $import,
            businessDate: $businessDate,
            result: $result,
            proposed: $proposed,
            existingVersion: $existingVersion,
        );

        return $result;
    }

    private function activeVersionForDate(int $centerId, string $businessDate): ?DailyVersion
    {
        $snapshot = ActiveDailySnapshot::query()
            ->withoutCenterScope()
            ->where('center_id', $centerId)
            ->whereDate('business_date', $businessDate)
            ->first();

        if ($snapshot === null) {
            return null;
        }

        return DailyVersion::query()
            ->withoutCenterScope()
            ->find($snapshot->daily_version_id);
    }

    private function persistComparison(
        Import $import,
        string $businessDate,
        DayComparisonResult $result,
        ?DailyDataset $proposed,
        ?DailyVersion $existingVersion = null,
    ): ImportDayComparison {
        $existingVersion ??= $this->activeVersionForDate($import->center_id, $businessDate);

        $recordCountDelta = null;

        if ($proposed !== null && $existingVersion !== null) {
            $recordCountDelta = $proposed->recordCount - $existingVersion->record_count;
        }

        return ImportDayComparison::query()
            ->withoutCenterScope()
            ->updateOrCreate(
                [
                    'import_id' => $import->id,
                    'business_date' => $businessDate,
                ],
                [
                    'center_id' => $import->center_id,
                    'comparison_result' => $result,
                    'existing_version_id' => $existingVersion?->id,
                    'proposed_version_id' => null,
                    'existing_ht' => $existingVersion?->total_ht,
                    'existing_vat' => $existingVersion?->total_vat,
                    'existing_ttc' => $existingVersion?->total_ttc,
                    'proposed_ht' => $proposed?->totalHt,
                    'proposed_vat' => $proposed?->totalVat,
                    'proposed_ttc' => $proposed?->totalTtc,
                    'record_count_delta' => $recordCountDelta,
                ],
            );
    }
}
