<?php

declare(strict_types=1);

namespace App\Support\Center;

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\Centers\Services\AssignedCenterService;

final class CenterContextResolver
{
    public function __construct(
        private readonly ActiveCenterContextService $activeCenterContextService,
        private readonly AssignedCenterService $assignedCenterService,
    ) {}

    public function resolve(?User $user = null): ?ResolvedCenterContext
    {
        $user ??= auth()->user();

        if ($user === null) {
            return null;
        }

        $activeCenter = request()->attributes->get('active_center');

        if ($activeCenter instanceof ActiveCenterContext) {
            return ResolvedCenterContext::fromActiveCenter($activeCenter);
        }

        $assignedCenter = request()->attributes->get('assigned_center');

        if ($assignedCenter instanceof AssignedCenterContext) {
            return ResolvedCenterContext::fromAssignedCenter($assignedCenter);
        }

        if ($this->assignedCenterService->appliesTo($user)) {
            return $this->resolveAssignedCenter($user);
        }

        if ($user->isOwner()) {
            $context = $this->activeCenterContextService->resolve($user);

            return $context !== null
                ? ResolvedCenterContext::fromActiveCenter($context)
                : null;
        }

        return null;
    }

    public function shouldApplyOperationalScope(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        if ($this->assignedCenterService->appliesTo($user)) {
            return $this->resolve($user) !== null;
        }

        if ($user->isOwner()) {
            $routeName = request()->route()?->getName();

            if ($routeName !== null && ! OperationalRouteNames::requiresActiveCenter($routeName)) {
                return false;
            }

            return $this->resolve($user) !== null;
        }

        return false;
    }

    public function resourceBelongsToResolvedCenter(User $user, object $resource): bool
    {
        $centerId = $resource instanceof \Illuminate\Database\Eloquent\Model
            ? $resource->getAttribute('center_id')
            : ($resource->center_id ?? null);

        if ($centerId === null) {
            return false;
        }

        $context = $this->resolve($user);

        if ($context === null) {
            return false;
        }

        return (int) $centerId === $context->centerId;
    }

    public function canImport(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->isOwner() || $user->isCenterStaff()) {
            return $this->resolve($user) !== null;
        }

        return false;
    }

    private function resolveAssignedCenter(User $user): ?ResolvedCenterContext
    {
        if ($user->center_id === null) {
            return null;
        }

        $center = $user->relationLoaded('center')
            ? $user->center
            : Center::query()->find($user->center_id);

        if ($center === null
            || (int) $center->organization_id !== (int) $user->organization_id
            || ! $center->is_active) {
            return null;
        }

        return new ResolvedCenterContext(
            centerId: (int) $center->id,
            organizationId: (int) $center->organization_id,
            centerName: $center->name,
            source: ResolvedCenterContext::SOURCE_ASSIGNED,
        );
    }
}
