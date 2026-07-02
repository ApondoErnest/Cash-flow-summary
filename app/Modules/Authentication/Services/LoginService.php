<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class LoginService
{
    public function __construct(
        private readonly LoginRateLimiter $rateLimiter,
        private readonly SessionService $sessionService,
    ) {}

    public function authenticate(string $username, string $password, bool $remember, string $ipAddress): User
    {
        $this->rateLimiter->ensureNotRateLimited($username, $ipAddress);

        if (! Auth::attempt(
            [
                'username' => $username,
                'password' => $password,
                'is_active' => true,
            ],
            $remember,
        )) {
            $this->rateLimiter->hit($username, $ipAddress);

            throw ValidationException::withMessages([
                'username' => [__('auth.failed')],
            ]);
        }

        $this->rateLimiter->clear($username, $ipAddress);

        /** @var User $user */
        $user = Auth::user();

        $user->update(['last_login_at' => now()]);

        $this->sessionService->markAuthenticated();

        return $user;
    }
}
