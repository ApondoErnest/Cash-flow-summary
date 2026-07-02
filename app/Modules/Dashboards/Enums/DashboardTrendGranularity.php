<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Enums;

enum DashboardTrendGranularity: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => __('dashboard.trend.daily'),
            self::Weekly => __('dashboard.trend.weekly'),
            self::Monthly => __('dashboard.trend.monthly'),
            self::Yearly => __('dashboard.trend.yearly'),
        };
    }
}
