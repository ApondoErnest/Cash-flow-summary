<?php

declare(strict_types=1);

namespace App\Modules\Settings\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class WhatsappSettings extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->isOwner(), 403, __('center.owner_only'));
    }

    public function render(): View
    {
        return view('livewire.settings.whatsapp-settings');
    }
}
