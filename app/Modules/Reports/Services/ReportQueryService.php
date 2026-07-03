<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Centers\Models\Center;
use App\Modules\Dashboards\Enums\DashboardPeriod;
use App\Modules\Dashboards\Services\SubmissionStatusService;
use App\Modules\Dashboards\Support\DashboardMoney;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\ActiveDailySnapshot;
use App\Modules\Reports\Models\DailySummary;
use App\Modules\Reports\Support\CenterReportData;
use App\Modules\Reports\Support\ReportDailyRow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ReportQueryService
{
    public function __construct(
        private readonly SubmissionStatusService $submissionStatusService,
    ) {}

    public function buildCenterReport(
        Center $center,
        DashboardPeriod $period,
        ?Carbon $referenceDate = null,
        ?Carbon $customFrom = null,
        ?Carbon $customTo = null,
    ): CenterReportData {
        $reference = ($referenceDate ?? now())->copy();
        [$rangeStart, $rangeEnd] = $period->range($reference, $customFrom, $customTo);

        $snapshots = $this->activeSnapshotsForRange($center->id, $rangeStart, $rangeEnd);
        $summaries = $this->summariesForSnapshots($center->id, $snapshots);

        $dailyRows = [];
        $totalHt = 0.0;
        $totalVat = 0.0;
        $totalTtc = 0.0;
        $recordCount = 0;

        foreach ($snapshots as $snapshot) {
            $metrics = $this->resolveSnapshotMetrics($snapshot, $summaries);
            $dailyRows[] = $metrics['row'];
            $totalHt += $metrics['ht'];
            $totalVat += $metrics['vat'];
            $totalTtc += $metrics['ttc'];
            $recordCount += $metrics['recordCount'];
        }

        return new CenterReportData(
            centerName: $center->name,
            periodLabel: $period->label($customFrom, $customTo),
            totalHt: DashboardMoney::format($totalHt),
            totalVat: DashboardMoney::format($totalVat),
            totalTtc: DashboardMoney::format($totalTtc),
            recordCount: $recordCount,
            daysWithData: count($dailyRows),
            missingSubmissionDates: $this->submissionStatusService->missingSubmissionDatesBetween(
                $center,
                $rangeStart,
                $rangeEnd,
            ),
            dailyRows: $dailyRows,
            hasData: $dailyRows !== [],
        );
    }

    /**
     * @return Collection<int, ActiveDailySnapshot>
     */
    private function activeSnapshotsForRange(int $centerId, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        return ActiveDailySnapshot::query()
            ->withoutCenterScope()
            ->where('center_id', $centerId)
            ->whereDate('business_date', '>=', $rangeStart->toDateString())
            ->whereDate('business_date', '<=', $rangeEnd->toDateString())
            ->with(['dailyVersion' => static fn ($query) => $query->withoutCenterScope()])
            ->orderByDesc('business_date')
            ->get()
            ->filter(static function (ActiveDailySnapshot $snapshot): bool {
                return $snapshot->dailyVersion?->status === DailyVersionStatus::Active;
            })
            ->values();
    }

    /**
     * @param  Collection<int, ActiveDailySnapshot>  $snapshots
     * @return Collection<string, DailySummary>
     */
    private function summariesForSnapshots(int $centerId, Collection $snapshots): Collection
    {
        if ($snapshots->isEmpty()) {
            return collect();
        }

        $dates = $snapshots
            ->map(static fn (ActiveDailySnapshot $snapshot): string => $snapshot->business_date->toDateString())
            ->all();

        return DailySummary::query()
            ->withoutCenterScope()
            ->where('center_id', $centerId)
            ->whereIn('business_date', $dates)
            ->get()
            ->keyBy(static fn (DailySummary $summary): string => $summary->business_date->toDateString());
    }

    /**
     * @param  Collection<string, DailySummary>  $summaries
     * @return array{row: ReportDailyRow, ht: float, vat: float, ttc: float, recordCount: int}
     */
    private function resolveSnapshotMetrics(ActiveDailySnapshot $snapshot, Collection $summaries): array
    {
        $version = $snapshot->dailyVersion;
        $businessDate = $snapshot->business_date->timezone(config('app.timezone'));
        $dateKey = $businessDate->toDateString();
        $summary = $summaries->get($dateKey);

        if ($summary !== null && (int) $summary->daily_version_id === (int) $snapshot->daily_version_id) {
            return [
                'row' => new ReportDailyRow(
                    businessDate: $businessDate->format('d/m/Y'),
                    businessDateIso: $dateKey,
                    recordCount: $summary->record_count,
                    totalHt: DashboardMoney::format($summary->total_ht),
                    totalVat: DashboardMoney::format($summary->total_vat),
                    totalTtc: DashboardMoney::format($summary->total_ttc),
                ),
                'ht' => (float) $summary->total_ht,
                'vat' => (float) $summary->total_vat,
                'ttc' => (float) $summary->total_ttc,
                'recordCount' => $summary->record_count,
            ];
        }

        return [
            'row' => new ReportDailyRow(
                businessDate: $businessDate->format('d/m/Y'),
                businessDateIso: $dateKey,
                recordCount: (int) ($version?->record_count ?? 0),
                totalHt: DashboardMoney::format($version?->total_ht ?? 0),
                totalVat: DashboardMoney::format($version?->total_vat ?? 0),
                totalTtc: DashboardMoney::format($version?->total_ttc ?? 0),
            ),
            'ht' => (float) ($version?->total_ht ?? 0),
            'vat' => (float) ($version?->total_vat ?? 0),
            'ttc' => (float) ($version?->total_ttc ?? 0),
            'recordCount' => (int) ($version?->record_count ?? 0),
        ];
    }
}
