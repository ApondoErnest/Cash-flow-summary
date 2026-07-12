<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Services;

use App\Modules\CsvImports\Enums\DayComparisonResult;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportDayComparison;
use App\Modules\CsvImports\Support\ImportDayComparisonRow;
use App\Modules\CsvImports\Support\ImportDetailData;
use App\Modules\Dashboards\Support\DashboardMoney;

final class ImportDetailService
{
    public function __construct(
        private readonly ImportResultService $importResultService,
    ) {}

    public function build(Import $import): ImportDetailData
    {
        $import->loadMissing([
            'uploadedBy:id,name',
            'dayComparisons' => static fn ($query) => $query->orderBy('business_date'),
        ]);
        $import->loadCount('errors');

        $result = $this->importResultService->build($import);

        return new ImportDetailData(
            result: $result,
            uploadedByName: $import->uploadedBy?->name ?? '—',
            completedAt: $import->completed_at !== null
                ? \App\Support\Locale\LocalizedDateTime::dateTime($import->completed_at)
                : null,
            fileSizeLabel: $this->formatBytes((int) $import->file_size),
            errorCount: (int) $import->errors_count,
            dayComparisons: $import->dayComparisons
                ->map(fn (ImportDayComparison $comparison): ImportDayComparisonRow => $this->toComparisonRow($comparison))
                ->all(),
        );
    }

    private function toComparisonRow(ImportDayComparison $comparison): ImportDayComparisonRow
    {
        [$label, $variant] = $this->comparisonPresentation($comparison->comparison_result);

        return new ImportDayComparisonRow(
            businessDate: $comparison->business_date->format('d/m/Y'),
            resultLabel: $label,
            resultVariant: $variant,
            existingTtc: DashboardMoney::format($comparison->existing_ttc ?? 0),
            proposedTtc: DashboardMoney::format($comparison->proposed_ttc ?? 0),
            recordCountDelta: (int) $comparison->record_count_delta,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function comparisonPresentation(DayComparisonResult $result): array
    {
        return match ($result) {
            DayComparisonResult::New => [
                __('csv_import.detail.comparison.new'),
                'success',
            ],
            DayComparisonResult::Unchanged => [
                __('csv_import.detail.comparison.unchanged'),
                'neutral',
            ],
            DayComparisonResult::RevisionRequired => [
                __('csv_import.detail.comparison.revision_required'),
                'warning',
            ],
            DayComparisonResult::CoveredWithoutRows => [
                __('csv_import.detail.comparison.covered_without_rows'),
                'info',
            ],
            DayComparisonResult::Invalid => [
                __('csv_import.detail.comparison.invalid'),
                'error',
            ],
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
