<?php

declare(strict_types=1);

namespace App\Modules\Centers\Services;

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Support\Center\ActiveCenterContext;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class OwnerPreferredCenterService
{
    public function __construct(
        private readonly ActiveCenterContextService $activeCenterContextService,
        private readonly CenterSelectionService $centerSelectionService,
    ) {}

    public function bootstrapActiveCenter(User $user): ?ActiveCenterContext
    {
        if (! $this->activeCenterContextService->appliesTo($user)) {
            return null;
        }

        $this->clearPreferredIfInvalid($user);

        $existing = $this->activeCenterContextService->resolve($user);

        if ($existing !== null) {
            return $existing;
        }

        $center = $this->resolveBootstrappableCenter($user);

        if ($center === null) {
            return null;
        }

        return $this->activeCenterContextService->set($user, $center);
    }

    public function resolveBootstrappableCenter(User $user): ?Center
    {
        if (! $user->isOwner()) {
            return null;
        }

        $activeCenters = $this->centerSelectionService->activeCentersFor($user);

        if ($activeCenters->isEmpty()) {
            return null;
        }

        $preferred = $this->findValidPreferredCenter($user, $activeCenters);

        if ($preferred !== null) {
            return $preferred;
        }

        if ($activeCenters->count() === 1) {
            return $activeCenters->first();
        }

        return null;
    }

    /**
     * @param  Collection<int, Center>|null  $activeCenters
     */
    public function findValidPreferredCenter(User $user, ?Collection $activeCenters = null): ?Center
    {
        if ($user->preferred_center_id === null) {
            return null;
        }

        $activeCenters ??= $this->centerSelectionService->activeCentersFor($user);

        return $activeCenters->firstWhere('id', $user->preferred_center_id);
    }

    public function setPreferred(User $user, Center $center): void
    {
        if (! $user->isOwner()) {
            throw new HttpException(403, __('center.not_applicable'));
        }

        if ($this->centerSelectionService->findSelectableCenter($user, (int) $center->id) === null) {
            throw new HttpException(403, __('center.active_center_invalid'));
        }

        $user->forceFill(['preferred_center_id' => $center->id])->save();
    }

    public function clearPreferred(User $user): void
    {
        if ($user->preferred_center_id === null) {
            return;
        }

        $user->forceFill(['preferred_center_id' => null])->save();
    }

    public function clearPreferredIfInvalid(User $user): void
    {
        if ($user->preferred_center_id === null) {
            return;
        }

        if ($this->findValidPreferredCenter($user) === null) {
            $this->clearPreferred($user);
        }
    }

    public function clearPreferredIfMatches(User $user, int $centerId): void
    {
        if ((int) $user->preferred_center_id === $centerId) {
            $this->clearPreferred($user);
        }
    }
}
