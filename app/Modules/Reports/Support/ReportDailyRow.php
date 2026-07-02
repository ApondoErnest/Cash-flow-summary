<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

final readonly class ReportDailyRow
{
    public function __construct(
        public string $businessDate,
        public string $businessDateIso,
        public int $recordCount,
        public string $totalHt,
        public string $totalVat,
        public string $totalTtc,
    ) {}
}
