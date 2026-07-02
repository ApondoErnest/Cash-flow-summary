<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Support;

final class DailyDataset
{
    /**
     * @param  list<int>  $masterRecordIds
     */
    public function __construct(
        public readonly int $centerId,
        public readonly string $businessDate,
        public readonly array $masterRecordIds,
        public readonly string $datasetHash,
        public readonly int $recordCount,
        public readonly string $totalHt,
        public readonly string $totalVat,
        public readonly string $totalTtc,
    ) {}

    public function isEmpty(): bool
    {
        return $this->recordCount === 0;
    }
}
