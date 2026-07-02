<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Support;

final class CsvFooterDetector
{
    public static function isFooterLine(string $line): bool
    {
        $normalized = mb_strtolower($line, 'UTF-8');

        return str_contains($normalized, 'nombre total d')
            || str_contains($normalized, 'total number of inspections');
    }
}
