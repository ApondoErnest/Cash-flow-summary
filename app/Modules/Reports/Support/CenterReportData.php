<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

final readonly class CenterReportData
{
    /**
     * @param  list<string>  $missingSubmissionDates
     * @param  list<ReportDailyRow>  $dailyRows
     */
    public function __construct(
        public string $centerName,
        public string $periodLabel,
        public string $totalHt,
        public string $totalVat,
        public string $totalTtc,
        public int $recordCount,
        public int $daysWithData,
        public array $missingSubmissionDates,
        public array $dailyRows,
        public bool $hasData,
    ) {}
}
