<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\User;
use App\Support\Center\CenterContextResolver;

trait ChecksCenterScope
{
    protected function resourceBelongsToResolvedCenter(User $user, object $resource): bool
    {
        return app(CenterContextResolver::class)->resourceBelongsToResolvedCenter($user, $resource);
    }

    protected function isOwnerAdminContext(User $user): bool
    {
        if (! $user->isOwner()) {
            return false;
        }

        $routeName = request()->route()?->getName();

        return $routeName !== null
            && ! \App\Support\Center\OperationalRouteNames::requiresActiveCenter($routeName);
    }
}
