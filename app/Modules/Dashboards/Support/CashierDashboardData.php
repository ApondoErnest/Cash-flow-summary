<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Support;

final readonly class CashierDashboardData
{
    /**
     * @param  list<string>  $missingSubmissionDates
     * @param  list<OwnerDashboardImportRow>  $recentImports
     */
    public function __construct(
        public string $centerName,
        public string $referenceDate,
        public string $todayTtc,
        public string $yesterdayTtc,
        public int $activeRecordsToday,
        public array $missingSubmissionDates,
        public array $recentImports,
        public bool $hasData,
    ) {}
}
