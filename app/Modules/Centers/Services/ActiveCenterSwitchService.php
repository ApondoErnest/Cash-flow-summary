<?php

declare(strict_types=1);

namespace App\Modules\Centers\Services;

use App\Models\User;
use App\Support\Center\ActiveCenterContext;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ActiveCenterSwitchService
{
    public function __construct(
        private readonly ActiveCenterContextService $activeCenterContextService,
        private readonly CenterSelectionService $centerSelectionService,
    ) {}

    public function switch(User $user, int $centerId): ActiveCenterContext
    {
        if (! $this->activeCenterContextService->appliesTo($user)) {
            throw new HttpException(403, __('center.not_applicable'));
        }

        $center = $this->centerSelectionService->findSelectableCenter($user, $centerId);

        if ($center === null) {
            throw new HttpException(403, __('center.active_center_invalid'));
        }

        $this->clearPageFilters();

        return $this->activeCenterContextService->set($user, $center);
    }

    private function clearPageFilters(): void
    {
        foreach (config('owner_active_center.page_filter_session_keys', []) as $sessionKey) {
            Session::forget($sessionKey);
        }
    }
}
