<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Support;

final readonly class OwnerDashboardTrendPoint
{
    public function __construct(
        public string $label,
        public string $totalTtc,
        public float $totalTtcNumeric,
    ) {}
}
