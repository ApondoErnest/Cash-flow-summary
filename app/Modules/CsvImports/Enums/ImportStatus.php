<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Enums;

enum ImportStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithDuplicates = 'completed_with_duplicates';
    case CompletedWithWarnings = 'completed_with_warnings';
    case ExactFileDuplicate = 'exact_file_duplicate';
    case AwaitingOwnerApproval = 'awaiting_owner_approval';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
