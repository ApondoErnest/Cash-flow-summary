<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Services;

use App\Modules\CsvImports\Enums\CompletionStatus;
use App\Modules\CsvImports\Enums\FinancialStatus;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\CsvImports\Support\RecordDetailData;
use App\Modules\CsvImports\Support\RecordExplorerRow;
use App\Modules\CsvImports\Support\RecordStatusPresenter;
use App\Modules\Dashboards\Support\DashboardMoney;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class RecordExplorerService
{
    /**
     * @param  array{
     *     search?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     completion?: string|null,
     *     financial?: string|null,
     * }  $filters
     * @return LengthAwarePaginator<int, MasterCashFlowRecord>
     */
    public function paginateForActiveCenter(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = MasterCashFlowRecord::query()
            ->latest('registration_date')
            ->latest('registration_time')
            ->latest('id');

        if (($filters['search'] ?? '') !== '') {
            $search = (string) $filters['search'];
            $normalized = mb_strtolower(trim($search));
            $like = '%'.$normalized.'%';

            $query->where(function ($builder) use ($like, $search): void {
                $builder
                    ->where('customer_name_normalized', 'like', $like)
                    ->orWhere('licence_plate_normalized', 'like', $like)
                    ->orWhere('customer_name', 'like', '%'.$search.'%')
                    ->orWhere('licence_plate', 'like', '%'.$search.'%');
            });
        }

        if (($filters['from'] ?? '') !== '') {
            $query->whereDate('registration_date', '>=', $filters['from']);
        }

        if (($filters['to'] ?? '') !== '') {
            $query->whereDate('registration_date', '<=', $filters['to']);
        }

        if (($filters['completion'] ?? '') !== '') {
            $query->where('completion_status', $filters['completion']);
        }

        if (($filters['financial'] ?? '') !== '') {
            $query->where('financial_status', $filters['financial']);
        }

        return $query->paginate($perPage);
    }

    public function toRow(MasterCashFlowRecord $record): RecordExplorerRow
    {
        $completion = RecordStatusPresenter::completion($record->completion_status);
        $financial = RecordStatusPresenter::financial($record->financial_status);

        return new RecordExplorerRow(
            id: $record->id,
            registrationDate: $record->registration_date->format('d/m/Y'),
            registrationTime: $this->formatTime((string) $record->registration_time),
            customerName: $record->customer_name,
            licencePlate: $record->licence_plate,
            categoryCode: $record->category_code,
            inspectionTypeCode: $record->inspection_type_code,
            netAmount: DashboardMoney::format($record->net_amount),
            vatAmount: DashboardMoney::format($record->vat_amount),
            grossAmount: DashboardMoney::format($record->gross_amount),
            completionStatusLabel: $completion['label'],
            completionStatusVariant: $completion['variant'],
            financialStatusLabel: $financial['label'],
            financialStatusVariant: $financial['variant'],
        );
    }

    public function toDetail(MasterCashFlowRecord $record): RecordDetailData
    {
        $record->loadMissing('firstImport:id,original_filename');

        $completion = RecordStatusPresenter::completion($record->completion_status);
        $financial = RecordStatusPresenter::financial($record->financial_status);

        return new RecordDetailData(
            id: $record->id,
            registrationDate: $record->registration_date->format('d/m/Y'),
            registrationTime: $this->formatTime((string) $record->registration_time),
            completionDate: $record->completion_date?->format('d/m/Y'),
            customerName: $record->customer_name,
            licencePlate: $record->licence_plate,
            categoryCode: $record->category_code,
            inspectionTypeCode: $record->inspection_type_code,
            netAmount: DashboardMoney::format($record->net_amount),
            vatAmount: DashboardMoney::format($record->vat_amount),
            grossAmount: DashboardMoney::format($record->gross_amount),
            completionStatusLabel: $completion['label'],
            completionStatusVariant: $completion['variant'],
            financialStatusLabel: $financial['label'],
            financialStatusVariant: $financial['variant'],
            firstSeenAt: $record->first_seen_at !== null
                ? \App\Support\Locale\LocalizedDateTime::dateTime($record->first_seen_at)
                : null,
            firstImportId: $record->first_import_id,
            firstImportFilename: $record->firstImport?->original_filename,
            normalizationPolicyVersion: $record->normalization_policy_version,
        );
    }

    /**
     * @return list<CompletionStatus>
     */
    public function completionFilterOptions(): array
    {
        return CompletionStatus::cases();
    }

    /**
     * @return list<FinancialStatus>
     */
    public function financialFilterOptions(): array
    {
        return FinancialStatus::cases();
    }

    private function formatTime(string $time): string
    {
        return Carbon::createFromFormat('H:i:s', strlen($time) === 5 ? $time.':00' : $time)
            ->format('H:i');
    }
}
