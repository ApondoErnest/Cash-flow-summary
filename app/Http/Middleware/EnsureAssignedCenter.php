<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Centers\Services\AssignedCenterService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAssignedCenter
{
    public function __construct(
        private readonly AssignedCenterService $assignedCenterService,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->assignedCenterService->appliesTo($user)) {
            return $next($request);
        }

        $context = $this->assignedCenterService->resolve($user);

        if (! $this->assignedCenterService->requestIsScopedToAssignedCenter($user, $request)) {
            abort(403, __('center.cross_center_forbidden'));
        }

        $request->attributes->set('assigned_center', $context);

        return $next($request);
    }
}
