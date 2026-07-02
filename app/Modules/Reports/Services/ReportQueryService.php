<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Centers\Models\Center;
use App\Modules\Dashboards\Enums\DashboardPeriod;
use App\Modules\Dashboards\Services\SubmissionStatusService;
use App\Modules\Dashboards\Support\DashboardMoney;
use App\Modules\Reports\Models\DailySummary;
use App\Modules\Reports\Support\CenterReportData;
use App\Modules\Reports\Support\ReportDailyRow;
use Illuminate\Support\Carbon;

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

        $summaries = DailySummary::query()
            ->withoutCenterScope()
            ->where('center_id', $center->id)
            ->whereDate('business_date', '>=', $rangeStart->toDateString())
            ->whereDate('business_date', '<=', $rangeEnd->toDateString())
            ->orderByDesc('business_date')
            ->get();

        $dailyRows = $summaries
            ->map(static function (DailySummary $summary): ReportDailyRow {
                $businessDate = $summary->business_date->timezone(config('app.timezone'));

                return new ReportDailyRow(
                    businessDate: $businessDate->format('d/m/Y'),
                    businessDateIso: $businessDate->toDateString(),
                    recordCount: $summary->record_count,
                    totalHt: DashboardMoney::format($summary->total_ht),
                    totalVat: DashboardMoney::format($summary->total_vat),
                    totalTtc: DashboardMoney::format($summary->total_ttc),
                );
            })
            ->all();

        return new CenterReportData(
            centerName: $center->name,
            periodLabel: $period->label($customFrom, $customTo),
            totalHt: DashboardMoney::format(DashboardMoney::sum($summaries->pluck('total_ht')->all())),
            totalVat: DashboardMoney::format(DashboardMoney::sum($summaries->pluck('total_vat')->all())),
            totalTtc: DashboardMoney::format(DashboardMoney::sum($summaries->pluck('total_ttc')->all())),
            recordCount: (int) $summaries->sum('record_count'),
            daysWithData: $summaries->count(),
            missingSubmissionDates: $this->submissionStatusService->missingSubmissionDatesBetween(
                $center,
                $rangeStart,
                $rangeEnd,
            ),
            dailyRows: $dailyRows,
            hasData: $summaries->isNotEmpty(),
        );
    }
}
