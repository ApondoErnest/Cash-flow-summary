<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Authentication\Services\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->twoFactorService->mustVerify($user)) {
            return $next($request);
        }

        if ($this->twoFactorService->isVerified()) {
            return $next($request);
        }

        if ($request->routeIs('two-factor.challenge', 'logout')) {
            return $next($request);
        }

        return redirect()->route('two-factor.challenge');
    }
}
