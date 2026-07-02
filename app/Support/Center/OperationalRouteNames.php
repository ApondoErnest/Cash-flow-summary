<?php

declare(strict_types=1);

namespace App\Support\Center;

final class OperationalRouteNames
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return config('owner_active_center.operational_route_names', []);
    }

    public static function requiresActiveCenter(?string $routeName): bool
    {
        if ($routeName === null) {
            return false;
        }

        return in_array($routeName, self::all(), true);
    }
}
