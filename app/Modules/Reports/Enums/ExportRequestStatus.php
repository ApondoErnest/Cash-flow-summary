<?php

declare(strict_types=1);

namespace App\Modules\Reports\Enums;

enum ExportRequestStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
}
