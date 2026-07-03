<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Services;

use App\Modules\Centers\Models\Center;
use App\Modules\Dashboards\Enums\DashboardPeriod;
use App\Modules\Dashboards\Support\CashierDashboardData;
use App\Modules\Dashboards\Support\DashboardMoney;
use App\Modules\Reports\Models\DailySummary;
use Illuminate\Support\Carbon;

final class CashierDashboardService
{
    private const RECENT_IMPORT_LIMIT = 3;

    public function __construct(
        private readonly OwnerDashboardService $ownerDashboardService,
        private readonly SubmissionStatusService $submissionStatusService,
    ) {}

    public function build(Center $center, ?Carbon $referenceDate = null): CashierDashboardData
    {
        $reference = ($referenceDate ?? now())->copy();
        $todayTtc = $this->totalTtcForPeriod($center->id, DashboardPeriod::Today, $reference);
        $yesterdayTtc = $this->totalTtcForPeriod($center->id, DashboardPeriod::Yesterday, $reference);
        $missingSubmissionDates = $this->submissionStatusService->missingSubmissionDates($center, $reference);
        $recentImports = $this->ownerDashboardService->recentImportRows(
            $center->id,
            self::RECENT_IMPORT_LIMIT,
        );

        return new CashierDashboardData(
            centerName: $center->name,
            referenceDate: $reference->timezone(config('app.timezone'))->format('d/m/Y'),
            todayTtc: $todayTtc,
            yesterdayTtc: $yesterdayTtc,
            activeRecordsToday: $this->activeRecordsToday($center->id, $reference),
            missingSubmissionDates: $missingSubmissionDates,
            recentImports: $recentImports,
            hasData: $todayTtc !== DashboardMoney::format(0)
                || $yesterdayTtc !== DashboardMoney::format(0)
                || $recentImports !== [],
        );
    }

    private function totalTtcForPeriod(int $centerId, DashboardPeriod $period, Carbon $reference): string
    {
        [$rangeStart, $rangeEnd] = $period->range($reference);

        $summaries = DailySummary::query()
            ->withoutCenterScope()
            ->where('center_id', $centerId)
            ->whereDate('business_date', '>=', $rangeStart->toDateString())
            ->whereDate('business_date', '<=', $rangeEnd->toDateString())
            ->pluck('total_ttc');

        return DashboardMoney::format(DashboardMoney::sum($summaries->all()));
    }

    private function activeRecordsToday(int $centerId, Carbon $reference): int
    {
        $summary = DailySummary::query()
            ->withoutCenterScope()
            ->where('center_id', $centerId)
            ->whereDate('business_date', $reference->toDateString())
            ->first();

        return $summary?->record_count ?? 0;
    }
}
