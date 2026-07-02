<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Services;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Session as SessionFacade;

final class SessionService
{
    public function markAuthenticated(): void
    {
        $this->touch();
    }

    public function touch(?Session $session = null): void
    {
        $this->writer($session)->put($this->lastActivityKey(), now()->timestamp);
    }

    public function isIdleExpired(?Session $session = null): bool
    {
        $lastActivity = $this->reader($session)->get($this->lastActivityKey());

        if (! is_int($lastActivity)) {
            return false;
        }

        return now()->timestamp - $lastActivity > $this->timeoutSeconds();
    }

    public function timeoutMinutes(): int
    {
        return (int) config('auth_security.session.timeout_minutes', 120);
    }

    private function reader(?Session $session): Session
    {
        return $session ?? SessionFacade::driver();
    }

    private function writer(?Session $session): Session
    {
        return $session ?? SessionFacade::driver();
    }

    private function lastActivityKey(): string
    {
        return (string) config('auth_security.session.last_activity_key', 'auth.last_activity');
    }

    private function timeoutSeconds(): int
    {
        return $this->timeoutMinutes() * 60;
    }
}
