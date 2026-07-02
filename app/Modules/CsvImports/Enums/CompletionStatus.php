<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Enums;

enum CompletionStatus: string
{
    case Completed = 'completed';
    case Unfinished = 'unfinished';
}
