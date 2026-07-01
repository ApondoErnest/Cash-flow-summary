<?php

declare(strict_types=1);

namespace App\Support\Navigation;

final readonly class NavigationItem
{
    public function __construct(
        public string $label,
        public string $icon,
        public string $routeName,
    ) {}
}
