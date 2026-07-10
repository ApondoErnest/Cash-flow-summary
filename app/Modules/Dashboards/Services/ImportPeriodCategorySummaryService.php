<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Services;

use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\DailyVersionMembership;
use App\Modules\Dashboards\Support\DashboardCategoryCodes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ImportPeriodCategorySummaryService
{
    /**
     * @return array<string, int>
     */
    public function countsForImport(Import $import): array
    {
        /** @var array<string, int> $totals */
        $totals = array_fill_keys(DashboardCategoryCodes::CATEGORY_CODES, 0);

        if ($import->actual_period_start === null || $import->actual_period_end === null) {
            return $totals;
        }

        return $this->countsForCenterInRange(
            (int) $import->center_id,
            Carbon::parse($import->actual_period_start)->startOfDay(),
            Carbon::parse($import->actual_period_end)->endOfDay(),
        );
    }

    /**
     * @return array<string, int>
     */
    public function countsForCenterInRange(int $centerId, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        /** @var array<string, int> $totals */
        $totals = array_fill_keys(DashboardCategoryCodes::CATEGORY_CODES, 0);

        $masters = $this->activeMastersForRange($centerId, $rangeStart, $rangeEnd);

        foreach ($masters as $master) {
            $category = DashboardCategoryCodes::normalizeCategory((string) $master->category_code);

            if (array_key_exists($category, $totals)) {
                $totals[$category]++;
            }
        }

        return $totals;
    }

    public function formatSummaryForCenterInRange(int $centerId, Carbon $rangeStart, Carbon $rangeEnd): string
    {
        $counts = $this->countsForCenterInRange($centerId, $rangeStart, $rangeEnd);
        $parts = [];

        foreach (DashboardCategoryCodes::CATEGORY_CODES as $code) {
            $parts[] = "{$code}: {$counts[$code]}";
        }

        return implode(', ', $parts);
    }

    public function formatSummaryForImport(Import $import): string
    {
        if ($import->actual_period_start === null || $import->actual_period_end === null) {
            return $this->formatSummaryForCenterInRange(
                (int) $import->center_id,
                now()->startOfDay(),
                now()->endOfDay(),
            );
        }

        return $this->formatSummaryForCenterInRange(
            (int) $import->center_id,
            Carbon::parse($import->actual_period_start)->startOfDay(),
            Carbon::parse($import->actual_period_end)->endOfDay(),
        );
    }

    /**
     * @return Collection<int, MasterCashFlowRecord>
     */
    private function activeMastersForRange(int $centerId, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        $masterIds = DailyVersionMembership::query()
            ->whereIn('daily_version_id', function ($query) use ($centerId, $rangeStart, $rangeEnd): void {
                $query->select('daily_versions.id')
                    ->from('daily_versions')
                    ->join('active_daily_snapshots', 'active_daily_snapshots.daily_version_id', '=', 'daily_versions.id')
                    ->where('daily_versions.center_id', $centerId)
                    ->where('daily_versions.status', DailyVersionStatus::Active->value)
                    ->whereDate('active_daily_snapshots.business_date', '>=', $rangeStart->toDateString())
                    ->whereDate('active_daily_snapshots.business_date', '<=', $rangeEnd->toDateString());
            })
            ->distinct()
            ->pluck('master_cash_flow_record_id');

        if ($masterIds->isEmpty()) {
            return collect();
        }

        return MasterCashFlowRecord::query()
            ->withoutCenterScope()
            ->whereIn('id', $masterIds)
            ->get(['category_code']);
    }
}
