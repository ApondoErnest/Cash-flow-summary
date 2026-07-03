<x-ui.page wide class="mf-settings-security">
    <header class="space-y-2">
        <flux:heading size="xl" class="font-display text-text-heading!">
            {{ __('settings.security.title') }}
        </flux:heading>
        <flux:text class="text-text-muted!">
            {{ __('settings.security.description') }}
        </flux:text>
    </header>

    <x-admin.settings-shell-notice />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('settings.security.password_policy_title')">
            <flux:text class="text-text-muted!">
                {{ __('settings.security.password_policy_description') }}
            </flux:text>

            <ul class="mt-4 space-y-2">
                @foreach ($this->passwordPolicySummary as $rule)
                    <li class="flex items-start gap-2 text-sm text-text-heading">
                        <flux:icon icon="check-circle" variant="solid" class="mt-0.5 size-4 shrink-0 text-emerald-brand" />
                        <span>{{ $rule }}</span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        <x-ui.card :title="__('settings.security.session_title')">
            <flux:text class="text-text-muted!">
                {{ __('settings.security.session_description') }}
            </flux:text>

            <div class="mt-4 flex items-baseline gap-2">
                <span class="tabular-money text-3xl font-semibold text-text-heading">
                    {{ $this->sessionTimeoutMinutes }}
                </span>
                <span class="text-sm text-text-muted">{{ __('settings.security.session_minutes') }}</span>
            </div>
        </x-ui.card>
    </div>

    <x-ui.card :title="__('settings.security.two_factor_title')">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <flux:text class="text-text-muted!">
                    {{ __('settings.security.two_factor_description') }}
                </flux:text>

                @if ($this->ownerHasTwoFactorEnabled)
                    <x-ui.status-badge status="success" icon="shield-check">
                        {{ __('settings.security.two_factor_enabled') }}
                    </x-ui.status-badge>
                @else
                    <x-ui.status-badge status="warning" icon="exclamation-triangle">
                        {{ __('settings.security.two_factor_disabled') }}
                    </x-ui.status-badge>
                @endif
            </div>

            <x-ui.button
                variant="{{ $this->ownerHasTwoFactorEnabled ? 'secondary' : 'primary' }}"
                icon="shield-check"
                href="{{ route('two-factor.setup') }}"
                class="shrink-0"
            >
                {{ $this->ownerHasTwoFactorEnabled
                    ? __('settings.security.manage_two_factor')
                    : __('settings.security.setup_two_factor') }}
            </x-ui.button>
        </div>
    </x-ui.card>
</x-ui.page>
