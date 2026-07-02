<?php

declare(strict_types=1);

namespace App\Enums;

enum CalendarExceptionType: string
{
    case Holiday = 'holiday';
    case Closure = 'closure';
    case SpecialOpen = 'special_open';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
