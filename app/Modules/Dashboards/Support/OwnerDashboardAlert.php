<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Support;

final readonly class OwnerDashboardAlert
{
    public function __construct(
        public string $type,
        public string $message,
        public ?string $href = null,
    ) {}
}
