<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Jobs;

use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\AuditLogging\Services\AuditLogger;
use App\Support\Center\JobCenterContextService;
use App\Modules\CsvVerification\Services\CsvInspectionService;
use App\Modules\CsvVerification\Services\CsvParsingService;
use App\Modules\CsvVerification\Services\DuplicatePreviewService;
use App\Modules\CsvImports\Services\ImportErrorRecorderService;
use App\Modules\CsvVerification\Services\FooterReaderService;
use App\Modules\CsvVerification\Services\HeaderMappingService;
use App\Modules\CsvVerification\Services\ReconciliationService;
use App\Modules\Normalization\NormalizationPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessVerificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly int $centerId,
    ) {}

    public function handle(
        CsvInspectionService $inspectionService,
        HeaderMappingService $headerMappingService,
        CsvParsingService $csvParsingService,
        FooterReaderService $footerReaderService,
        ReconciliationService $reconciliationService,
        DuplicatePreviewService $duplicatePreviewService,
        ImportErrorRecorderService $importErrorRecorderService,
        JobCenterContextService $jobCenterContextService,
        AuditLogger $auditLogger,
    ): void {
        try {
            $jobCenterContextService->runForCenter($this->centerId, function () use (
                $inspectionService,
                $headerMappingService,
                $csvParsingService,
                $footerReaderService,
                $reconciliationService,
                $duplicatePreviewService,
                $importErrorRecorderService,
                $auditLogger,
            ): void {
                $this->process(
                    $inspectionService,
                    $headerMappingService,
                    $csvParsingService,
                    $footerReaderService,
                    $reconciliationService,
                    $duplicatePreviewService,
                    $importErrorRecorderService,
                    $auditLogger,
                );
            });
        } catch (Throwable $exception) {
            $this->markVerificationFailed($auditLogger, $exception);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markVerificationFailed(app(AuditLogger::class), $exception);
    }

    private function markVerificationFailed(AuditLogger $auditLogger, ?Throwable $exception): void
    {
        $verification = ImportVerification::query()
            ->where('token', $this->token)
            ->where('center_id', $this->centerId)
            ->first();

        if ($verification === null) {
            return;
        }

        if (! in_array($verification->status, [VerificationStatus::Pending, VerificationStatus::Processing], true)) {
            return;
        }

        $errorMessage = filled($exception?->getMessage())
            ? $exception->getMessage()
            : (string) __('csv_verification.verification.processing_failed');

        $verification->update([
            'status' => VerificationStatus::Failed,
            'error_message' => $errorMessage,
        ]);

        $this->recordVerificationFailure($auditLogger, $verification, $errorMessage);
    }

    private function process(
        CsvInspectionService $inspectionService,
        HeaderMappingService $headerMappingService,
        CsvParsingService $csvParsingService,
        FooterReaderService $footerReaderService,
        ReconciliationService $reconciliationService,
        DuplicatePreviewService $duplicatePreviewService,
        ImportErrorRecorderService $importErrorRecorderService,
        AuditLogger $auditLogger,
    ): void {
        $verification = ImportVerification::query()
            ->where('token', $this->token)
            ->where('center_id', $this->centerId)
            ->first();

        if ($verification === null) {
            return;
        }

        if (! in_array($verification->status, [VerificationStatus::Pending, VerificationStatus::Processing], true)) {
            return;
        }

        $verification->update([
            'status' => VerificationStatus::Processing,
        ]);

        $inspection = $inspectionService->inspectVerification($verification);

        if (! $inspection->isValid()) {
            $verification->update([
                'status' => VerificationStatus::Failed,
                'error_message' => implode(' ', $inspection->errors),
                'validation_result' => $inspection->toValidationPayload(),
            ]);

            $this->recordVerificationFailure($auditLogger, $verification, implode(' ', $inspection->errors));

            return;
        }

        $mapping = $headerMappingService->map($inspection);

        $validationResult = array_merge(
            $inspection->toValidationPayload(),
            $mapping->toValidationPayload(),
        );

        if (! $mapping->isValid()) {
            $verification->update([
                'status' => VerificationStatus::Failed,
                'error_message' => implode(' ', $mapping->errors),
                'encoding' => $inspection->encoding,
                'delimiter' => $inspection->delimiter,
                'validation_result' => $validationResult,
            ]);

            $this->recordVerificationFailure($auditLogger, $verification, implode(' ', $mapping->errors));

            return;
        }

        $parseResult = $csvParsingService->parseVerification($verification, $mapping);

        $importErrorRecorderService->clearForVerification($verification->id);

        foreach ($parseResult->invalidRows as $invalidRow) {
            $importErrorRecorderService->recordFromParsedRow(
                $invalidRow,
                importVerificationId: $verification->id,
            );
        }

        $validationResult = array_merge(
            $validationResult,
            $parseResult->toValidationPayload(),
        );

        $disk = (string) config('csv_verification.temp_disk', 'local');
        $filePath = Storage::disk($disk)->path($verification->temp_storage_path);

        $footerResult = $footerReaderService->readFile(
            $filePath,
            $inspection->delimiter,
            $mapping->mapping,
        );

        $validationResult = array_merge($validationResult, $footerResult->toValidationPayload());

        if (! $footerResult->isValid()) {
            $verification->update([
                'encoding' => $inspection->encoding,
                'delimiter' => $inspection->delimiter,
                'source_language' => $mapping->language,
                'row_stats' => $parseResult->summary->toRowStats(),
                'validation_result' => $validationResult,
                'status' => VerificationStatus::Failed,
                'error_message' => implode(' ', $footerResult->errors),
            ]);

            $this->recordVerificationFailure($auditLogger, $verification, implode(' ', $footerResult->errors));

            return;
        }

        $reconciliationResult = $reconciliationService->reconcile(
            $filePath,
            $inspection->delimiter,
            $mapping->mapping,
            $footerResult->summary,
        );

        $validationResult = array_merge($validationResult, $reconciliationResult->toValidationPayload());

        if (! $reconciliationResult->isValid()) {
            $verification->update([
                'encoding' => $inspection->encoding,
                'delimiter' => $inspection->delimiter,
                'source_language' => $mapping->language,
                'row_stats' => $parseResult->summary->toRowStats(),
                'footer_summary' => $footerResult->summary->toArray(),
                'validation_result' => $validationResult,
                'status' => VerificationStatus::Failed,
                'error_message' => implode(' ', $reconciliationResult->errors),
            ]);

            $this->recordVerificationFailure($auditLogger, $verification, implode(' ', $reconciliationResult->errors));

            return;
        }

        $duplicatePreview = $duplicatePreviewService->previewVerification(
            $verification,
            $mapping,
            $filePath,
        );

        $validationResult = array_merge($validationResult, [
            'normalization' => [
                'policy' => NormalizationPolicy::VERSION,
                'normalized_rows' => $duplicatePreview->normalizedRows,
            ],
        ], $duplicatePreview->toValidationPayload());

        $actualPeriod = $parseResult->summary->toActualPeriod();

        $verification->update([
            'encoding' => $inspection->encoding,
            'delimiter' => $inspection->delimiter,
            'source_language' => $mapping->language,
            'actual_period_start' => $actualPeriod['start'] ?? null,
            'actual_period_end' => $actualPeriod['end'] ?? null,
            'row_stats' => $parseResult->summary->toRowStats(),
            'footer_summary' => $footerResult->summary->toArray(),
            'duplicate_summary' => $duplicatePreview->toSummary(),
            'validation_result' => $validationResult,
            'status' => VerificationStatus::Ready,
            'verified_at' => now(),
        ]);
    }

    private function recordVerificationFailure(
        AuditLogger $auditLogger,
        ImportVerification $verification,
        string $errorMessage,
    ): void {
        $auditLogger->record(
            event: 'verification.failed',
            user: $verification->user,
            centerId: (int) $verification->center_id,
            resourceType: ImportVerification::class,
            resourceId: (int) $verification->id,
            newValues: [
                'token' => $verification->token,
                'filename' => $verification->original_filename,
            ],
            reason: $errorMessage,
        );
    }
}
