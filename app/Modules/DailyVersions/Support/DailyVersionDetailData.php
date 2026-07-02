<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Support;

final readonly class DailyVersionDetailData
{
    public function __construct(
        public int $id,
        public string $businessDate,
        public int $versionNumber,
        public string $statusLabel,
        public string $statusVariant,
        public int $recordCount,
        public string $totalHt,
        public string $totalVat,
        public string $totalTtc,
        public bool $isActiveSnapshot,
        public ?string $submittedByName,
        public ?string $approvedByName,
        public ?string $approvedAt,
        public ?string $rejectedReason,
        public ?int $importId,
        public ?string $importFilename,
        public ?int $previousVersionNumber,
    ) {}
}
