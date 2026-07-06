<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Support;

final readonly class OwnerDashboardData
{
    /**
     * @param  list<OwnerDashboardTrendPoint>  $trend
     * @param  list<OwnerDashboardAlert>  $alerts
     * @param  list<OwnerDashboardImportRow>  $recentImports
     */
    public function __construct(
        public string $centerName,
        public string $periodLabel,
        public string $totalTtc,
        public string $totalHt,
        public string $totalVat,
        public int $uniqueRecords,
        public int $completedCount,
        public int $unfinishedCount,
        public int $zeroValueCount,
        public int $duplicatesIgnored,
        /** @var list<OwnerDashboardCategoryCount> */
        public array $categoryCounts,
        public int $cvInspectionCount,
        public array $trend,
        public float $trendMaxTtc,
        public array $alerts,
        public array $recentImports,
        public ?string $lastImportAt,
        public bool $hasData,
    ) {}
}
