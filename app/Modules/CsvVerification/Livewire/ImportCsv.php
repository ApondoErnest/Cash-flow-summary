<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Livewire;

use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Support\Auth\RoleName;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ImportCsv extends Component
{
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

    public function render(): View
    {
        return view('livewire.csv-verification.import-csv')
            ->title(__('csv_verification.card.page_title'));
    }
}
