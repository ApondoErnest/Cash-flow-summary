<?php

declare(strict_types=1);

namespace App\Modules\Centers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\Centers\Services\ActiveCenterSwitchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SwitchActiveCenterController extends Controller
{
    public function __invoke(
        Request $request,
        Center $center,
        ActiveCenterSwitchService $switchService,
        ActiveCenterContextService $activeCenterContextService,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user !== null && $user->isOwner(), 403);

        $activeCenter = $activeCenterContextService->resolve($user);

        if ($activeCenter !== null && $activeCenter->centerId === (int) $center->id) {
            return redirect()->route('dashboard');
        }

        $switchService->switch($user, (int) $center->id);

        return redirect()->route('dashboard');
    }
}
