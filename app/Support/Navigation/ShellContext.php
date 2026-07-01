<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use App\Enums\UserRole;

final readonly class ShellContext
{
    /**
     * @param  list<NavigationGroup>  $navigationGroups
     */
    public function __construct(
        public UserRole $role,
        public string $centerName,
        public string $centerLabel,
        public bool $showsCenterSwitcher,
        public array $navigationGroups,
    ) {}
}
