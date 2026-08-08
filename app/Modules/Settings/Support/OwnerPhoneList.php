<?php

declare(strict_types=1);

namespace App\Modules\Settings\Support;

final class OwnerPhoneList
{
    private const E164_PATTERN = '/^\+[1-9]\d{7,14}$/';

    /**
     * @return list<string>
     */
    public static function parse(?string $stored): array
    {
        if ($stored === null || trim($stored) === '') {
            return [];
        }

        $phones = [];

        foreach (self::split($stored) as $part) {
            $normalized = self::normalizeSingle($part);

            if ($normalized === '' || ! self::isValidSingle($normalized)) {
                continue;
            }

            $phones[] = $normalized;
        }

        return array_values(array_unique($phones));
    }

    public static function normalizeInput(string $input): string
    {
        $phones = self::parse($input);

        if ($phones === []) {
            return '';
        }

        return implode(',', $phones);
    }

    public static function isValidInput(string $input): bool
    {
        if (trim($input) === '') {
            return false;
        }

        $phones = [];

        foreach (self::split($input) as $part) {
            $normalized = self::normalizeSingle($part);

            if ($normalized === '' || ! self::isValidSingle($normalized)) {
                return false;
            }

            $phones[] = $normalized;
        }

        return $phones !== [];
    }

    /**
     * @return list<string>
     */
    private static function split(string $input): array
    {
        return preg_split('/\s*,\s*/', trim($input)) ?: [];
    }

    private static function normalizeSingle(string $phone): string
    {
        $normalized = preg_replace('/\s+/', '', trim($phone));

        return $normalized ?? trim($phone);
    }

    private static function isValidSingle(string $phone): bool
    {
        return (bool) preg_match(self::E164_PATTERN, $phone);
    }
}
