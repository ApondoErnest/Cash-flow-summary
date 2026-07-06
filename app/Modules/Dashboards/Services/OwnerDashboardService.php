<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Services;

use App\Modules\Centers\Models\Center;
use App\Modules\CsvImports\Enums\CompletionStatus;
use App\Modules\CsvImports\Enums\FinancialStatus;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\DailyVersionMembership;
use App\Modules\Dashboards\Enums\DashboardPeriod;
use App\Modules\Dashboards\Enums\DashboardTrendGranularity;
use App\Modules\Dashboards\Support\DashboardMoney;
use App\Modules\Dashboards\Support\DashboardCategoryCodes;
use App\Modules\Dashboards\Support\OwnerDashboardAlert;
use App\Modules\Dashboards\Support\OwnerDashboardCategoryCount;
use App\Modules\Dashboards\Support\OwnerDashboardData;
use App\Modules\Dashboards\Support\OwnerDashboardImportRow;
use App\Modules\Dashboards\Support\OwnerDashboardTrendPoint;
use App\Modules\Reports\Models\Anomaly;
use App\Modules\Reports\Services\ReportQueryService;
use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class OwnerDashboardService
{
    public function __construct(
        private readonly SubmissionStatusService $submissionStatusService,
        private readonly ReportQueryService $reportQueryService,
    ) {}

    public function build(
        Center $center,
        DashboardPeriod $period,
        DashboardTrendGranularity $trendGranularity,
        ?Carbon $referenceDate = null,
        ?Carbon $customFrom = null,
        ?Carbon $customTo = null,
    ): OwnerDashboardData {
        $reference = ($referenceDate ?? now())->copy();
        [$rangeStart, $rangeEnd] = $period->range($reference, $customFrom, $customTo);

        $periodTotals = $this->reportQueryService->periodTotals(
            $center->id,
            $period,
            $referenceDate,
            $customFrom,
            $customTo,
        );

        $masterStats = $this->masterStatsForRange($center->id, $rangeStart, $rangeEnd);
        $dimensionStats = $this->dimensionStatsForRange($center->id, $rangeStart, $rangeEnd);
        $duplicatesIgnored = $this->duplicatesIgnoredForRange($center->id, $rangeStart, $rangeEnd);
        $trend = $this->buildTrend($center->id, $trendGranularity, $reference);
        $trendMax = $trend->max(static fn (OwnerDashboardTrendPoint $point): float => $point->totalTtcNumeric) ?? 0.0;
        $recentImports = $this->recentImports($center->id);
        $lastImport = Import::query()
            ->withoutCenterScope()
            ->where('center_id', $center->id)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->first();

        return new OwnerDashboardData(
            centerName: $center->name,
            periodLabel: $period->label($customFrom, $customTo),
            totalTtc: DashboardMoney::format($periodTotals['ttc']),
            totalHt: DashboardMoney::format($periodTotals['ht']),
            totalVat: DashboardMoney::format($periodTotals['vat']),
            uniqueRecords: $masterStats['unique'],
            completedCount: $masterStats['completed'],
            unfinishedCount: $masterStats['unfinished'],
            zeroValueCount: $masterStats['zero_value'],
            duplicatesIgnored: $duplicatesIgnored,
            categoryCounts: $dimensionStats['categories'],
            cvInspectionCount: $dimensionStats['cv'],
            trend: $trend->all(),
            trendMaxTtc: $trendMax,
            alerts: $this->buildAlerts($center, $reference),
            recentImports: $recentImports->all(),
            lastImportAt: $lastImport?->completed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            hasData: $periodTotals['ttc'] > 0
                || $periodTotals['recordCount'] > 0
                || $recentImports->isNotEmpty(),
        );
    }

    /**
     * @return array{unique: int, completed: int, unfinished: int, zero_value: int}
     */
    private function masterStatsForRange(int $centerId, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $masters = $this->activeMastersForRange($centerId, $rangeStart, $rangeEnd);

        if ($masters->isEmpty()) {
            return [
                'unique' => 0,
                'completed' => 0,
                'unfinished' => 0,
                'zero_value' => 0,
            ];
        }

        return [
            'unique' => $masters->count(),
            'completed' => $masters->where('completion_status', CompletionStatus::Completed)->count(),
            'unfinished' => $masters->where('completion_status', CompletionStatus::Unfinished)->count(),
            'zero_value' => $masters->where('financial_status', FinancialStatus::ZeroValue)->count(),
        ];
    }

    /**
     * @return array{categories: list<OwnerDashboardCategoryCount>, cv: int}
     */
    private function dimensionStatsForRange(int $centerId, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $masters = $this->activeMastersForRange($centerId, $rangeStart, $rangeEnd);

        /** @var array<string, int> $categoryTotals */
        $categoryTotals = array_fill_keys(DashboardCategoryCodes::CATEGORY_CODES, 0);
        $cvCount = 0;

        foreach ($masters as $master) {
            $category = DashboardCategoryCodes::normalizeCategory((string) $master->category_code);

            if (array_key_exists($category, $categoryTotals)) {
                $categoryTotals[$category]++;
            }

            if (DashboardCategoryCodes::normalizeInspectionType((string) $master->inspection_type_code) === DashboardCategoryCodes::CV_INSPECTION_TYPE) {
                $cvCount++;
            }
        }

        $categories = [];

        foreach (DashboardCategoryCodes::CATEGORY_CODES as $code) {
            $categories[] = new OwnerDashboardCategoryCount($code, $categoryTotals[$code]);
        }

        return [
            'categories' => $categories,
            'cv' => $cvCount,
        ];
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
            ->get([
                'completion_status',
                'financial_status',
                'category_code',
                'inspection_type_code',
            ]);
    }

    private function duplicatesIgnoredForRange(int $centerId, Carbon $rangeStart, Carbon $rangeEnd): int
    {
        return (int) Import::query()
            ->withoutCenterScope()
            ->where('center_id', $centerId)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->selectRaw('COALESCE(SUM(duplicate_within_file_count + historical_duplicate_count), 0) as aggregate')
            ->value('aggregate');
    }

    /**
     * @return Collection<int, OwnerDashboardTrendPoint>
     */
    private function buildTrend(int $centerId, DashboardTrendGranularity $granularity, Carbon $reference): Collection
    {
        $grouped = $this->reportQueryService->trendTtcTotals($centerId, $granularity, $reference);

        return collect($grouped)
            ->map(function (float $total, string $key) use ($granularity): OwnerDashboardTrendPoint {
                $label = match ($granularity) {
                    DashboardTrendGranularity::Daily => Carbon::parse($key)->format('d/m'),
                    DashboardTrendGranularity::Weekly => Carbon::parse($key)->format('d/m'),
                    DashboardTrendGranularity::Monthly => Carbon::createFromFormat('Y-m', $key)->format('m/Y'),
                    DashboardTrendGranularity::Yearly => $key,
                };

                return new OwnerDashboardTrendPoint(
                    label: $label,
                    totalTtc: DashboardMoney::format($total),
                    totalTtcNumeric: $total,
                );
            })
            ->values();
    }

    /**
     * @return Collection<int, OwnerDashboardImportRow>
     */
    private function recentImports(int $centerId, int $limit = 10): Collection
    {
        return Import::query()
            ->withoutCenterScope()
            ->where('center_id', $centerId)
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function (Import $import): OwnerDashboardImportRow {
                return new OwnerDashboardImportRow(
                    id: $import->id,
                    importedAt: ($import->completed_at ?? $import->created_at)
                        ->timezone(config('app.timezone'))
                        ->format('Y-m-d H:i'),
                    filename: $import->original_filename,
                    totalTtc: DashboardMoney::format($import->calculated_ttc ?? $import->source_ttc ?? 0),
                    status: $import->status,
                );
            });
    }

    /**
     * @return list<OwnerDashboardAlert>
     */
    private function buildAlerts(Center $center, Carbon $reference): array
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
                message: trans_choice('dashboard.alerts.revision_pending', $pendingRevisions, ['count' => $pendingRevisions]),
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

        $missingDates = $this->submissionStatusService->missingSubmissionDates($center, $reference);

        if ($missingDates !== []) {
            $alerts[] = new OwnerDashboardAlert(
                type: 'warning',
                message: trans_choice('dashboard.alerts.missing_submission', count($missingDates), ['count' => count($missingDates)]),
                href: route('reports.index'),
            );
        }

        $whatsappFailures = WhatsappMessage::query()
            ->where('center_id', $center->id)
            ->where('status', WhatsappMessageStatus::Failed)
            ->where('created_at', '>=', $reference->copy()->subDays(30))
            ->count();

        if ($whatsappFailures > 0) {
            $alerts[] = new OwnerDashboardAlert(
                type: 'error',
                message: trans_choice('dashboard.alerts.whatsapp_failed', $whatsappFailures, ['count' => $whatsappFailures]),
                href: route('whatsapp-history.index'),
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

    /**
     * @return list<OwnerDashboardTrendPoint>
     */
    public function trendPoints(
        int $centerId,
        DashboardTrendGranularity $granularity,
        ?Carbon $referenceDate = null,
    ): array {
        $reference = ($referenceDate ?? now())->copy();

        return $this->buildTrend($centerId, $granularity, $reference)->all();
    }

    /**
     * @return list<OwnerDashboardImportRow>
     */
    public function recentImportRows(int $centerId, int $limit = 10): array
    {
        return $this->recentImports($centerId, $limit)->all();
    }
}
