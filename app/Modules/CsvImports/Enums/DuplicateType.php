<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Enums;

enum DuplicateType: string
{
    case WithinFile = 'within_file';
    case Historical = 'historical';
    case Probable = 'probable';
}
