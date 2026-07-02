<?php

declare(strict_types=1);

use App\Modules\Dashboards\Enums\DashboardPeriod;
use Illuminate\Support\Carbon;

test('dashboard period ranges cover today yesterday and rolling windows', function () {
    Carbon::setTestNow('2026-06-15 14:30:00');
    $reference = now();

    [$todayStart, $todayEnd] = DashboardPeriod::Today->range($reference);
    expect($todayStart->toDateTimeString())->toBe('2026-06-15 00:00:00');
    expect($todayEnd->toDateTimeString())->toBe('2026-06-15 23:59:59');

    [$yesterdayStart, $yesterdayEnd] = DashboardPeriod::Yesterday->range($reference);
    expect($yesterdayStart->toDateString())->toBe('2026-06-14');
    expect($yesterdayEnd->toDateString())->toBe('2026-06-14');

    [$lastWeekStart, $lastWeekEnd] = DashboardPeriod::LastWeek->range($reference);
    expect($lastWeekStart->toDateString())->toBe('2026-06-08');
    expect($lastWeekEnd->toDateString())->toBe('2026-06-14');

    [$lastMonthStart, $lastMonthEnd] = DashboardPeriod::LastMonth->range($reference);
    expect($lastMonthStart->toDateString())->toBe('2026-05-01');
    expect($lastMonthEnd->toDateString())->toBe('2026-05-31');
});

test('dashboard custom period uses selected from and to dates', function () {
    Carbon::setTestNow('2026-06-15 14:30:00');

    [$start, $end] = DashboardPeriod::Custom->range(
        now(),
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-10'),
    );

    expect($start->toDateTimeString())->toBe('2026-06-01 00:00:00');
    expect($end->toDateTimeString())->toBe('2026-06-10 23:59:59');
});

test('dashboard period filter options include yesterday last week last month and custom', function () {
    $values = array_map(
        static fn (DashboardPeriod $period): string => $period->value,
        DashboardPeriod::filterOptions(),
    );

    expect($values)->toBe([
        'today',
        'yesterday',
        'week',
        'last_week',
        'month',
        'last_month',
        'year',
        'custom',
    ]);
});
