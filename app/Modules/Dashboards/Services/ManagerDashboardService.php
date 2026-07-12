<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Services;

use App\Modules\Centers\Models\Center;
use App\Modules\CsvImports\Enums\CompletionStatus;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\Dashboards\Enums\DashboardPeriod;
use App\Modules\Dashboards\Enums\DashboardTrendGranularity;
use App\Modules\Dashboards\Support\DashboardMoney;
use App\Modules\Dashboards\Support\ManagerDashboardData;
use App\Modules\Dashboards\Support\OwnerDashboardAlert;
use App\Modules\Dashboards\Support\OwnerDashboardTrendPoint;
use App\Modules\Reports\Models\Anomaly;
use App\Modules\Reports\Services\ReportQueryService;
use Illuminate\Support\Carbon;

final class ManagerDashboardService
{
    public function __construct(
        private readonly OwnerDashboardService $ownerDashboardService,
        private readonly SubmissionStatusService $submissionStatusService,
        private readonly ReportQueryService $reportQueryService,
    ) {}

    public function build(
        Center $center,
        DashboardTrendGranularity $trendGranularity,
        ?Carbon $referenceDate = null,
    ): ManagerDashboardData {
        $reference = ($referenceDate ?? now())->copy();

        $todayTtc = $this->totalTtcForPeriod($center->id, DashboardPeriod::Today, $reference);
        $yesterdayTtc = $this->totalTtcForPeriod($center->id, DashboardPeriod::Yesterday, $reference);
        $weekTtc = $this->totalTtcForPeriod($center->id, DashboardPeriod::Week, $reference);
        $monthTtc = $this->totalTtcForPeriod($center->id, DashboardPeriod::Month, $reference);
        $yearTtc = $this->totalTtcForPeriod($center->id, DashboardPeriod::Year, $reference);
        $trend = $this->ownerDashboardService->trendPoints($center->id, $trendGranularity, $reference);
        $trendMax = collect($trend)->max(static fn (OwnerDashboardTrendPoint $point): float => $point->totalTtcNumeric) ?? 0.0;
        $recentImports = $this->ownerDashboardService->recentImportRows($center->id);
        $missingSubmissionDates = $this->submissionStatusService->missingSubmissionDates($center, $reference);
        $lastImport = Import::query()
            ->withoutCenterScope()
            ->where('center_id', $center->id)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->first();

        return new ManagerDashboardData(
            centerName: $center->name,
            todayTtc: $todayTtc,
            yesterdayTtc: $yesterdayTtc,
            weekTtc: $weekTtc,
            monthTtc: $monthTtc,
            yearTtc: $yearTtc,
            activeRecordsToday: $this->activeRecordsToday($center->id, $reference),
            trend: $trend,
            trendMaxTtc: $trendMax,
            alerts: $this->buildAlerts($center, $reference, $missingSubmissionDates),
            missingSubmissionDates: $missingSubmissionDates,
            recentImports: $recentImports,
            lastImportAt: $lastImport?->completed_at !== null
                ? \App\Support\Locale\LocalizedDateTime::dateTime($lastImport->completed_at)
                : null,
            hasData: $todayTtc !== DashboardMoney::format(0)
                || $yesterdayTtc !== DashboardMoney::format(0)
                || $weekTtc !== DashboardMoney::format(0)
                || $monthTtc !== DashboardMoney::format(0)
                || $yearTtc !== DashboardMoney::format(0)
                || $recentImports !== [],
        );
    }

    private function totalTtcForPeriod(int $centerId, DashboardPeriod $period, Carbon $reference): string
    {
        $totals = $this->reportQueryService->periodTotals($centerId, $period, $reference);

        return DashboardMoney::format($totals['ttc']);
    }

    private function activeRecordsToday(int $centerId, Carbon $reference): int
    {
        return $this->reportQueryService->periodTotals(
            $centerId,
            DashboardPeriod::Today,
            $reference,
        )['recordCount'];
    }

    /**
     * @param  list<string>  $missingSubmissionDates
     * @return list<OwnerDashboardAlert>
     */
    private function buildAlerts(Center $center, Carbon $reference, array $missingSubmissionDates): array
    {
        $alerts = [];

        $pendingRevisions = Import::query()
            ->withoutCenterScope()
            ->where('center_id', $center->id)
            ->where('status', ImportStatus::AwaitingOwnerApproval)
            ->count();

        if ($pendingRevisions > 0) {
            $alerts[] = new OwnerDashboardAlert(
                type: 'warning',
                message: trans_choice('dashboard.manager.alerts.correction_pending', $pendingRevisions, ['count' => $pendingRevisions]),
                href: route('revisions.index'),
            );
        }

        $failedImports = Import::query()
            ->withoutCenterScope()
            ->where('center_id', $center->id)
            ->where('status', ImportStatus::Failed)
            ->where('created_at', '>=', $reference->copy()->subDays(30))
            ->count();

        if ($failedImports > 0) {
            $alerts[] = new OwnerDashboardAlert(
                type: 'error',
                message: trans_choice('dashboard.alerts.failed_import', $failedImports, ['count' => $failedImports]),
                href: route('imports.index'),
            );
        }

        $probableDuplicates = Anomaly::query()
            ->withoutCenterScope()
            ->where('center_id', $center->id)
            ->whereNull('resolved_at')
            ->where('type', 'probable_duplicate')
            ->count();

        if ($probableDuplicates > 0) {
            $alerts[] = new OwnerDashboardAlert(
                type: 'warning',
                message: trans_choice('dashboard.alerts.probable_duplicate', $probableDuplicates, ['count' => $probableDuplicates]),
                href: route('anomalies.index'),
            );
        }

        $unfinishedWarnings = MasterCashFlowRecord::query()
            ->withoutCenterScope()
            ->where('center_id', $center->id)
            ->where('completion_status', CompletionStatus::Unfinished)
            ->where('first_seen_at', '>=', $reference->copy()->subDays(7))
            ->count();

        if ($unfinishedWarnings > 0) {
            $alerts[] = new OwnerDashboardAlert(
                type: 'info',
                message: trans_choice('dashboard.alerts.unfinished_records', $unfinishedWarnings, ['count' => $unfinishedWarnings]),
                href: route('records.index'),
            );
        }

        if ($missingSubmissionDates !== []) {
            $alerts[] = new OwnerDashboardAlert(
                type: 'warning',
                message: trans_choice('dashboard.alerts.missing_submission', count($missingSubmissionDates), ['count' => count($missingSubmissionDates)]),
                href: route('reports.index'),
            );
        }

        if ($alerts === []) {
            $alerts[] = new OwnerDashboardAlert(
                type: 'success',
                message: __('dashboard.alerts.all_clear'),
            );
        }

        return $alerts;
    }
}
