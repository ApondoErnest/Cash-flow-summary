<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Enums;

enum VerificationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Imported = 'imported';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Failed = 'failed';
}
