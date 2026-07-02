<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Services;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class LoginRateLimiter
{
    public function ensureNotRateLimited(string $username, string $ipAddress): void
    {
        foreach ($this->keys($username, $ipAddress) as $key) {
            if (RateLimiter::tooManyAttempts($key, $this->maxAttempts())) {
                $seconds = RateLimiter::availableIn($key);

                throw ValidationException::withMessages([
                    'username' => [__('auth.throttle', ['seconds' => $seconds])],
                ]);
            }
        }
    }

    public function hit(string $username, string $ipAddress): void
    {
        foreach ($this->keys($username, $ipAddress) as $key) {
            RateLimiter::hit($key, $this->decaySeconds());
        }
    }

    public function clear(string $username, string $ipAddress): void
    {
        foreach ($this->keys($username, $ipAddress) as $key) {
            RateLimiter::clear($key);
        }
    }

    /**
     * @return list<string>
     */
    private function keys(string $username, string $ipAddress): array
    {
        $normalizedUsername = mb_strtolower(trim($username));

        return [
            'login-ip:'.sha1($ipAddress),
            'login-username:'.sha1($normalizedUsername),
        ];
    }

    private function maxAttempts(): int
    {
        return (int) config('auth_security.login.max_attempts', 5);
    }

    private function decaySeconds(): int
    {
        return (int) config('auth_security.login.decay_minutes', 15) * 60;
    }
}
