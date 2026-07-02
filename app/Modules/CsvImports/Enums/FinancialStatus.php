<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Enums;

enum FinancialStatus: string
{
    case Revenue = 'revenue';
    case ZeroValue = 'zero_value';
}
