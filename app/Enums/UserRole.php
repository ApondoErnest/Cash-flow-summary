<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Cashier = 'cashier';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Center Manager',
            self::Cashier => 'Cashier',
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
}
