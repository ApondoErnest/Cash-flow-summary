<?php

declare(strict_types=1);

namespace App\Support\Locale;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class LocalizedDateTime
{
    public static function date(CarbonInterface|string|null $value): string
    {
        $carbon = self::carbon($value);

        return $carbon?->translatedFormat('d/m/Y') ?? '—';
    }

    public static function time(CarbonInterface|string|null $value): string
    {
        $carbon = self::carbon($value);

        if ($carbon !== null) {
            return self::formatTimeCarbon($carbon);
        }

        if (is_string($value) && preg_match('/^\d{1,2}:\d{2}/', $value) === 1) {
            try {
                return self::formatTimeCarbon(Carbon::createFromFormat('H:i', substr($value, 0, 5)));
            } catch (\Throwable) {
                return substr($value, 0, 5);
            }
        }

        return '—';
    }

    public static function dateTime(CarbonInterface|string|null $value): string
    {
        $carbon = self::carbon($value);

        if ($carbon === null) {
            return '—';
        }

        $local = $carbon->timezone(config('app.timezone'));

        return $local->translatedFormat('d/m/Y').' '.self::formatTimeCarbon($local);
    }

    public static function dateTimeSeconds(CarbonInterface|string|null $value): string
    {
        $carbon = self::carbon($value);

        if ($carbon === null) {
            return '—';
        }

        $local = $carbon->timezone(config('app.timezone'));

        if (app()->getLocale() === 'en') {
            return $local->translatedFormat('d/m/Y').' '.$local->format('g:i:s A');
        }

        return $local->translatedFormat('d/m/Y H:i:s');
    }

    private static function formatTimeCarbon(CarbonInterface $carbon): string
    {
        if (app()->getLocale() === 'en') {
            return $carbon->format('g:i A');
        }

        return $carbon->format('H:i');
    }

    private static function carbon(CarbonInterface|string|null $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
