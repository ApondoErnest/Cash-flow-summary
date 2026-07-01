<?php

declare(strict_types=1);

namespace App\Support\Navigation;

final readonly class NavigationGroup
{
    /**
     * @param  list<NavigationItem>  $items
     */
    public function __construct(
        public string $heading,
        public array $items,
    ) {}
}
