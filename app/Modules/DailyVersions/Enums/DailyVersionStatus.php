<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Enums;

enum DailyVersionStatus: string
{
    case Proposed = 'proposed';
    case Active = 'active';
    case Superseded = 'superseded';
    case Rejected = 'rejected';
    case Invalid = 'invalid';
}
