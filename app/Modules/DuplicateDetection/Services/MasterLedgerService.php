<?php

declare(strict_types=1);

namespace App\Modules\DuplicateDetection\Services;

use App\Modules\CsvImports\Enums\CompletionStatus;
use App\Modules\CsvImports\Enums\DuplicateType;
use App\Modules\CsvImports\Enums\FinancialStatus;
use App\Modules\CsvImports\Enums\ImportRowStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportRow;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\DuplicateDetection\Support\ExactDuplicateKind;
use App\Modules\DuplicateDetection\Support\MasterLedgerProcessResult;
use Illuminate\Database\QueryException;

final class MasterLedgerService
{
    public function __construct(
        private readonly ExactDuplicateService $exactDuplicateService,
    ) {}

    public function processImport(Import $import): MasterLedgerProcessResult
    {
        $newMasters = 0;
        $withinFileDuplicates = 0;
        $historicalDuplicates = 0;

        /** @var array<string, ImportRow> $firstRowByHashInImport */
        $firstRowByHashInImport = [];

        $rows = ImportRow::query()
            ->withoutCenterScope()
            ->where('import_id', $import->id)
            ->orderBy('source_row_number')
            ->get();

        foreach ($rows as $row) {
            if ($row->row_status === ImportRowStatus::Invalid) {
                continue;
            }

            $match = $this->exactDuplicateService->matchForImportRow(
                row: $row,
                centerId: $import->center_id,
                firstRowByHashInImport: $firstRowByHashInImport,
            );

            if ($match->kind === ExactDuplicateKind::WithinFile) {
                $withinFileDuplicates++;
                $row->update([
                    'row_status' => ImportRowStatus::DuplicateWithinFile,
                    'duplicate_type' => DuplicateType::WithinFile,
                    'duplicate_of_import_row_id' => $match->withinFileSourceRow?->id,
                    'master_record_id' => $match->masterRecord?->id,
                ]);

                continue;
            }

            if ($match->kind === ExactDuplicateKind::Historical) {
                $historicalDuplicates++;
                $row->update([
                    'row_status' => ImportRowStatus::HistoricalDuplicate,
                    'duplicate_type' => DuplicateType::Historical,
                    'master_record_id' => $match->masterRecord?->id,
                ]);

                continue;
            }

            $master = $this->insertFromImportRow($row);
            $newMasters++;

            $row->update([
                'row_status' => ImportRowStatus::Accepted,
                'master_record_id' => $master->id,
            ]);

            $firstRowByHashInImport[$row->exact_canonical_hash] = $row->fresh();
        }

        return new MasterLedgerProcessResult(
            newMasters: $newMasters,
            withinFileDuplicates: $withinFileDuplicates,
            historicalDuplicates: $historicalDuplicates,
        );
    }

    public function insertFromImportRow(ImportRow $row): MasterCashFlowRecord
    {
        $attributes = $this->masterAttributesFromImportRow($row);

        $existing = MasterCashFlowRecord::query()
            ->withoutCenterScope()
            ->where('center_id', $attributes['center_id'])
            ->where('normalization_policy_version', $attributes['normalization_policy_version'])
            ->where('exact_canonical_hash', $attributes['exact_canonical_hash'])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return MasterCashFlowRecord::query()->create($attributes);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            return MasterCashFlowRecord::query()
                ->withoutCenterScope()
                ->where('center_id', $attributes['center_id'])
                ->where('normalization_policy_version', $attributes['normalization_policy_version'])
                ->where('exact_canonical_hash', $attributes['exact_canonical_hash'])
                ->firstOrFail();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function masterAttributesFromImportRow(ImportRow $row): array
    {
        $canonical = $row->canonical_values ?? [];
        $original = $row->original_values ?? [];
        $completionDate = $canonical['completion_date'] ?? null;
        $netAmount = (int) ($canonical['net_amount'] ?? 0);
        $vatAmount = (int) ($canonical['vat_amount'] ?? 0);
        $grossAmount = (int) ($canonical['gross_amount'] ?? 0);

        return [
            'center_id' => $row->center_id,
            'registration_date' => $canonical['registration_date'],
            'registration_time' => $canonical['registration_time'] ?? '00:00:00',
            'completion_date' => $completionDate,
            'customer_name' => (string) ($original['customer_name'] ?? ''),
            'customer_name_normalized' => (string) ($canonical['customer_name'] ?? ''),
            'category_code' => (string) ($canonical['category_code'] ?? ''),
            'inspection_type_code' => (string) ($canonical['inspection_type_code'] ?? ''),
            'licence_plate' => (string) ($original['licence_plate'] ?? ''),
            'licence_plate_normalized' => (string) ($canonical['licence_plate'] ?? ''),
            'net_amount' => $this->moneyFromInt($netAmount),
            'vat_amount' => $this->moneyFromInt($vatAmount),
            'gross_amount' => $this->moneyFromInt($grossAmount),
            'completion_status' => $completionDate === null
                ? CompletionStatus::Unfinished
                : CompletionStatus::Completed,
            'financial_status' => $netAmount === 0 && $vatAmount === 0 && $grossAmount === 0
                ? FinancialStatus::ZeroValue
                : FinancialStatus::Revenue,
            'exact_canonical_hash' => $row->exact_canonical_hash,
            'normalization_policy_version' => $row->normalization_policy_version,
            'first_import_id' => $row->import_id,
            'first_import_row_id' => $row->id,
            'first_seen_at' => now(),
        ];
    }

    private function moneyFromInt(int $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();

        if (in_array($code, ['23000', '23505'], true)) {
            return true;
        }

        return str_contains(strtolower($exception->getMessage()), 'unique constraint failed');
    }
}
