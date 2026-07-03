<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Services\ImportErrorReportService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ImportErrorDownloadController extends Controller
{
    public function __invoke(Import $import, ImportErrorReportService $reportService): Response
    {
        Gate::authorize('download', $import);

        abort_unless($reportService->importHasErrors($import), 404);

        return response($reportService->generateCsvForImport($import), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$reportService->filenameForImport($import).'"',
        ]);
    }
}
