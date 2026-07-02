<?php

declare(strict_types=1);

namespace App\Modules\Settings\Livewire;

use App\Modules\Centers\Models\Organization;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class OrganizationSettings extends Component
{
    public Organization $organization;

    public function mount(): void
    {
        $owner = auth()->user();

        abort_unless($owner?->isOwner(), 403, __('center.owner_only'));

        $organization = $owner->organization;

        abort_if($organization === null, 404);

        $this->organization = $organization;
    }

    public function render(): View
    {
        return view('livewire.settings.organization-settings');
    }
}
