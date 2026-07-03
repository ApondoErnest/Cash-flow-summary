<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CsvImports\Services\ImportErrorReportService;
use App\Modules\CsvVerification\Models\ImportVerification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Response;

class VerificationErrorDownloadController extends Controller
{
    public function __invoke(string $token, ImportErrorReportService $reportService): Response
    {
        $verification = ImportVerification::query()
            ->where('token', $token)
            ->firstOrFail();

        $user = auth()->user();

        if ($user === null || (int) $verification->user_id !== (int) $user->id) {
            throw new AuthorizationException(__('center.cross_center_forbidden'));
        }

        abort_unless($reportService->verificationHasErrors($verification), 404);

        return response($reportService->generateCsvForVerification($verification), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$reportService->filenameForVerification($verification).'"',
        ]);
    }
}
