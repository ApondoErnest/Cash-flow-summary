<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Livewire;

use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Services\ImportResultService;
use App\Modules\CsvImports\Support\ImportResultData;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Support\Auth\RoleName;
use App\Support\Downloads\FileDownloadUrlService;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ImportResultPage extends Component
{
    public Import $import;

    public function mount(Import $import): void
    {
        $this->import = $import->load(['center', 'dayComparisons', 'whatsappMessages', 'importVerification']);
    }

    #[Computed]
    public function result(): ImportResultData
    {
        return app(ImportResultService::class)->build($this->import, auth()->user());
    }

    #[Computed]
    public function isStaffView(): bool
    {
        return auth()->user()?->isCenterStaff() === true;
    }

    #[Computed]
    public function isManagerView(): bool
    {
        return auth()->user()?->hasRole(RoleName::CenterManager) === true;
    }

    #[Computed]
    public function isCorrectionSubmission(): bool
    {
        return $this->import->import_mode === ImportMode::Correction;
    }

    #[Computed]
    public function importErrorDownloadUrl(): ?string
    {
        if ($this->import->invalid_count <= 0) {
            return null;
        }

        return app(FileDownloadUrlService::class)->importErrors($this->import);
    }

    public function render(): View
    {
        return view('livewire.csv-imports.import-result')
            ->title(__('csv_import.result.page_title'));
    }
}
