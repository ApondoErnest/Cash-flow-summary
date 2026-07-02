<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Support;

final readonly class ImportDayComparisonRow
{
    public function __construct(
        public string $businessDate,
        public string $resultLabel,
        public string $resultVariant,
        public string $existingTtc,
        public string $proposedTtc,
        public int $recordCountDelta,
    ) {}
}
