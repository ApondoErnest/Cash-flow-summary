<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Livewire;

use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\DailyVersions\Services\DailyVersionListService;
use App\Modules\DailyVersions\Support\DailyVersionDetailData;
use App\Modules\DailyVersions\Support\DailyVersionListRow;
use App\Support\Center\CenterContextResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class DailyVersionList extends Component
{
    use WithPagination;

    #[Url(as: 'status', history: true)]
    public string $statusFilter = '';

    #[Url(as: 'from', history: true)]
    public string $fromDate = '';

    #[Url(as: 'to', history: true)]
    public string $toDate = '';

    #[Url(as: 'version', history: true)]
    public ?int $selectedVersionId = null;

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedVersionId = null;
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
        $this->selectedVersionId = null;
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
        $this->selectedVersionId = null;
    }

    public function selectVersion(int $versionId): void
    {
        $version = DailyVersion::query()->withoutCenterScope()->find($versionId);
        $user = auth()->user();

        if ($version === null
            || $user === null
            || ! app(CenterContextResolver::class)->resourceBelongsToResolvedCenter($user, $version)) {
            $this->selectedVersionId = null;

            return;
        }

        $this->selectedVersionId = $version->id;
    }

    public function clearSelection(): void
    {
        $this->selectedVersionId = null;
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
     * @return LengthAwarePaginator<int, DailyVersion>
     */
    #[Computed]
    public function versions()
    {
        return app(DailyVersionListService::class)->paginateForActiveCenter([
            'status' => $this->statusFilter !== '' ? $this->statusFilter : null,
            'from' => $this->fromDate !== '' ? $this->fromDate : null,
            'to' => $this->toDate !== '' ? $this->toDate : null,
        ]);
    }

    /**
     * @return list<DailyVersionListRow>
     */
    #[Computed]
    public function rows(): array
    {
        $service = app(DailyVersionListService::class);

        return $this->versions
            ->getCollection()
            ->map(static fn (DailyVersion $version): DailyVersionListRow => $service->toListRow($version))
            ->all();
    }

    #[Computed]
    public function selectedVersion(): ?DailyVersionDetailData
    {
        if ($this->selectedVersionId === null) {
            return null;
        }

        $version = DailyVersion::query()->find($this->selectedVersionId);

        if ($version === null) {
            return null;
        }

        return app(DailyVersionListService::class)->toDetail($version);
    }

    /**
     * @return list<\App\Modules\DailyVersions\Enums\DailyVersionStatus>
     */
    #[Computed]
    public function statusOptions()
    {
        return app(DailyVersionListService::class)->filterableStatuses();
    }

    public function render(): View
    {
        return view('livewire.daily-versions.daily-version-list')
            ->title(__('daily_versions.list.page_title'));
    }
}
