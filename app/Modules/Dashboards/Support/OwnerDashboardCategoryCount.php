<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Support;

final readonly class OwnerDashboardCategoryCount
{
    public function __construct(
        public string $code,
        public int $count,
    ) {}
}
