<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Services;

use App\Models\User;
use App\Modules\AuditLogging\Services\AuditLogger;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Exceptions\ExactFileDuplicateException;
use App\Modules\CsvImports\Jobs\ProcessImportJob;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\CsvVerification\Services\CorrectionSubmissionService;
use App\Modules\CsvVerification\Services\VerificationCleanupService;
use App\Modules\CsvVerification\Services\VerificationService;
use App\Modules\DailyVersions\Services\RevisionService;
use App\Modules\DailyVersions\Services\VersionComparisonService;
use App\Modules\DailyVersions\Support\ImportVersionApplyResult;
use App\Modules\DuplicateDetection\Services\MasterLedgerService;
use App\Modules\DuplicateDetection\Support\MasterLedgerProcessResult;
use App\Modules\Reports\Services\SummaryGenerationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class ImportService
{
    public function __construct(
        private readonly ActiveCenterContextService $activeCenterContext,
        private readonly FileStorageService $fileStorageService,
        private readonly ImportRowService $importRowService,
        private readonly MasterLedgerService $masterLedgerService,
        private readonly VersionComparisonService $versionComparisonService,
        private readonly RevisionService $revisionService,
        private readonly SummaryGenerationService $summaryGenerationService,
        private readonly VerificationCleanupService $verificationCleanupService,
        private readonly CorrectionSubmissionService $correctionSubmissionService,
        private readonly AuditLogger $auditLogger,
        private readonly VerificationService $verificationService,
    ) {}

    public function commitFromVerification(User $user, string $token): Import
    {
        $verification = ImportVerification::query()
            ->withoutCenterScope()
            ->where('token', $token)
            ->first();

        if ($verification === null) {
            throw new InvalidArgumentException(__('csv_import.commit.not_found'));
        }

        $this->assertUserCanCommitForCenter($user, $verification->center);
        $this->correctionSubmissionService->assertModeAllowed($user, $verification->import_mode);

        if ((int) $verification->user_id !== (int) $user->id) {
            throw new AuthorizationException(__('center.cross_center_forbidden'));
        }

        if ($verification->status === VerificationStatus::Imported) {
            throw new InvalidArgumentException(__('csv_import.commit.already_committed'));
        }

        if ($this->verificationService->isExpired($verification)) {
            throw new InvalidArgumentException(__('csv_verification.verification.expired'));
        }

        if ($verification->status !== VerificationStatus::Ready) {
            throw new InvalidArgumentException(__('csv_import.commit.not_ready'));
        }

        $import = DB::transaction(function () use ($user, $verification): Import {
            $locked = ImportVerification::query()
                ->withoutCenterScope()
                ->whereKey($verification->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === VerificationStatus::Imported) {
                throw new InvalidArgumentException(__('csv_import.commit.already_committed'));
            }

            if ($this->verificationService->isExpired($locked)) {
                throw new InvalidArgumentException(__('csv_verification.verification.expired'));
            }

            if ($locked->status !== VerificationStatus::Ready) {
                throw new InvalidArgumentException(__('csv_import.commit.not_ready'));
            }

            $duplicate = Import::query()
                ->withoutCenterScope()
                ->where('center_id', $locked->center_id)
                ->where('file_hash', $locked->file_hash)
                ->lockForUpdate()
                ->first();

            if ($duplicate !== null) {
                return $this->markExactFileDuplicate($user, $locked, $duplicate);
            }

            $footer = $locked->footer_summary ?? [];
            $rowStats = $locked->row_stats ?? [];
            $duplicateSummary = $locked->duplicate_summary ?? [];

            $import = Import::query()->create([
                'center_id' => $locked->center_id,
                'import_verification_id' => $locked->id,
                'uploaded_by' => $user->id,
                'import_mode' => $locked->import_mode,
                'source_language' => (string) ($locked->source_language ?? 'fr'),
                'original_filename' => $locked->original_filename,
                'storage_path' => '',
                'file_hash' => $locked->file_hash,
                'file_size' => $locked->file_size,
                'encoding' => $locked->encoding,
                'delimiter' => $locked->delimiter,
                'actual_period_start' => $locked->actual_period_start,
                'actual_period_end' => $locked->actual_period_end,
                'declared_count' => (int) ($footer['count'] ?? 0),
                'parsed_count' => (int) ($rowStats['total_rows'] ?? 0),
                'invalid_count' => (int) ($rowStats['invalid'] ?? 0),
                'duplicate_within_file_count' => 0,
                'historical_duplicate_count' => 0,
                'new_master_count' => 0,
                'source_ht' => $this->moneyFromSummaryInt((int) ($footer['ht'] ?? 0)),
                'source_vat' => $this->moneyFromSummaryInt((int) ($footer['vat'] ?? 0)),
                'source_ttc' => $this->moneyFromSummaryInt((int) ($footer['ttc'] ?? 0)),
                'calculated_ht' => $this->moneyFromSummaryInt((int) ($footer['ht'] ?? 0)),
                'calculated_vat' => $this->moneyFromSummaryInt((int) ($footer['vat'] ?? 0)),
                'calculated_ttc' => $this->moneyFromSummaryInt((int) ($footer['ttc'] ?? 0)),
                'status' => ImportStatus::Processing,
                'warnings' => $this->buildWarnings($duplicateSummary),
                'processing_started_at' => now(),
            ]);

            $storagePath = $this->fileStorageService->promoteVerificationFile($locked, $import->id);

            $import->update([
                'storage_path' => $storagePath,
            ]);

            $locked->update([
                'status' => VerificationStatus::Imported,
                'import_id' => $import->id,
                'committed_at' => now(),
            ]);

            $this->auditLogger->record(
                event: 'import.created',
                user: $user,
                centerId: (int) $locked->center_id,
                resourceType: Import::class,
                resourceId: (int) $import->id,
                newValues: [
                    'token' => $locked->token,
                    'filename' => $locked->original_filename,
                    'import_id' => $import->id,
                    'import_mode' => $import->import_mode->value,
                    'queued' => true,
                ],
            );

            return $import->fresh();
        });

        $this->verificationCleanupService->deleteTempFile($verification->fresh());

        if ($import->import_verification_id !== $verification->id) {
            throw new ExactFileDuplicateException($import);
        }

        if ((bool) config('csv_imports.process_synchronously')) {
            $this->finalizeQueuedCommit($import->fresh(), $user);
        } else {
            ProcessImportJob::dispatch((int) $import->id, (int) $user->id);
        }

        return $import->fresh();
    }

    public function finalizeQueuedCommit(Import $import, User $user): Import
    {
        if ($import->status !== ImportStatus::Processing) {
            return $import;
        }

        $verification = ImportVerification::query()
            ->withoutCenterScope()
            ->findOrFail($import->import_verification_id);

        try {
            $absolutePath = $this->fileStorageService->absolutePath($import->storage_path);
            $rowCount = $this->importRowService->persistRows($import, $verification, $absolutePath);
            $ledgerResult = $this->masterLedgerService->processImport($import->fresh());
            $this->versionComparisonService->processImport($import->fresh());
            $applyResult = $this->revisionService->applyImportComparisons($import->fresh(), $user);
            $this->summaryGenerationService->queueRegenerationForImport($import->fresh());

            $duplicateSummary = $verification->duplicate_summary ?? [];

            $import->update([
                'parsed_count' => $rowCount,
                'duplicate_within_file_count' => $ledgerResult->withinFileDuplicates,
                'historical_duplicate_count' => $ledgerResult->historicalDuplicates,
                'new_master_count' => $ledgerResult->newMasters,
                'status' => $this->resolveImportStatus($duplicateSummary, $ledgerResult, $applyResult),
                'completed_at' => now(),
            ]);

            $this->correctionSubmissionService->recordSubmission($import->fresh(), $user);

            return $import->fresh();
        } catch (Throwable $exception) {
            $warnings = $import->warnings ?? [];
            $warnings[] = __('csv_import.commit.processing_failed');

            $import->forceFill([
                'status' => ImportStatus::Failed,
                'completed_at' => now(),
                'warnings' => array_values(array_unique($warnings)),
            ])->save();

            throw $exception;
        }
    }

    private function markExactFileDuplicate(
        User $user,
        ImportVerification $verification,
        Import $existingImport,
    ): Import {
        $verification->update([
            'status' => VerificationStatus::Imported,
            'import_id' => $existingImport->id,
            'committed_at' => now(),
        ]);

        $this->auditLogger->record(
            event: 'import.exact_file_duplicate',
            user: $user,
            centerId: (int) $verification->center_id,
            resourceType: Import::class,
            resourceId: (int) $existingImport->id,
            newValues: [
                'token' => $verification->token,
                'filename' => $verification->original_filename,
                'existing_import_id' => $existingImport->id,
            ],
        );

        return $existingImport;
    }

    /**
     * @param  array<string, mixed>  $duplicateSummary
     * @return list<string>
     */
    private function buildWarnings(array $duplicateSummary): array
    {
        $warnings = [];
        $probable = (int) ($duplicateSummary['probable'] ?? 0);

        if ($probable > 0) {
            $warnings[] = __('csv_import.warnings.probable_duplicates', ['count' => $probable]);
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $duplicateSummary
     */
    private function resolveImportStatus(
        array $duplicateSummary,
        MasterLedgerProcessResult $ledgerResult,
        ImportVersionApplyResult $applyResult,
    ): ImportStatus {
        if ($applyResult->proposedRevisions > 0) {
            return ImportStatus::AwaitingOwnerApproval;
        }

        $probable = (int) ($duplicateSummary['probable'] ?? 0);

        if ($ledgerResult->hasExactDuplicates()) {
            return ImportStatus::CompletedWithDuplicates;
        }

        if ($probable > 0) {
            return ImportStatus::CompletedWithWarnings;
        }

        return ImportStatus::Completed;
    }

    private function moneyFromSummaryInt(int $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function assertUserCanCommitForCenter(User $user, Center $center): void
    {
        if ($user->isOwner()) {
            $activeCenter = $this->activeCenterContext->resolve($user);

            if ($activeCenter === null || $activeCenter->centerId !== (int) $center->id) {
                throw new AuthorizationException(__('center.active_center_invalid'));
            }

            return;
        }

        if ($user->isCenterStaff()) {
            if ((int) $user->center_id !== (int) $center->id) {
                throw new AuthorizationException(__('center.cross_center_forbidden'));
            }

            if ((int) $center->organization_id !== (int) $user->organization_id) {
                throw new AuthorizationException(__('center.assigned_invalid'));
            }

            return;
        }

        throw new AuthorizationException(__('center.not_applicable'));
    }
}
