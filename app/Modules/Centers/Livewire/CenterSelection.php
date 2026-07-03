<?php

declare(strict_types=1);

namespace App\Modules\Centers\Livewire;

use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\Centers\Services\CenterSelectionService;
use App\Modules\Centers\Services\OwnerPreferredCenterService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.center-selection')]
class CenterSelection extends Component
{
    public ?int $centerId = null;

    public string $search = '';

    public bool $rememberAsDefault = true;

    public function mount(CenterSelectionService $centerSelectionService): void
    {
        $user = auth()->user();

        if ($user === null) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $centers = $centerSelectionService->activeCentersFor($user);

        if ($centers->count() === 1) {
            $this->centerId = (int) $centers->first()->id;
        }

        $this->rememberAsDefault = $user->preferred_center_id === null
            || ($this->centerId !== null && (int) $user->preferred_center_id === $this->centerId);
    }

    public function selectCenter(int $centerId): void
    {
        $this->centerId = $centerId;
    }

    public function openCenter(
        CenterSelectionService $centerSelectionService,
        ActiveCenterContextService $activeCenterContextService,
        OwnerPreferredCenterService $ownerPreferredCenterService,
    ): void {
        $user = auth()->user();

        if ($user === null) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $this->validate(
            [
                'centerId' => ['required', 'integer'],
            ],
            [],
            [
                'centerId' => __('center.selection.field'),
            ],
        );

        $center = $centerSelectionService->findSelectableCenter($user, (int) $this->centerId);

        if ($center === null) {
            $this->addError('centerId', __('center.selection.invalid'));

            return;
        }

        $activeCenterContextService->set($user, $center);

        if ($this->rememberAsDefault) {
            $ownerPreferredCenterService->setPreferred($user, $center);
        }

        $this->redirectIntended(default: route('dashboard'), navigate: true);
    }

    /**
     * @return Collection<int, Center>
     */
    #[Computed]
    public function centers(): Collection
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        $centerSelectionService = app(CenterSelectionService::class);

        return $centerSelectionService->searchCenters(
            $centerSelectionService->activeCentersFor($user),
            $this->search,
        );
    }

    public function render(CenterSelectionService $centerSelectionService): View
    {
        $user = auth()->user();
        $allCenters = $user !== null
            ? $centerSelectionService->activeCentersFor($user)
            : collect();

        $canReturnToDashboard = $user !== null
            && app(ActiveCenterContextService::class)->resolve($user) !== null;

        return view('livewire.centers.center-selection', [
            'hasCenters' => $allCenters->isNotEmpty(),
            'centerCount' => $allCenters->count(),
            'statusMessage' => session('status'),
            'canReturnToDashboard' => $canReturnToDashboard,
        ])->title(__('center.selection.title'));
    }
}
