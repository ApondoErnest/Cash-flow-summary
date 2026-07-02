<x-ui.page class="max-w-2xl">
    <header class="space-y-2">
        <flux:heading size="xl" class="font-display text-text-heading!">
            {{ __('two_factor.setup_heading') }}
        </flux:heading>
        <flux:text class="text-text-muted!">
            {{ __('two_factor.setup_intro') }}
        </flux:text>
    </header>

    @if ($user?->hasTwoFactorEnabled() && ! $showRecoveryCodes)
        <x-ui.card>
            <flux:callout variant="success" icon="shield-check" class="mb-4">
                {{ __('two_factor.setup_enabled') }}
            </flux:callout>
            <flux:text class="text-text-muted!">
                {{ __('two_factor.setup_enabled_hint') }}
            </flux:text>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-ui.button variant="secondary" wire:click="disable" wire:confirm="{{ __('two_factor.disable') }}?">
                    {{ __('two_factor.disable') }}
                </x-ui.button>
                <flux:button variant="primary" :href="route('dashboard')" wire:navigate>
                    {{ __('two_factor.continue') }}
                </flux:button>
            </div>
        </x-ui.card>
    @elseif ($showRecoveryCodes)
        <x-ui.card :title="__('two_factor.recovery_codes_heading')">
            <flux:text class="mb-4 text-text-muted!">
                {{ __('two_factor.recovery_codes_hint') }}
            </flux:text>

            <ul class="grid grid-cols-1 gap-2 rounded-lg border border-slate-200 bg-slate-50/80 p-4 sm:grid-cols-2">
                @foreach ($recoveryCodes as $recoveryCode)
                    <li class="font-mono text-sm text-text-heading">{{ $recoveryCode }}</li>
                @endforeach
            </ul>

            <div class="mt-6">
                <flux:button variant="primary" :href="route('dashboard')" wire:navigate>
                    {{ __('two_factor.continue') }}
                </flux:button>
            </div>
        </x-ui.card>
    @else
        <x-ui.card>
            <div class="grid gap-8 lg:grid-cols-[auto_1fr] lg:items-start">
                @if ($qrCode)
                    <div class="mx-auto rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:mx-0">
                        {!! $qrCode !!}
                    </div>
                @endif

                <div class="space-y-5">
                    <div>
                        <p class="text-sm font-medium text-text-heading">{{ __('two_factor.manual_entry') }}</p>
                        <p class="mt-2 break-all rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2 font-mono text-sm text-text-body">
                            {{ $pendingSecret }}
                        </p>
                    </div>

                    <form wire:submit="confirm" class="mf-login-form space-y-5">
                        <flux:field>
                            <flux:label>{{ __('two_factor.code') }}</flux:label>
                            <flux:input
                                wire:model="confirmationCode"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                required
                            />
                            <flux:error name="confirmationCode" />
                        </flux:field>

                        <x-ui.button variant="primary" type="submit" class="w-full justify-center sm:w-auto">
                            {{ __('two_factor.confirm_enable') }}
                        </x-ui.button>
                    </form>
                </div>
            </div>
        </x-ui.card>
    @endif
</x-ui.page>
