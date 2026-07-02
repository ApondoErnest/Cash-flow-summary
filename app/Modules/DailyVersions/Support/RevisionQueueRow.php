<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Support;

final readonly class RevisionQueueRow
{
    public function __construct(
        public int $id,
        public string $businessDate,
        public int $versionNumber,
        public string $submittedByName,
        public string $existingHt,
        public string $existingVat,
        public string $existingTtc,
        public string $proposedHt,
        public string $proposedVat,
        public string $proposedTtc,
        public ?int $importId,
        public ?string $importFilename,
    ) {}
}
