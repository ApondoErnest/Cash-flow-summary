<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Auth\RoleName;

enum UserRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Cashier = 'cashier';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('roles.owner'),
            self::Manager => __('roles.manager'),
            self::Cashier => __('roles.cashier'),
        };
    }

    public function initials(): string
    {
        return match ($this) {
            self::Owner => 'OW',
            self::Manager => 'MG',
            self::Cashier => 'CA',
        };
    }

    public static function fromPreview(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Owner;
    }

    public function spatieName(): string
    {
        return match ($this) {
            self::Owner => RoleName::Owner,
            self::Manager => RoleName::CenterManager,
            self::Cashier => RoleName::Cashier,
        };
    }
}
