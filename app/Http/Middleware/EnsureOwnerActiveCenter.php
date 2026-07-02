<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\Centers\Services\OwnerPreferredCenterService;
use App\Support\Center\OperationalRouteNames;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerActiveCenter
{
    public function __construct(
        private readonly ActiveCenterContextService $activeCenterContextService,
        private readonly OwnerPreferredCenterService $ownerPreferredCenterService,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->activeCenterContextService->appliesTo($user)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName === config('owner_active_center.selection_route_name')) {
            return $next($request);
        }

        if (! OperationalRouteNames::requiresActiveCenter($routeName)) {
            return $next($request);
        }

        $context = $this->activeCenterContextService->resolve($user);

        if ($context === null) {
            $clearedInvalidContext = $this->activeCenterContextService->consumedInvalidContextClear();

            if ($clearedInvalidContext) {
                $this->ownerPreferredCenterService->clearPreferredIfInvalid($user);
            }

            $redirect = redirect()->guest(route((string) config('owner_active_center.selection_route_name')));

            if ($clearedInvalidContext) {
                $redirect->with('status', __('center.active_center_cleared'));
            }

            return $redirect;
        }

        if (! $this->activeCenterContextService->requestIsScopedToActiveCenter($user, $request, $context)) {
            abort(403, __('center.cross_center_forbidden'));
        }

        $request->attributes->set('active_center', $context);

        return $next($request);
    }
}
