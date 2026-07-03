<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Livewire;

use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Services\ImportDetailService;
use App\Modules\CsvImports\Support\ImportDetailData;
use App\Support\Auth\RoleName;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ImportDetail extends Component
{
    public Import $import;

    public function mount(Import $import): void
    {
        $this->import = $import->load('center:id,name');
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
    public function centerName(): string
    {
        return $this->import->center?->name
            ?? auth()->user()?->center?->name
            ?? '—';
    }

    #[Computed]
    public function detail(): ImportDetailData
    {
        return app(ImportDetailService::class)->build($this->import);
    }

    public function render(): View
    {
        return view('livewire.csv-imports.import-detail')
            ->title(__('csv_import.detail.page_title', ['filename' => $this->import->original_filename]));
    }
}
