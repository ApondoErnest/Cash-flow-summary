<?php

declare(strict_types=1);

namespace App\Modules\Centers\Livewire;

use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\Centers\Services\CenterSelectionService;
use App\Support\Center\ActiveCenterContext;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CenterSwitcher extends Component
{
    #[Computed]
    public function activeCenter(): ?ActiveCenterContext
    {
        $user = auth()->user();

        if ($user === null || ! $user->isOwner()) {
            return null;
        }

        return app(ActiveCenterContextService::class)->resolve($user);
    }

    /**
     * @return Collection<int, Center>
     */
    #[Computed]
    public function centers(): Collection
    {
        $user = auth()->user();

        if ($user === null || ! $user->isOwner()) {
            return collect();
        }

        return app(CenterSelectionService::class)->activeCentersFor($user);
    }

    public function render(CenterSelectionService $centerSelectionService): View
    {
        $user = auth()->user();

        if ($user === null || ! $user->isOwner()) {
            return view('livewire.centers.center-switcher-empty');
        }

        $activeCenter = $this->activeCenter;

        return view('livewire.centers.center-switcher', [
            'activeCenter' => $activeCenter,
            'centers' => $this->centers,
            'displayLabel' => static function (Center $center) use ($centerSelectionService): string {
                return $centerSelectionService->displayLabel($center);
            },
        ]);
    }
}
