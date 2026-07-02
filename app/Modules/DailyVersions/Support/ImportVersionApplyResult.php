<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Support;

final class ImportVersionApplyResult
{
    public function __construct(
        public readonly int $activatedDays,
        public readonly int $proposedRevisions,
    ) {}
}
