<?php

declare(strict_types=1);

namespace App\Modules\Centers\Services;

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Support\Center\ActiveCenterContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ActiveCenterContextService
{
    private bool $clearedInvalidContext = false;

    public function appliesTo(User $user): bool
    {
        return $user->isOwner();
    }

    public function hasStoredCenter(): bool
    {
        return Session::has($this->sessionKey('center_id'));
    }

    public function set(User $user, Center $center): ActiveCenterContext
    {
        if (! $this->appliesTo($user)) {
            throw new HttpException(403, __('center.not_applicable'));
        }

        $this->assertCenterEligible($user, $center);

        Session::put($this->sessionKey('center_id'), (int) $center->id);
        Session::put($this->sessionKey('organization_id'), (int) $user->organization_id);
        Session::put($this->sessionKey('owner_user_id'), (int) $user->id);
        Session::put($this->sessionKey('selected_at'), now()->toIso8601String());

        return $this->contextFromCenter($center, (int) $user->id);
    }

    public function resolve(User $user): ?ActiveCenterContext
    {
        $this->clearedInvalidContext = false;

        if (! $this->appliesTo($user)) {
            return null;
        }

        $centerId = Session::get($this->sessionKey('center_id'));

        if ($centerId === null) {
            return null;
        }

        $organizationId = Session::get($this->sessionKey('organization_id'));
        $ownerUserId = Session::get($this->sessionKey('owner_user_id'));

        if ((int) $ownerUserId !== (int) $user->id) {
            $this->clearInvalidContext();

            return null;
        }

        if ((int) $organizationId !== (int) $user->organization_id) {
            $this->clearInvalidContext();

            return null;
        }

        $center = Center::query()->find($centerId);

        if ($center === null
            || (int) $center->organization_id !== (int) $user->organization_id
            || ! $center->is_active) {
            $this->clearInvalidContext();

            return null;
        }

        return $this->contextFromCenter($center, (int) $user->id);
    }

    public function clear(): void
    {
        foreach (config('owner_active_center.session', []) as $key) {
            Session::forget($key);
        }
    }

    public function consumedInvalidContextClear(): bool
    {
        $cleared = $this->clearedInvalidContext;
        $this->clearedInvalidContext = false;

        return $cleared;
    }

    public function requestIsScopedToActiveCenter(User $user, Request $request, ActiveCenterContext $context): bool
    {
        if (! $this->appliesTo($user)) {
            return true;
        }

        foreach ($this->extractRequestedCenterIds($request) as $requestedCenterId) {
            if ($requestedCenterId !== $context->centerId) {
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

    private function clearInvalidContext(): void
    {
        $this->clear();
        $this->clearedInvalidContext = true;
    }

    private function assertCenterEligible(User $user, Center $center): void
    {
        if ((int) $center->organization_id !== (int) $user->organization_id) {
            throw new HttpException(403, __('center.active_center_invalid'));
        }

        if (! $center->is_active) {
            throw new HttpException(403, __('center.active_center_inactive'));
        }
    }

    private function contextFromCenter(Center $center, int $ownerUserId): ActiveCenterContext
    {
        return new ActiveCenterContext(
            centerId: (int) $center->id,
            organizationId: (int) $center->organization_id,
            centerName: $center->name,
            ownerUserId: $ownerUserId,
        );
    }

    private function sessionKey(string $name): string
    {
        return (string) config("owner_active_center.session.{$name}");
    }
}
