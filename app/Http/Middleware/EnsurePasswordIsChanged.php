<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Authentication\Services\PasswordService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function __construct(
        private readonly PasswordService $passwordService,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->passwordService->mustChange($user)) {
            return $next($request);
        }

        if ($request->routeIs('password.change', 'logout')) {
            return $next($request);
        }

        return redirect()->route('password.change');
    }
}
