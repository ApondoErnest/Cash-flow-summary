<?php

declare(strict_types=1);

namespace App\Modules\DuplicateDetection\Support;

final class MasterLedgerProcessResult
{
    public function __construct(
        public readonly int $newMasters,
        public readonly int $withinFileDuplicates,
        public readonly int $historicalDuplicates,
    ) {}

    public function hasExactDuplicates(): bool
    {
        return $this->withinFileDuplicates > 0 || $this->historicalDuplicates > 0;
    }
}
