<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

final readonly class ExportCleanupResult
{
    public function __construct(
        public int $expired,
        public int $filesDeleted,
    ) {}
}
