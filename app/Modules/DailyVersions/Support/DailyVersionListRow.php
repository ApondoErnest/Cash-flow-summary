<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Support;

final readonly class DailyVersionListRow
{
    public function __construct(
        public int $id,
        public string $businessDate,
        public int $versionNumber,
        public string $statusLabel,
        public string $statusVariant,
        public int $recordCount,
        public string $totalTtc,
        public bool $isActiveSnapshot,
        public ?string $submittedByName,
        public ?string $approvedByName,
    ) {}
}
