<?php

declare(strict_types=1);

namespace App\Support\Locale;

use Illuminate\Support\Facades\Cookie;

final class AppLocale
{
    public static function supported(): array
    {
        return config('locale.supported', ['en', 'fr']);
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::supported(), true);
    }

    public static function resolve(): string
    {
        $locale = session(config('locale.session_key'))
            ?? request()->cookie(config('locale.cookie'))
            ?? config('app.locale');

        if (! is_string($locale) || ! self::isSupported($locale)) {
            $fallback = config('app.fallback_locale', 'en');

            return self::isSupported($fallback) ? $fallback : 'en';
        }

        return $locale;
    }

    public static function apply(): void
    {
        app()->setLocale(self::resolve());
    }

    public static function set(string $locale): void
    {
        if (! self::isSupported($locale)) {
            return;
        }

        session()->put(config('locale.session_key'), $locale);
        Cookie::queue(Cookie::forever(config('locale.cookie'), $locale));
        app()->setLocale($locale);
    }
}
