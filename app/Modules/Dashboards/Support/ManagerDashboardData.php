<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Support;

final readonly class ManagerDashboardData
{
    /**
     * @param  list<OwnerDashboardTrendPoint>  $trend
     * @param  list<OwnerDashboardAlert>  $alerts
     * @param  list<OwnerDashboardImportRow>  $recentImports
     * @param  list<string>  $missingSubmissionDates
     */
    public function __construct(
        public string $centerName,
        public string $todayTtc,
        public string $yesterdayTtc,
        public string $weekTtc,
        public string $monthTtc,
        public string $yearTtc,
        public int $activeRecordsToday,
        public array $trend,
        public float $trendMaxTtc,
        public array $alerts,
        public array $missingSubmissionDates,
        public array $recentImports,
        public ?string $lastImportAt,
        public bool $hasData,
    ) {}
}
