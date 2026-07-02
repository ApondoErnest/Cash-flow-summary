<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * Spatie role names — seeded in Step 31.
 *
 * @see docs/design/data-model.md
 */
final class RoleName
{
    public const Owner = 'owner';

    public const CenterManager = 'center_manager';

    public const Cashier = 'cashier';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::Owner,
            self::CenterManager,
            self::Cashier,
        ];
    }
}
