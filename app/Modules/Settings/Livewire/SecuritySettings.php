<?php

declare(strict_types=1);

namespace App\Modules\Settings\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SecuritySettings extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->isOwner(), 403, __('center.owner_only'));
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function passwordPolicySummary(): array
    {
        $rules = [];

        $rules[] = __('settings.security.password_rules.min_length', [
            'count' => (int) config('auth_security.password.min_length', 12),
        ]);

        if (config('auth_security.password.require_mixed_case', true)) {
            $rules[] = __('settings.security.password_rules.mixed_case');
        }

        if (config('auth_security.password.require_numbers', true)) {
            $rules[] = __('settings.security.password_rules.numbers');
        }

        if (config('auth_security.password.require_symbols', true)) {
            $rules[] = __('settings.security.password_rules.symbols');
        }

        return $rules;
    }

    #[Computed]
    public function sessionTimeoutMinutes(): int
    {
        return (int) config('auth_security.session.timeout_minutes', 120);
    }

    #[Computed]
    public function ownerHasTwoFactorEnabled(): bool
    {
        return auth()->user()?->hasTwoFactorEnabled() ?? false;
    }

    public function render(): View
    {
        return view('livewire.settings.security-settings');
    }
}
