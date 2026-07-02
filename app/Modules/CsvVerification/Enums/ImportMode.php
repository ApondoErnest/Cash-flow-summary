<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Enums;

use App\Models\User;
use App\Support\Auth\RoleName;

enum ImportMode: string
{
    case Operational = 'operational';
    case Historical = 'historical';
    case Correction = 'correction';

    /**
     * @return list<self>
     */
    public static function availableFor(User $user): array
    {
        $modes = [
            self::Operational,
            self::Historical,
        ];

        if ($user->isOwner() || $user->hasRole(RoleName::CenterManager)) {
            $modes[] = self::Correction;
        }

        return $modes;
    }

    public function canSubmit(User $user): bool
    {
        return in_array($this, self::availableFor($user), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Operational => __('csv_verification.import_mode.operational'),
            self::Historical => __('csv_verification.import_mode.historical'),
            self::Correction => __('csv_verification.import_mode.correction'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Operational => __('csv_verification.import_mode.operational_description'),
            self::Historical => __('csv_verification.import_mode.historical_description'),
            self::Correction => __('csv_verification.import_mode.correction_description'),
        };
    }
}
