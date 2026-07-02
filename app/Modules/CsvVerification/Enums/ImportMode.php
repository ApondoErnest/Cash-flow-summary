<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Enums;

enum ImportMode: string
{
    case Operational = 'operational';
    case Historical = 'historical';
    case Correction = 'correction';
}
