<?php

declare(strict_types=1);

namespace App\Modules\DuplicateDetection\Support;

use App\Modules\CsvImports\Models\ImportRow;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;

enum ExactDuplicateKind: string
{
    case None = 'none';
    case WithinFile = 'within_file';
    case Historical = 'historical';
}

final class ExactDuplicateMatch
{
    public function __construct(
        public readonly ExactDuplicateKind $kind,
        public readonly ?MasterCashFlowRecord $masterRecord = null,
        public readonly ?ImportRow $withinFileSourceRow = null,
    ) {}

    public function isDuplicate(): bool
    {
        return $this->kind !== ExactDuplicateKind::None;
    }
}
