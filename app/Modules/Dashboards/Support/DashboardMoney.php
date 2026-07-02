<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Support;

final class DashboardMoney
{
    public static function format(string|float|int $amount): string
    {
        return number_format((float) $amount, 2, ',', ' ');
    }

    public static function sum(array $amounts): string
    {
        $total = 0.0;

        foreach ($amounts as $amount) {
            $total += (float) $amount;
        }

        return number_format($total, 2, '.', '');
    }
}
