<?php

declare(strict_types=1);

namespace App\Modules\Reports\Enums;

enum ExportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Pdf = 'pdf';
}
