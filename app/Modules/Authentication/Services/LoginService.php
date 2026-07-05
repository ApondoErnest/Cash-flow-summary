<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Services;

use App\Models\User;
use App\Modules\AuditLogging\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class LoginService
{
    public function __construct(
        private readonly LoginRateLimiter $rateLimiter,
        private readonly SessionService $sessionService,
        private readonly AuditLogger $auditLogger,
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

            $this->auditLogger->record(
                event: 'login.failed',
                newValues: ['username' => $username],
            );

            throw ValidationException::withMessages([
                'username' => [__('auth.failed')],
            ]);
        }

        $this->rateLimiter->clear($username, $ipAddress);

        /** @var User $user */
        $user = Auth::user();

        $user->update(['last_login_at' => now()]);

        $this->sessionService->markAuthenticated();

        $this->auditLogger->record(
            event: 'login',
            user: $user,
            centerId: $user->center_id !== null ? (int) $user->center_id : null,
            resourceType: User::class,
            resourceId: (int) $user->id,
        );

        return $user;
    }
}
