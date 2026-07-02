<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Services;

use App\Modules\CsvImports\Enums\DayComparisonResult;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Support\ImportDayComparisonRow;
use App\Modules\CsvImports\Support\ImportDetailData;
use App\Modules\CsvImports\Support\ImportListRow;
use App\Modules\CsvImports\Support\ImportStatusPresenter;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\Dashboards\Support\DashboardMoney;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class ImportListService
{
    /**
     * @param  array{search?: string|null, status?: string|null, from?: string|null, to?: string|null}  $filters
     * @return LengthAwarePaginator<int, Import>
     */
    public function paginateForActiveCenter(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Import::query()
            ->with(['uploadedBy:id,name'])
            ->latest('created_at');

        if (($filters['search'] ?? '') !== '') {
            $query->where('original_filename', 'like', '%'.$filters['search'].'%');
        }

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        if (($filters['from'] ?? '') !== '') {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (($filters['to'] ?? '') !== '') {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->paginate($perPage);
    }

    public function toListRow(Import $import): ImportListRow
    {
        $import->loadMissing('uploadedBy:id,name');

        $badge = ImportStatusPresenter::badge($import->status);

        return new ImportListRow(
            id: $import->id,
            importedAt: ($import->completed_at ?? $import->created_at)
                ->timezone(config('app.timezone'))
                ->format('Y-m-d H:i'),
            filename: $import->original_filename,
            importModeLabel: $this->importModeLabel($import->import_mode),
            actualPeriod: $this->formatActualPeriod($import),
            totalTtc: DashboardMoney::format($import->calculated_ttc ?? $import->source_ttc ?? 0),
            statusLabel: $badge['label'],
            statusVariant: $badge['variant'],
            uploadedByName: $import->uploadedBy?->name ?? '—',
        );
    }

    /**
     * @return list<ImportStatus>
     */
    public function filterableStatuses(): array
    {
        return [
            ImportStatus::Processing,
            ImportStatus::Completed,
            ImportStatus::CompletedWithDuplicates,
            ImportStatus::CompletedWithWarnings,
            ImportStatus::AwaitingOwnerApproval,
            ImportStatus::ExactFileDuplicate,
            ImportStatus::Failed,
            ImportStatus::Cancelled,
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
