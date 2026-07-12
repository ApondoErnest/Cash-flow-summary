<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Support;

final class DashboardMoney
{
    /**
     * Display amount for UI / WhatsApp.
     * FR: 1 202 130,00 — EN: 1,202,130.00
     */
    public static function format(string|float|int $amount, ?string $locale = null): string
    {
        $locale = self::resolveLocale($locale);

        if ($locale === 'fr') {
            return number_format((float) $amount, 2, ',', ' ');
        }

        return number_format((float) $amount, 2, '.', ',');
    }

    /**
     * Integer display (counts, etc.).
     * FR: 1 202 — EN: 1,202
     */
    public static function formatInteger(int|float|string $amount, ?string $locale = null): string
    {
        $locale = self::resolveLocale($locale);

        if ($locale === 'fr') {
            return number_format((float) $amount, 0, '', ' ');
        }

        return number_format((float) $amount, 0, '', ',');
    }

    public static function sum(array $amounts): string
    {
        $total = 0.0;

        foreach ($amounts as $amount) {
            $total += (float) $amount;
        }

        return number_format($total, 2, '.', '');
    }

    private static function resolveLocale(?string $locale): string
    {
        $resolved = strtolower(trim((string) ($locale ?? app()->getLocale())));

        return in_array($resolved, ['en', 'fr'], true) ? $resolved : 'en';
    }
}
