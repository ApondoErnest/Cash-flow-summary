<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Livewire;

use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Services\ImportListService;
use App\Modules\CsvImports\Support\ImportListRow;
use App\Support\Auth\RoleName;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class ImportList extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'status', history: true)]
    public string $statusFilter = '';

    #[Url(as: 'from', history: true)]
    public string $fromDate = '';

    #[Url(as: 'to', history: true)]
    public string $toDate = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
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

    #[Computed]
    public function isManagerView(): bool
    {
        return auth()->user()?->hasRole(RoleName::CenterManager) === true;
    }

    #[Computed]
    public function isCashierView(): bool
    {
        return auth()->user()?->hasRole(RoleName::Cashier) === true;
    }

    #[Computed]
    public function isStaffView(): bool
    {
        return $this->isManagerView || $this->isCashierView;
    }

    #[Computed]
    public function pageDescription(): string
    {
        if ($this->isManagerView) {
            return __('csv_import.page.manager.list.subtitle', ['center' => $this->centerName]);
        }

        if ($this->isCashierView) {
            return __('csv_import.page.cashier.list.subtitle_compact');
        }

        return __('csv_import.list.description');
    }

    #[Computed]
    public function centerBannerLabel(): string
    {
        if ($this->isStaffView) {
            return __('csv_import.page.staff.center_label');
        }

        return __('csv_import.list.center_label');
    }

    /**
     * @return LengthAwarePaginator<int, Import>
     */
    #[Computed]
    public function imports()
    {
        return app(ImportListService::class)->paginateForActiveCenter([
            'search' => $this->search,
            'status' => $this->statusFilter !== '' ? $this->statusFilter : null,
            'from' => $this->fromDate !== '' ? $this->fromDate : null,
            'to' => $this->toDate !== '' ? $this->toDate : null,
        ]);
    }

    /**
     * @return list<ImportListRow>
     */
    #[Computed]
    public function rows(): array
    {
        $service = app(ImportListService::class);

        return $this->imports
            ->getCollection()
            ->map(static fn (Import $import): ImportListRow => $service->toListRow($import))
            ->all();
    }

    /**
     * @return list<\App\Modules\CsvImports\Enums\ImportStatus>
     */
    #[Computed]
    public function statusOptions()
    {
        return app(ImportListService::class)->filterableStatuses();
    }

    public function render(): View
    {
        return view('livewire.csv-imports.import-list')
            ->title(__('csv_import.list.page_title'));
    }
}
