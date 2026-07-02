<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Support;

final class VersionComparisonProcessResult
{
    public function __construct(
        public readonly int $newDays,
        public readonly int $unchangedDays,
        public readonly int $revisionRequiredDays,
        public readonly int $coveredWithoutRowsDays,
    ) {}

    public function hasRevisionRequired(): bool
    {
        return $this->revisionRequiredDays > 0;
    }
}
