<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Enums;

enum ImportRowStatus: string
{
    case New = 'new';
    case Accepted = 'accepted';
    case DuplicateWithinFile = 'duplicate_within_file';
    case HistoricalDuplicate = 'historical_duplicate';
    case ProbableDuplicate = 'probable_duplicate';
    case Invalid = 'invalid';
    case Ignored = 'ignored';
}
