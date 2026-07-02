<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Support\Center\CenterContextResolver;

class DailyVersionPolicy extends CenterResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return app(CenterContextResolver::class)->canImport($user);
    }

    public function view(User $user, DailyVersion $version): bool
    {
        return $this->resourceBelongsToResolvedCenter($user, $version);
    }
}
