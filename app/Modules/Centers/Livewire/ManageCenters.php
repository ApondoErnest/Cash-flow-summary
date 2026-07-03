<?php

declare(strict_types=1);

namespace App\Modules\Centers\Livewire;

use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\ActiveCenterSwitchService;
use App\Modules\Centers\Services\CenterService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ManageCenters extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Center::class);
    }

    public function openCenter(
        int $centerId,
        ActiveCenterSwitchService $switchService,
    ): void {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $center = Center::query()->findOrFail($centerId);
        $this->authorize('view', $center);

        $switchService->switch($user, $centerId);

        $this->redirect(route('dashboard'), navigate: false);
    }

    /**
     * @return Collection<int, Center>
     */
    #[Computed]
    public function centers()
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        $centers = app(CenterService::class)->listForOrganization($user);
        $query = trim(mb_strtolower($this->search));

        if ($query === '') {
            return $centers;
        }

        return $centers->filter(function (Center $center) use ($query): bool {
            $haystack = mb_strtolower(implode(' ', array_filter([
                $center->name,
                $center->code,
                $center->city,
                $center->region,
                $center->address,
            ])));

            return str_contains($haystack, $query);
        })->values();
    }

    public function render(CenterService $centerService): View
    {
        return view('livewire.centers.manage-centers', [
            'locationLabel' => static fn (Center $center): string => $centerService->locationLabel($center),
        ])->title(__('center.manage.title'));
    }
}
