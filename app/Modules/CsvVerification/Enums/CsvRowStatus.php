<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Enums;

enum CsvRowStatus: string
{
    case Completed = 'completed';
    case Unfinished = 'unfinished';
    case Invalid = 'invalid';
}
