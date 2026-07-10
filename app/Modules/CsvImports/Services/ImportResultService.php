<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Services;

use App\Models\User;
use App\Modules\CsvImports\Enums\DayComparisonResult;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Support\ImportResultData;
use App\Modules\CsvImports\Support\ImportStatusPresenter;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\Dashboards\Support\DashboardMoney;
use Illuminate\Support\Carbon;

final class ImportResultService
{
    public function build(Import $import, ?User $viewer = null): ImportResultData
    {
        $import->loadMissing(['center', 'dayComparisons', 'importVerification']);

        $dayComparisons = $import->dayComparisons;
        $activeDays = $dayComparisons
            ->where('comparison_result', DayComparisonResult::New)
            ->count();
        $unchangedDays = $dayComparisons
            ->where('comparison_result', DayComparisonResult::Unchanged)
            ->count();
        $revisionsPending = $dayComparisons
            ->where('comparison_result', DayComparisonResult::RevisionRequired)
            ->count();

        [$headline, $statusBadge, $statusVariant] = $this->resolveStatusPresentation(
            $import,
            $viewer,
        );
        [$whatsappStatus, $whatsappVariant] = $this->resolveWhatsappPresentation($import);

        return new ImportResultData(
            importId: $import->id,
            headline: $headline,
            statusBadge: $statusBadge,
            statusVariant: $statusVariant,
            filename: $import->original_filename,
            centerName: $import->center->name,
            importModeLabel: $this->importModeLabel($import->import_mode),
            sourceLanguage: strtoupper((string) $import->source_language),
            actualPeriod: $this->formatActualPeriod($import),
            sourceRows: $import->parsed_count,
            newUnique: $import->new_master_count,
            duplicatesIgnored: $import->duplicate_within_file_count + $import->historical_duplicate_count,
            invalidRows: $import->invalid_count,
            activeDays: $activeDays,
            unchangedDays: $unchangedDays,
            revisionsPending: $revisionsPending,
            footerHt: DashboardMoney::format($import->source_ht),
            footerVat: DashboardMoney::format($import->source_vat),
            footerTtc: DashboardMoney::format($import->source_ttc),
            whatsappStatus: $whatsappStatus,
            whatsappVariant: $whatsappVariant,
            warnings: $import->warnings ?? [],
            isExactFileDuplicate: $import->status === ImportStatus::ExactFileDuplicate,
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveStatusPresentation(Import $import, ?User $viewer): array
    {
        $badge = ImportStatusPresenter::badge($import->status);

        $headline = match (true) {
            $import->import_mode === ImportMode::Correction
                && $viewer?->isCenterStaff() === true
                && $import->status === ImportStatus::AwaitingOwnerApproval
                => __('csv_import.result.headline.correction_submitted'),
            default => ImportStatusPresenter::headline($import->status),
        };

        return [
            $headline,
            $badge['label'],
            $badge['variant'],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveWhatsappPresentation(Import $import): array
    {
        if ($import->status === ImportStatus::ExactFileDuplicate) {
            return [
                __('csv_import.result.whatsapp.not_applicable'),
                'neutral',
            ];
        }

        if (in_array($import->status, [
            ImportStatus::Completed,
            ImportStatus::CompletedWithDuplicates,
            ImportStatus::CompletedWithWarnings,
            ImportStatus::AwaitingOwnerApproval,
        ], true)) {
            return [
                __('csv_import.result.whatsapp.scheduled_summary'),
                'info',
            ];
        }

        return [
            __('csv_import.result.whatsapp.not_applicable'),
            'neutral',
        ];
    }

    private function importModeLabel(ImportMode $mode): string
    {
        return match ($mode) {
            ImportMode::Operational => __('csv_verification.import_mode.operational'),
            ImportMode::Historical => __('csv_verification.import_mode.historical'),
            ImportMode::Correction => __('csv_verification.import_mode.correction'),
        };
    }

    private function formatActualPeriod(Import $import): ?string
    {
        if ($import->actual_period_start === null || $import->actual_period_end === null) {
            return null;
        }

        $start = Carbon::parse($import->actual_period_start)->format('d/m/Y');
        $end = Carbon::parse($import->actual_period_end)->format('d/m/Y');

        return $start === $end ? $start : "{$start} – {$end}";
    }
}
