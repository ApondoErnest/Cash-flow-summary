<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Services;

use App\Models\User;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\ActiveDailySnapshot;
use App\Modules\DailyVersions\Models\DailyVersion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ActiveSnapshotService
{
    public function activate(DailyVersion $version, ?User $approvedBy = null): ActiveDailySnapshot
    {
        if ($version->status === DailyVersionStatus::Rejected || $version->status === DailyVersionStatus::Invalid) {
            throw new InvalidArgumentException(__('daily_versions.activate.not_allowed'));
        }

        return DB::transaction(function () use ($version, $approvedBy): ActiveDailySnapshot {
            $snapshot = ActiveDailySnapshot::query()
                ->withoutCenterScope()
                ->where('center_id', $version->center_id)
                ->whereDate('business_date', $version->business_date)
                ->lockForUpdate()
                ->first();

            if ($snapshot !== null) {
                $currentActive = DailyVersion::query()
                    ->withoutCenterScope()
                    ->find($snapshot->daily_version_id);

                if ($currentActive !== null && (int) $currentActive->id !== (int) $version->id) {
                    $currentActive->update(['status' => DailyVersionStatus::Superseded]);
                }
            }

            $version->update([
                'status' => DailyVersionStatus::Active,
                'approved_by' => $approvedBy?->id,
                'approved_at' => now(),
            ]);

            return ActiveDailySnapshot::query()
                ->withoutCenterScope()
                ->updateOrCreate(
                    [
                        'center_id' => $version->center_id,
                        'business_date' => $version->business_date,
                    ],
                    [
                        'daily_version_id' => $version->id,
                        'activated_at' => now(),
                    ],
                );
        });
    }
}
