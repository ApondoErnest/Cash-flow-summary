<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Authentication\Services\SessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    public function __construct(
        private readonly SessionService $sessionService,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $this->sessionService->isIdleExpired($request->session())) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('auth_status', 'session_expired');
        }

        if ($request->user()) {
            $this->sessionService->touch($request->session());
        }

        return $next($request);
    }
}
