<?php

declare(strict_types=1);

namespace App\Modules\Centers\Services;

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Support\Center\AssignedCenterContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AssignedCenterService
{
    public function appliesTo(User $user): bool
    {
        return $user->isCenterStaff();
    }

    public function resolve(User $user): AssignedCenterContext
    {
        if (! $this->appliesTo($user)) {
            throw new HttpException(403, __('center.not_applicable'));
        }

        if ($user->center_id === null) {
            throw new HttpException(403, __('center.assigned_missing'));
        }

        $center = $user->center;

        if ($center === null) {
            throw new HttpException(403, __('center.assigned_missing'));
        }

        if ((int) $center->organization_id !== (int) $user->organization_id) {
            throw new HttpException(403, __('center.assigned_invalid'));
        }

        if (! $center->is_active) {
            throw new HttpException(403, __('center.assigned_inactive'));
        }

        return new AssignedCenterContext(
            centerId: (int) $center->id,
            organizationId: (int) $center->organization_id,
            centerName: $center->name,
        );
    }

    public function requestIsScopedToAssignedCenter(User $user, Request $request): bool
    {
        if (! $this->appliesTo($user)) {
            return true;
        }

        $assignedCenterId = $user->center_id;

        if ($assignedCenterId === null) {
            return false;
        }

        foreach ($this->extractRequestedCenterIds($request) as $requestedCenterId) {
            if ((int) $requestedCenterId !== (int) $assignedCenterId) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<int>
     */
    public function extractRequestedCenterIds(Request $request): array
    {
        $ids = [];

        foreach (['center_id', 'center'] as $key) {
            $value = $request->route($key) ?? $request->query($key) ?? $request->input($key);

            if ($value !== null && $value !== '') {
                $centerId = $this->normalizeCenterId($value);

                if ($centerId !== null) {
                    $ids[] = $centerId;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function normalizeCenterId(mixed $value): ?int
    {
        if ($value instanceof Center) {
            return (int) $value->id;
        }

        if (is_object($value) && isset($value->id)) {
            return (int) $value->id;
        }

        return (int) $value;
    }
}
