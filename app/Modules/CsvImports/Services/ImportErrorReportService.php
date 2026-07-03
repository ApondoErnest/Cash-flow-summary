<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Services;

use App\Modules\CsvImports\Enums\ImportRowStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportError;
use App\Modules\CsvImports\Models\ImportRow;
use App\Modules\CsvVerification\Models\ImportVerification;
use Illuminate\Support\Collection;

final class ImportErrorReportService
{
    public function __construct(
        private readonly ImportErrorRecorderService $errorRecorder,
    ) {}

    public function importHasErrors(Import $import): bool
    {
        if ((int) $import->invalid_count > 0) {
            return true;
        }

        return ImportError::query()->where('import_id', $import->id)->exists();
    }

    public function verificationHasErrors(ImportVerification $verification): bool
    {
        if (((int) ($verification->row_stats['invalid'] ?? 0)) > 0) {
            return true;
        }

        return ImportError::query()
            ->where('import_verification_id', $verification->id)
            ->exists();
    }

    public function filenameForImport(Import $import): string
    {
        $base = pathinfo($import->original_filename, PATHINFO_FILENAME);

        return sprintf('%s-errors.csv', $base);
    }

    public function filenameForVerification(ImportVerification $verification): string
    {
        $base = pathinfo($verification->original_filename, PATHINFO_FILENAME);

        return sprintf('%s-errors.csv', $base);
    }

    public function generateCsvForImport(Import $import): string
    {
        $this->ensureImportErrors($import);

        $errors = ImportError::query()
            ->where('import_id', $import->id)
            ->orderBy('source_row_number')
            ->orderBy('id')
            ->get();

        return $this->buildCsv($errors);
    }

    public function generateCsvForVerification(ImportVerification $verification): string
    {
        $errors = ImportError::query()
            ->where('import_verification_id', $verification->id)
            ->orderBy('source_row_number')
            ->orderBy('id')
            ->get();

        return $this->buildCsv($errors);
    }

    private function ensureImportErrors(Import $import): void
    {
        if (ImportError::query()->where('import_id', $import->id)->exists()) {
            return;
        }

        ImportRow::query()
            ->where('import_id', $import->id)
            ->where('row_status', ImportRowStatus::Invalid)
            ->orderBy('source_row_number')
            ->each(fn (ImportRow $row): null => $this->errorRecorder->recordFromImportRow($row));
    }

    /**
     * @param  Collection<int, ImportError>  $errors
     */
    private function buildCsv(Collection $errors): string
    {
        $handle = fopen('php://temp', 'rb+');

        if ($handle === false) {
            throw new \RuntimeException('Unable to create error report stream.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            __('csv_import.errors.report.columns.row_number'),
            __('csv_import.errors.report.columns.field'),
            __('csv_import.errors.report.columns.error_code'),
            __('csv_import.errors.report.columns.message'),
            __('csv_import.errors.report.columns.original_value'),
            __('csv_import.errors.report.columns.raw_row'),
        ], ';');

        foreach ($errors as $error) {
            fputcsv($handle, [
                $error->source_row_number,
                $error->field,
                $error->error_code,
                $error->message,
                $error->original_value,
                $error->raw_row,
            ], ';');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
