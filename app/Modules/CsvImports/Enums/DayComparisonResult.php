<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Enums;

enum DayComparisonResult: string
{
    case New = 'new';
    case Unchanged = 'unchanged';
    case RevisionRequired = 'revision_required';
    case CoveredWithoutRows = 'covered_without_rows';
    case Invalid = 'invalid';
}
