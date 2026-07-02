<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Livewire;

use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\CsvImports\Services\RecordExplorerService;
use App\Modules\CsvImports\Support\RecordDetailData;
use App\Modules\CsvImports\Support\RecordExplorerRow;
use App\Support\Auth\RoleName;
use App\Support\Center\CenterContextResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class RecordsExplorer extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'from', history: true)]
    public string $fromDate = '';

    #[Url(as: 'to', history: true)]
    public string $toDate = '';

    #[Url(as: 'completion', history: true)]
    public string $completionFilter = '';

    #[Url(as: 'financial', history: true)]
    public string $financialFilter = '';

    #[Url(as: 'record', history: true)]
    public ?int $selectedRecordId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->selectedRecordId = null;
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
        $this->selectedRecordId = null;
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
        $this->selectedRecordId = null;
    }

    public function updatedCompletionFilter(): void
    {
        $this->resetPage();
        $this->selectedRecordId = null;
    }

    public function updatedFinancialFilter(): void
    {
        $this->resetPage();
        $this->selectedRecordId = null;
    }

    public function selectRecord(int $recordId): void
    {
        $record = MasterCashFlowRecord::query()->withoutCenterScope()->find($recordId);
        $user = auth()->user();

        if ($record === null
            || $user === null
            || ! app(CenterContextResolver::class)->resourceBelongsToResolvedCenter($user, $record)) {
            $this->selectedRecordId = null;

            return;
        }

        $this->selectedRecordId = $record->id;
    }

    public function clearSelection(): void
    {
        $this->selectedRecordId = null;
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
    public function pageDescription(): string
    {
        if ($this->isManagerView) {
            return __('records.page.manager.subtitle', ['center' => $this->centerName]);
        }

        return __('records.description');
    }

    #[Computed]
    public function centerBannerLabel(): string
    {
        if ($this->isManagerView) {
            return __('records.page.manager.center_label');
        }

        return __('records.center_label');
    }

    /**
     * @return LengthAwarePaginator<int, MasterCashFlowRecord>
     */
    #[Computed]
    public function records()
    {
        return app(RecordExplorerService::class)->paginateForActiveCenter([
            'search' => $this->search,
            'from' => $this->fromDate !== '' ? $this->fromDate : null,
            'to' => $this->toDate !== '' ? $this->toDate : null,
            'completion' => $this->completionFilter !== '' ? $this->completionFilter : null,
            'financial' => $this->financialFilter !== '' ? $this->financialFilter : null,
        ]);
    }

    /**
     * @return list<RecordExplorerRow>
     */
    #[Computed]
    public function rows(): array
    {
        $service = app(RecordExplorerService::class);

        return $this->records
            ->getCollection()
            ->map(static fn (MasterCashFlowRecord $record): RecordExplorerRow => $service->toRow($record))
            ->all();
    }

    #[Computed]
    public function selectedRecord(): ?RecordDetailData
    {
        if ($this->selectedRecordId === null) {
            return null;
        }

        $record = MasterCashFlowRecord::query()->find($this->selectedRecordId);

        if ($record === null) {
            return null;
        }

        return app(RecordExplorerService::class)->toDetail($record);
    }

    /**
     * @return list<\App\Modules\CsvImports\Enums\CompletionStatus>
     */
    #[Computed]
    public function completionOptions()
    {
        return app(RecordExplorerService::class)->completionFilterOptions();
    }

    /**
     * @return list<\App\Modules\CsvImports\Enums\FinancialStatus>
     */
    #[Computed]
    public function financialOptions()
    {
        return app(RecordExplorerService::class)->financialFilterOptions();
    }

    public function render(): View
    {
        return view('livewire.csv-imports.records-explorer')
            ->title(__('records.page_title'));
    }
}
