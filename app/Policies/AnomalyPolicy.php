<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Reports\Models\Anomaly;
use App\Support\Center\CenterContextResolver;

class AnomalyPolicy extends CenterResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return app(CenterContextResolver::class)->canImport($user);
    }

    public function view(User $user, Anomaly $anomaly): bool
    {
        return $this->resourceBelongsToResolvedCenter($user, $anomaly);
    }

    public function resolve(User $user, Anomaly $anomaly): bool
    {
        if ($anomaly->resolved_at !== null) {
            return false;
        }

        return $this->resourceBelongsToResolvedCenter($user, $anomaly);
    }
}
