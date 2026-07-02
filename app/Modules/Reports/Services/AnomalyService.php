<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Models\User;
use App\Modules\Reports\Models\Anomaly;
use App\Support\Center\CenterContextResolver;
use Illuminate\Auth\Access\AuthorizationException;

final class AnomalyService
{
    public function __construct(
        private readonly CenterContextResolver $centerContextResolver,
    ) {}

    public function resolve(User $user, Anomaly $anomaly): void
    {
        if ($anomaly->resolved_at !== null) {
            throw new AuthorizationException(__('anomalies.resolve.already_resolved'));
        }

        if (! $this->centerContextResolver->resourceBelongsToResolvedCenter($user, $anomaly)) {
            throw new AuthorizationException(__('anomalies.resolve.not_allowed'));
        }

        $anomaly->forceFill(['resolved_at' => now()])->save();
    }
}
