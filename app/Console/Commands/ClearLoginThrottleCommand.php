<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;

final class ClearLoginThrottleCommand extends Command
{
    protected $signature = 'auth:clear-login-throttle
                            {username? : Clear throttle for a username (defaults to all known keys when omitted with --ip)}
                            {--ip= : Clear throttle for a specific IP address}';

    protected $description = 'Clear login rate limiter keys (local recovery after failed attempts)';

    public function handle(): int
    {
        $cleared = 0;

        if ($ip = $this->option('ip')) {
            RateLimiter::clear('login-ip:'.sha1((string) $ip));
            $this->line("Cleared login throttle for IP [{$ip}].");
            $cleared++;
        }

        if ($username = $this->argument('username')) {
            RateLimiter::clear('login-username:'.sha1(mb_strtolower(trim((string) $username))));
            $this->line("Cleared login throttle for username [{$username}].");
            $cleared++;
        }

        if ($cleared === 0) {
            $this->error('Provide a username and/or --ip= to clear login throttles.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
