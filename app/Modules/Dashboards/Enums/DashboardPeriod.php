<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Enums;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

enum DashboardPeriod: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case Week = 'week';
    case LastWeek = 'last_week';
    case Month = 'month';
    case LastMonth = 'last_month';
    case Year = 'year';
    case Custom = 'custom';

    /**
     * @return list<self>
     */
    public static function filterOptions(): array
    {
        return [
            self::Today,
            self::Yesterday,
            self::Week,
            self::LastWeek,
            self::Month,
            self::LastMonth,
            self::Year,
            self::Custom,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(Carbon $reference, ?Carbon $customFrom = null, ?Carbon $customTo = null): array
    {
        return match ($this) {
            self::Today => [
                $reference->copy()->startOfDay(),
                $reference->copy()->endOfDay(),
            ],
            self::Yesterday => [
                $reference->copy()->subDay()->startOfDay(),
                $reference->copy()->subDay()->endOfDay(),
            ],
            self::Week => [
                $reference->copy()->startOfWeek(),
                $reference->copy()->endOfWeek(),
            ],
            self::LastWeek => [
                $reference->copy()->subWeek()->startOfWeek(),
                $reference->copy()->subWeek()->endOfWeek(),
            ],
            self::Month => [
                $reference->copy()->startOfMonth(),
                $reference->copy()->endOfMonth(),
            ],
            self::LastMonth => [
                $reference->copy()->subMonth()->startOfMonth(),
                $reference->copy()->subMonth()->endOfMonth(),
            ],
            self::Year => [
                $reference->copy()->startOfYear(),
                $reference->copy()->endOfYear(),
            ],
            self::Custom => [
                ($customFrom ?? throw new InvalidArgumentException('Custom period requires from and to dates.'))
                    ->copy()
                    ->startOfDay(),
                ($customTo ?? throw new InvalidArgumentException('Custom period requires from and to dates.'))
                    ->copy()
                    ->endOfDay(),
            ],
        };
    }

    public function label(?Carbon $customFrom = null, ?Carbon $customTo = null): string
    {
        return match ($this) {
            self::Today => __('dashboard.period.today'),
            self::Yesterday => __('dashboard.period.yesterday'),
            self::Week => __('dashboard.period.week'),
            self::LastWeek => __('dashboard.period.last_week'),
            self::Month => __('dashboard.period.month'),
            self::LastMonth => __('dashboard.period.last_month'),
            self::Year => __('dashboard.period.year'),
            self::Custom => ($customFrom !== null && $customTo !== null)
                ? __('dashboard.period.custom_range', [
                    'from' => $customFrom->format('d/m/Y'),
                    'to' => $customTo->format('d/m/Y'),
                ])
                : __('dashboard.period.custom'),
        };
    }
}
