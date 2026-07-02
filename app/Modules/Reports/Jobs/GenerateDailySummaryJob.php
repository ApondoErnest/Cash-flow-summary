<?php

declare(strict_types=1);

namespace App\Modules\Reports\Jobs;

use App\Modules\Reports\Services\SummaryGenerationService;
use App\Support\Center\JobCenterContextService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDailySummaryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $centerId,
        public readonly string $businessDate,
    ) {}

    public function handle(
        SummaryGenerationService $summaryGenerationService,
        JobCenterContextService $jobCenterContextService,
    ): void {
        $jobCenterContextService->runForCenter($this->centerId, function () use ($summaryGenerationService): void {
            $summaryGenerationService->regenerate($this->centerId, $this->businessDate);
        });
    }
}
