<?php

declare(strict_types=1);

use App\Modules\Dashboards\Support\DashboardMoney;

test('dashboard money formats french and english display amounts', function () {
    expect(DashboardMoney::format(1202130, 'fr'))->toBe('1 202 130,00')
        ->and(DashboardMoney::format(1202130, 'en'))->toBe('1,202,130.00')
        ->and(DashboardMoney::format(0, 'fr'))->toBe('0,00')
        ->and(DashboardMoney::format(0, 'en'))->toBe('0.00')
        ->and(DashboardMoney::formatInteger(1202, 'fr'))->toBe('1 202')
        ->and(DashboardMoney::formatInteger(1202, 'en'))->toBe('1,202');
});

test('dashboard money follows app locale when no locale is passed', function () {
    app()->setLocale('en');
    expect(DashboardMoney::format(11925))->toBe('11,925.00');

    app()->setLocale('fr');
    expect(DashboardMoney::format(11925))->toBe('11 925,00');
});
