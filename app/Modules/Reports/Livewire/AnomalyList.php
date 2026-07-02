<?php

declare(strict_types=1);

namespace App\Modules\Reports\Livewire;

use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\Reports\Models\Anomaly;
use App\Modules\Reports\Services\AnomalyListService;
use App\Modules\Reports\Services\AnomalyService;
use App\Modules\Reports\Support\AnomalyDetailData;
use App\Modules\Reports\Support\AnomalyListRow;
use App\Support\Center\CenterContextResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class AnomalyList extends Component
{
    use WithPagination;

    #[Url(as: 'type', history: true)]
    public string $typeFilter = '';

    #[Url(as: 'resolution', history: true)]
    public string $resolutionFilter = '';

    #[Url(as: 'from', history: true)]
    public string $fromDate = '';

    #[Url(as: 'to', history: true)]
    public string $toDate = '';

    #[Url(as: 'anomaly', history: true)]
    public ?int $selectedAnomalyId = null;

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
        $this->selectedAnomalyId = null;
    }

    public function updatedResolutionFilter(): void
    {
        $this->resetPage();
        $this->selectedAnomalyId = null;
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
        $this->selectedAnomalyId = null;
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
        $this->selectedAnomalyId = null;
    }

    public function selectAnomaly(int $anomalyId): void
    {
        $anomaly = Anomaly::query()->withoutCenterScope()->find($anomalyId);
        $user = auth()->user();

        if ($anomaly === null
            || $user === null
            || ! app(CenterContextResolver::class)->resourceBelongsToResolvedCenter($user, $anomaly)) {
            $this->selectedAnomalyId = null;

            return;
        }

        $this->selectedAnomalyId = $anomaly->id;
    }

    public function clearSelection(): void
    {
        $this->selectedAnomalyId = null;
    }

    public function resolve(AnomalyService $anomalyService): void
    {
        $anomaly = $this->resolveSelectedAnomaly();

        if ($anomaly === null) {
            return;
        }

        try {
            $anomalyService->resolve(auth()->user(), $anomaly);
        } catch (AuthorizationException $exception) {
            $this->addError('resolve', $exception->getMessage());

            return;
        }

        $this->clearSelection();
        session()->flash('status', __('anomalies.resolve.success'));
    }

    #[Computed]
    public function centerName(): string
    {
        $user = auth()->user();
        $context = app(ActiveCenterContextService::class)->resolve($user);

        if ($context !== null) {
            return $context->centerName;
        }

        return $user?->center?->name ?? '—';
    }

    /**
     * @return LengthAwarePaginator<int, Anomaly>
     */
    #[Computed]
    public function anomalies()
    {
        return app(AnomalyListService::class)->paginateForActiveCenter([
            'type' => $this->typeFilter !== '' ? $this->typeFilter : null,
            'resolution' => $this->resolutionFilter !== '' ? $this->resolutionFilter : null,
            'from' => $this->fromDate !== '' ? $this->fromDate : null,
            'to' => $this->toDate !== '' ? $this->toDate : null,
        ]);
    }

    /**
     * @return list<AnomalyListRow>
     */
    #[Computed]
    public function rows(): array
    {
        $service = app(AnomalyListService::class);

        return $this->anomalies
            ->getCollection()
            ->map(static fn (Anomaly $anomaly): AnomalyListRow => $service->toListRow($anomaly))
            ->all();
    }

    #[Computed]
    public function selectedAnomaly(): ?AnomalyDetailData
    {
        if ($this->selectedAnomalyId === null) {
            return null;
        }

        $anomaly = Anomaly::query()->find($this->selectedAnomalyId);

        if ($anomaly === null) {
            return null;
        }

        $user = auth()->user();
        $canResolve = $user !== null
            && $anomaly->resolved_at === null
            && app(CenterContextResolver::class)->resourceBelongsToResolvedCenter($user, $anomaly);

        return app(AnomalyListService::class)->toDetail(
            $anomaly,
            $canResolve,
        );
    }

    /**
     * @return list<\App\Modules\Reports\Enums\AnomalyType>
     */
    #[Computed]
    public function typeOptions()
    {
        return app(AnomalyListService::class)->filterableTypes();
    }

    private function resolveSelectedAnomaly(): ?Anomaly
    {
        if ($this->selectedAnomalyId === null) {
            return null;
        }

        return Anomaly::query()->find($this->selectedAnomalyId);
    }

    public function render(): View
    {
        return view('livewire.reports.anomaly-list')
            ->title(__('anomalies.page_title'));
    }
}
