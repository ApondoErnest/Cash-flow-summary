<?php

declare(strict_types=1);

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Models\ExportRequest;
use App\Modules\Reports\Services\ExportService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ExportDownloadController extends Controller
{
    public function __invoke(ExportRequest $exportRequest, ExportService $exportService): Response
    {
        Gate::authorize('download', $exportRequest);

        return $exportService->downloadResponse($exportRequest);
    }
}
