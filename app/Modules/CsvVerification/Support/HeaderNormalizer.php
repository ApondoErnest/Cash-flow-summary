<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Support;

final class HeaderNormalizer
{
    public static function normalize(string $header): string
    {
        $header = preg_replace('/\x{FEFF}|\xEF\xBB\xBF/u', '', $header) ?? $header;
        $header = trim($header);
        $header = str_replace(['’', '‘', '`', '´'], "'", $header);
        $header = mb_strtolower($header, 'UTF-8');
        $header = preg_replace('/\s+/u', ' ', $header) ?? $header;
        $header = self::stripAccents($header);

        return trim($header);
    }

    private static function stripAccents(string $value): string
    {
        if (class_exists(\Transliterator::class)) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);

            return is_string($transliterated) ? $transliterated : $value;
        }

        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return is_string($converted) ? $converted : $value;
    }
}
