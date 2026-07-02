<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Services;

use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\DailyVersions\Support\RevisionQueueRow;
use App\Modules\Dashboards\Support\DashboardMoney;
use Illuminate\Support\Collection;

final class RevisionQueueService
{
    /**
     * @return Collection<int, DailyVersion>
     */
    public function pendingForActiveCenter(): Collection
    {
        return DailyVersion::query()
            ->where('status', DailyVersionStatus::Proposed)
            ->with([
                'previousVersion',
                'submittedBy:id,name',
                'import:id,original_filename',
            ])
            ->orderBy('business_date')
            ->orderBy('version_number')
            ->get();
    }

    public function toQueueRow(DailyVersion $version): RevisionQueueRow
    {
        $version->loadMissing(['previousVersion', 'submittedBy:id,name', 'import:id,original_filename']);
        $previous = $version->previousVersion;

        return new RevisionQueueRow(
            id: $version->id,
            businessDate: $version->business_date->format('d/m/Y'),
            versionNumber: $version->version_number,
            submittedByName: $version->submittedBy?->name ?? '—',
            existingHt: DashboardMoney::format($previous?->total_ht ?? 0),
            existingVat: DashboardMoney::format($previous?->total_vat ?? 0),
            existingTtc: DashboardMoney::format($previous?->total_ttc ?? 0),
            proposedHt: DashboardMoney::format($version->total_ht),
            proposedVat: DashboardMoney::format($version->total_vat),
            proposedTtc: DashboardMoney::format($version->total_ttc),
            importId: $version->import_id,
            importFilename: $version->import?->original_filename,
        );
    }

    public function findPending(int $versionId): ?DailyVersion
    {
        return DailyVersion::query()
            ->whereKey($versionId)
            ->where('status', DailyVersionStatus::Proposed)
            ->first();
    }
}
