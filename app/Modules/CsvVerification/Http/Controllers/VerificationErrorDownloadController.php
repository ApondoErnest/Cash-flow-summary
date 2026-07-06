<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CsvImports\Services\ImportErrorReportService;
use App\Modules\CsvVerification\Models\ImportVerification;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class VerificationErrorDownloadController extends Controller
{
    public function __invoke(string $token, ImportErrorReportService $reportService): Response
    {
        $verification = ImportVerification::query()
            ->withoutCenterScope()
            ->where('token', $token)
            ->firstOrFail();

        Gate::authorize('download', $verification);

        abort_unless($reportService->verificationHasErrors($verification), 404);

        return response($reportService->generateCsvForVerification($verification), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$reportService->filenameForVerification($verification).'"',
        ]);
    }
}
