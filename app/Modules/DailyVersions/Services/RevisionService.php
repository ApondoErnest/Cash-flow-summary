<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Services;

use App\Models\User;
use App\Modules\AuditLogging\Services\AuditLogger;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\CsvImports\Enums\DayComparisonResult;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportDayComparison;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\DailyVersions\Models\DailyVersionMembership;
use App\Modules\DailyVersions\Support\DailyDataset;
use App\Modules\DailyVersions\Support\ImportVersionApplyResult;
use App\Modules\Reports\Services\SummaryGenerationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RevisionService
{
    public function __construct(
        private readonly DailyDatasetService $dailyDatasetService,
        private readonly ActiveSnapshotService $activeSnapshotService,
        private readonly ActiveCenterContextService $activeCenterContext,
        private readonly SummaryGenerationService $summaryGenerationService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function applyImportComparisons(Import $import, User $submittedBy): ImportVersionApplyResult
    {
        $activatedDays = 0;
        $proposedRevisions = 0;

        $comparisons = ImportDayComparison::query()
            ->withoutCenterScope()
            ->where('import_id', $import->id)
            ->orderBy('business_date')
            ->get();

        foreach ($comparisons as $comparison) {
            if ($comparison->comparison_result === DayComparisonResult::New) {
                $version = $this->createVersionForComparison(
                    import: $import,
                    comparison: $comparison,
                    submittedBy: $submittedBy,
                    previousVersion: null,
                );

                $this->activeSnapshotService->activate($version);
                $activatedDays++;
            }

            if ($comparison->comparison_result === DayComparisonResult::RevisionRequired) {
                $previousVersion = $comparison->existingVersion;

                $this->createVersionForComparison(
                    import: $import,
                    comparison: $comparison,
                    submittedBy: $submittedBy,
                    previousVersion: $previousVersion,
                );

                $proposedRevisions++;
            }
        }

        if ($proposedRevisions > 0) {
            $this->auditLogger->record(
                event: 'revision.submitted',
                user: $submittedBy,
                centerId: (int) $import->center_id,
                resourceType: Import::class,
                resourceId: (int) $import->id,
                newValues: [
                    'import_id' => $import->id,
                    'proposed_revisions' => $proposedRevisions,
                ],
            );
        }

        return new ImportVersionApplyResult(
            activatedDays: $activatedDays,
            proposedRevisions: $proposedRevisions,
        );
    }

    public function approve(User $owner, DailyVersion $version): DailyVersion
    {
        $this->assertOwnerCanManageVersion($owner, $version->center);

        if ($version->status !== DailyVersionStatus::Proposed) {
            throw new InvalidArgumentException(__('daily_versions.approve.not_proposed'));
        }

        $this->activeSnapshotService->activate($version, $owner);

        $this->summaryGenerationService->queueRegeneration(
            $version->center_id,
            $version->business_date->toDateString(),
        );

        $this->auditLogger->record(
            event: 'revision.approved',
            user: $owner,
            centerId: (int) $version->center_id,
            resourceType: DailyVersion::class,
            resourceId: (int) $version->id,
            newValues: [
                'business_date' => $version->business_date?->toDateString(),
                'version_number' => $version->version_number,
                'import_id' => $version->import_id,
            ],
        );

        if ($version->import_id !== null) {
            $this->finalizeImportStatusIfApprovalsComplete(
                Import::query()->withoutCenterScope()->findOrFail($version->import_id),
            );
        }

        return $version->fresh();
    }

    public function reject(User $owner, DailyVersion $version, string $reason): DailyVersion
    {
        $this->assertOwnerCanManageVersion($owner, $version->center);

        if ($version->status !== DailyVersionStatus::Proposed) {
            throw new InvalidArgumentException(__('daily_versions.reject.not_proposed'));
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(__('daily_versions.reject.reason_required'));
        }

        $version->update([
            'status' => DailyVersionStatus::Rejected,
            'rejected_reason' => $reason,
        ]);

        $this->auditLogger->record(
            event: 'revision.rejected',
            user: $owner,
            centerId: (int) $version->center_id,
            resourceType: DailyVersion::class,
            resourceId: (int) $version->id,
            newValues: [
                'business_date' => $version->business_date?->toDateString(),
                'version_number' => $version->version_number,
                'reason' => $reason,
            ],
            reason: $reason,
        );

        if ($version->import_id !== null) {
            $this->finalizeImportStatusIfApprovalsComplete(
                Import::query()->withoutCenterScope()->findOrFail($version->import_id),
            );
        }

        return $version->fresh();
    }

    private function createVersionForComparison(
        Import $import,
        ImportDayComparison $comparison,
        User $submittedBy,
        ?DailyVersion $previousVersion,
    ): DailyVersion {
        $businessDate = $comparison->business_date->toDateString();
        $dataset = $this->dailyDatasetService->buildFromImport($import, $businessDate);

        if ($dataset->isEmpty()) {
            throw new InvalidArgumentException(__('daily_versions.create.empty_dataset'));
        }

        return DB::transaction(function () use (
            $import,
            $comparison,
            $submittedBy,
            $previousVersion,
            $dataset,
            $businessDate,
        ): DailyVersion {
            $version = DailyVersion::query()->create([
                'center_id' => $import->center_id,
                'business_date' => $businessDate,
                'import_id' => $import->id,
                'version_number' => $this->nextVersionNumber($import->center_id, $businessDate),
                'dataset_hash' => $dataset->datasetHash,
                'record_count' => $dataset->recordCount,
                'total_ht' => $dataset->totalHt,
                'total_vat' => $dataset->totalVat,
                'total_ttc' => $dataset->totalTtc,
                'status' => DailyVersionStatus::Proposed,
                'previous_version_id' => $previousVersion?->id,
                'submitted_by' => $submittedBy->id,
            ]);

            $this->syncMemberships($version, $dataset);

            $comparison->update(['proposed_version_id' => $version->id]);

            return $version;
        });
    }

    private function syncMemberships(DailyVersion $version, DailyDataset $dataset): void
    {
        foreach ($dataset->masterRecordIds as $masterRecordId) {
            DailyVersionMembership::query()->create([
                'daily_version_id' => $version->id,
                'master_cash_flow_record_id' => $masterRecordId,
            ]);
        }
    }

    private function nextVersionNumber(int $centerId, string $businessDate): int
    {
        $currentMax = DailyVersion::query()
            ->withoutCenterScope()
            ->where('center_id', $centerId)
            ->whereDate('business_date', $businessDate)
            ->max('version_number');

        return ((int) $currentMax) + 1;
    }

    private function finalizeImportStatusIfApprovalsComplete(Import $import): void
    {
        if ($import->status !== ImportStatus::AwaitingOwnerApproval) {
            return;
        }

        $pendingRevisions = DailyVersion::query()
            ->withoutCenterScope()
            ->where('import_id', $import->id)
            ->where('status', DailyVersionStatus::Proposed)
            ->count();

        if ($pendingRevisions > 0) {
            return;
        }

        $import->update([
            'status' => $this->resolveCompletedImportStatus($import),
        ]);
    }

    private function resolveCompletedImportStatus(Import $import): ImportStatus
    {
        if ($import->duplicate_within_file_count > 0 || $import->historical_duplicate_count > 0) {
            return ImportStatus::CompletedWithDuplicates;
        }

        $warnings = $import->warnings ?? [];

        if ($warnings !== []) {
            return ImportStatus::CompletedWithWarnings;
        }

        return ImportStatus::Completed;
    }

    private function assertOwnerCanManageVersion(User $user, Center $center): void
    {
        if (! $user->isOwner()) {
            throw new AuthorizationException(__('daily_versions.owner_only'));
        }

        $activeCenter = $this->activeCenterContext->resolve($user);

        if ($activeCenter === null || $activeCenter->centerId !== (int) $center->id) {
            throw new AuthorizationException(__('center.active_center_invalid'));
        }
    }
}
