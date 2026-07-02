<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Services;

use App\Models\User;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\Centers\Services\OwnerPreferredCenterService;

final class AuthenticationRedirectService
{
    public function __construct(
        private readonly PasswordService $passwordService,
        private readonly TwoFactorService $twoFactorService,
        private readonly ActiveCenterContextService $activeCenterContextService,
        private readonly OwnerPreferredCenterService $ownerPreferredCenterService,
    ) {}

    public function nextRoute(User $user): string
    {
        if ($this->passwordService->mustChange($user)) {
            return route('password.change');
        }

        if ($this->twoFactorService->mustVerify($user) && ! $this->twoFactorService->isVerified()) {
            return route('two-factor.challenge');
        }

        if ($this->activeCenterContextService->appliesTo($user)) {
            $this->ownerPreferredCenterService->bootstrapActiveCenter($user);

            if ($this->activeCenterContextService->resolve($user) === null) {
                return route((string) config('owner_active_center.selection_route_name'));
            }
        }

        return route('dashboard');
    }
}
