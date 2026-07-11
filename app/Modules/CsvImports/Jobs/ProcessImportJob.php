<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Jobs;

use App\Models\User;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Services\ImportService;
use App\Support\Center\JobCenterContextService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $timeout;

    public function __construct(
        public readonly int $importId,
        public readonly int $userId,
    ) {
        $this->tries = max(1, (int) config('csv_imports.job_tries', 1));
        $this->timeout = max(60, (int) config('csv_imports.job_timeout_seconds', 600));
    }

    public function handle(
        ImportService $importService,
        JobCenterContextService $jobCenterContextService,
    ): void {
        $import = Import::query()->withoutCenterScope()->find($this->importId);

        if ($import === null || $import->status !== ImportStatus::Processing) {
            return;
        }

        $user = User::query()->find($this->userId);

        if ($user === null) {
            $import->forceFill([
                'status' => ImportStatus::Failed,
                'completed_at' => now(),
            ])->save();

            return;
        }

        $jobCenterContextService->runForCenter(
            (int) $import->center_id,
            fn () => $importService->finalizeQueuedCommit($import, $user),
        );
    }

    public function failed(?Throwable $exception): void
    {
        $import = Import::query()->withoutCenterScope()->find($this->importId);

        if ($import === null || $import->status !== ImportStatus::Processing) {
            return;
        }

        $warnings = $import->warnings ?? [];
        $warnings[] = __('csv_import.commit.processing_failed');

        $import->forceFill([
            'status' => ImportStatus::Failed,
            'completed_at' => now(),
            'warnings' => array_values(array_unique($warnings)),
        ])->save();
    }
}
