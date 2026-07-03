<?php

declare(strict_types=1);

namespace App\Modules\Reports\Jobs;

use App\Modules\Reports\Models\ExportRequest;
use App\Modules\Reports\Services\ExportService;
use App\Support\Center\JobCenterContextService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateExportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $exportRequestId,
    ) {}

    public function handle(
        ExportService $exportService,
        JobCenterContextService $jobCenterContextService,
    ): void {
        $export = ExportRequest::query()->find($this->exportRequestId);

        if ($export === null || $export->center_id === null) {
            return;
        }

        $jobCenterContextService->runForCenter($export->center_id, function () use ($exportService, $export): void {
            $exportService->generate($export->fresh());
        });
    }
}
