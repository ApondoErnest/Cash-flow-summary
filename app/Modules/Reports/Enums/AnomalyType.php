<?php

declare(strict_types=1);

namespace App\Modules\Reports\Enums;

enum AnomalyType: string
{
    case ProbableDuplicate = 'probable_duplicate';
    case ReconciliationFailure = 'reconciliation_failure';
}
