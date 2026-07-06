<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Support;

final class DashboardCategoryCodes
{
    /**
     * @var list<string>
     */
    public const CATEGORY_CODES = ['A', 'B', 'B1', 'C', 'D'];

    public const CV_INSPECTION_TYPE = 'CV';

    public static function normalizeCategory(string $code): string
    {
        return trim($code);
    }

    public static function normalizeInspectionType(string $code): string
    {
        return strtoupper(trim($code));
    }
}
