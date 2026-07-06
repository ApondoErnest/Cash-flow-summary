<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\ActiveDailySnapshot;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\DailyVersions\Models\DailyVersionMembership;
use App\Modules\Reports\Jobs\GenerateDailySummaryJob;
use App\Modules\Reports\Models\DailySummary;
use App\Modules\Reports\Models\SummaryBreakdown;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SummaryGenerationService
{
    /**
     * @var list<string>
     */
    private const BREAKDOWN_KEYS = [
        'category_code',
        'inspection_type_code',
    ];

    public function regenerate(int $centerId, string $businessDate): ?DailySummary
    {
        $snapshot = ActiveDailySnapshot::query()
            ->withoutCenterScope()
            ->where('center_id', $centerId)
            ->whereDate('business_date', $businessDate)
            ->first();

        if ($snapshot === null) {
            return null;
        }

        $version = DailyVersion::query()
            ->withoutCenterScope()
            ->find($snapshot->daily_version_id);

        if ($version === null || $version->status !== DailyVersionStatus::Active) {
            return null;
        }

        /** @var Collection<int, MasterCashFlowRecord> $masters */
        $masters = MasterCashFlowRecord::query()
            ->withoutCenterScope()
            ->whereIn('id', DailyVersionMembership::query()
                ->where('daily_version_id', $version->id)
                ->pluck('master_cash_flow_record_id'))
            ->orderBy('id')
            ->get();

        return DB::transaction(function () use ($centerId, $businessDate, $version, $masters): DailySummary {
            $payload = [
                'daily_version_id' => $version->id,
                'record_count' => $masters->count(),
                'total_ht' => $this->money($masters->sum(static fn (MasterCashFlowRecord $record): float => (float) $record->net_amount)),
                'total_vat' => $this->money($masters->sum(static fn (MasterCashFlowRecord $record): float => (float) $record->vat_amount)),
                'total_ttc' => $this->money($masters->sum(static fn (MasterCashFlowRecord $record): float => (float) $record->gross_amount)),
                'generated_at' => now(),
            ];

            $summary = DailySummary::query()
                ->withoutCenterScope()
                ->where('center_id', $centerId)
                ->whereDate('business_date', $businessDate)
                ->first();

            if ($summary === null) {
                $summary = DailySummary::query()->withoutCenterScope()->create(array_merge([
                    'center_id' => $centerId,
                    'business_date' => $businessDate,
                ], $payload));
            } else {
                $summary->update($payload);
            }

            $summary->breakdowns()->delete();

            foreach (self::BREAKDOWN_KEYS as $breakdownKey) {
                /** @var Collection<string, Collection<int, MasterCashFlowRecord>> $groups */
                $groups = $masters->groupBy(static fn (MasterCashFlowRecord $record): string => (string) $record->{$breakdownKey});

                foreach ($groups as $breakdownValue => $group) {
                    SummaryBreakdown::query()->create([
                        'daily_summary_id' => $summary->id,
                        'breakdown_key' => $breakdownKey,
                        'breakdown_value' => $breakdownValue,
                        'record_count' => $group->count(),
                        'total_ht' => $this->money($group->sum(static fn (MasterCashFlowRecord $record): float => (float) $record->net_amount)),
                        'total_vat' => $this->money($group->sum(static fn (MasterCashFlowRecord $record): float => (float) $record->vat_amount)),
                        'total_ttc' => $this->money($group->sum(static fn (MasterCashFlowRecord $record): float => (float) $record->gross_amount)),
                    ]);
                }
            }

            return $summary->fresh(['breakdowns']);
        });
    }

    public function queueRegeneration(int $centerId, string $businessDate): void
    {
        if (config('csv_verification.process_synchronously')) {
            $this->regenerate($centerId, $businessDate);

            return;
        }

        GenerateDailySummaryJob::dispatch($centerId, $businessDate);
    }

    public function queueRegenerationForImport(Import $import): void
    {
        $activeVersions = DailyVersion::query()
            ->withoutCenterScope()
            ->where('import_id', $import->id)
            ->where('status', DailyVersionStatus::Active)
            ->get(['center_id', 'business_date']);

        foreach ($activeVersions as $version) {
            $this->queueRegeneration(
                $version->center_id,
                $version->business_date->toDateString(),
            );
        }
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
