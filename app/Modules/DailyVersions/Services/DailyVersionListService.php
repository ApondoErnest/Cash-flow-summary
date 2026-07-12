<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Services;

use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\DailyVersions\Support\DailyVersionDetailData;
use App\Modules\DailyVersions\Support\DailyVersionListRow;
use App\Modules\DailyVersions\Support\DailyVersionStatusPresenter;
use App\Modules\Dashboards\Support\DashboardMoney;
use App\Support\Locale\LocalizedDateTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class DailyVersionListService
{
    /**
     * @param  array{status?: string|null, from?: string|null, to?: string|null}  $filters
     * @return LengthAwarePaginator<int, DailyVersion>
     */
    public function paginateForActiveCenter(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = DailyVersion::query()
            ->with(['submittedBy:id,name', 'approvedBy:id,name', 'import:id,original_filename', 'activeSnapshot'])
            ->orderByDesc('business_date')
            ->orderByDesc('version_number');

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        if (($filters['from'] ?? '') !== '') {
            $query->whereDate('business_date', '>=', $filters['from']);
        }

        if (($filters['to'] ?? '') !== '') {
            $query->whereDate('business_date', '<=', $filters['to']);
        }

        return $query->paginate($perPage);
    }

    public function toListRow(DailyVersion $version): DailyVersionListRow
    {
        $version->loadMissing(['submittedBy:id,name', 'approvedBy:id,name', 'activeSnapshot']);
        $badge = DailyVersionStatusPresenter::badge($version->status);

        return new DailyVersionListRow(
            id: $version->id,
            businessDate: $version->business_date->format('d/m/Y'),
            versionNumber: $version->version_number,
            statusLabel: $badge['label'],
            statusVariant: $badge['variant'],
            recordCount: $version->record_count,
            totalTtc: DashboardMoney::format($version->total_ttc),
            isActiveSnapshot: $version->activeSnapshot !== null,
            submittedByName: $version->submittedBy?->name,
            approvedByName: $version->approvedBy?->name,
        );
    }

    public function toDetail(DailyVersion $version): DailyVersionDetailData
    {
        $version->loadMissing([
            'submittedBy:id,name',
            'approvedBy:id,name',
            'import:id,original_filename',
            'activeSnapshot',
            'previousVersion:id,version_number',
        ]);

        $badge = DailyVersionStatusPresenter::badge($version->status);

        return new DailyVersionDetailData(
            id: $version->id,
            businessDate: $version->business_date->format('d/m/Y'),
            versionNumber: $version->version_number,
            statusLabel: $badge['label'],
            statusVariant: $badge['variant'],
            recordCount: $version->record_count,
            totalHt: DashboardMoney::format($version->total_ht),
            totalVat: DashboardMoney::format($version->total_vat),
            totalTtc: DashboardMoney::format($version->total_ttc),
            isActiveSnapshot: $version->activeSnapshot !== null,
            submittedByName: $version->submittedBy?->name,
            approvedByName: $version->approvedBy?->name,
            approvedAt: $version->approved_at !== null
                ? LocalizedDateTime::dateTime($version->approved_at)
                : null,
            rejectedReason: $version->rejected_reason,
            importId: $version->import_id,
            importFilename: $version->import?->original_filename,
            previousVersionNumber: $version->previousVersion?->version_number,
        );
    }

    /**
     * @return list<DailyVersionStatus>
     */
    public function filterableStatuses(): array
    {
        return DailyVersionStatus::cases();
    }
}
