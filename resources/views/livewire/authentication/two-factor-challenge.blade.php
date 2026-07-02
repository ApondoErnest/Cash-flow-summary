<div class="mf-login-page relative flex min-h-dvh flex-col lg:flex-row">
    <x-authentication.guest-logout />

    <x-authentication.brand-panel
        :heading="__('two_factor.challenge_title')"
        :description="config('app.name')"
    />

    <main class="mf-login-main relative flex flex-1 items-center justify-center px-6 py-10 sm:px-10 lg:px-14">
        <div class="mf-login-form-panel w-full max-w-md rounded-2xl p-8 sm:p-10" data-mf-two-factor-challenge>
            <header class="mb-8">
                <flux:heading size="lg" class="font-display text-text-heading!">
                    {{ __('two_factor.challenge_heading') }}
                </flux:heading>
                <flux:text class="mt-2 text-text-muted!">
                    {{ $useRecoveryCode ? __('two_factor.recovery_prompt') : __('two_factor.challenge_prompt') }}
                </flux:text>
            </header>

            <form wire:submit="verify" class="mf-login-form space-y-5">
                <flux:field>
                    <flux:label>
                        {{ $useRecoveryCode ? __('two_factor.recovery_code') : __('two_factor.code') }}
                    </flux:label>
                    <flux:input
                        wire:model="code"
                        inputmode="{{ $useRecoveryCode ? 'text' : 'numeric' }}"
                        autocomplete="one-time-code"
                        required
                        autofocus
                    />
                    <flux:error name="code" />
                </flux:field>

                <x-ui.button variant="primary" type="submit" class="mf-login-submit w-full justify-center" wire:loading.attr="disabled">
                    {{ __('two_factor.verify') }}
                </x-ui.button>
            </form>

            <button
                type="button"
                wire:click="$toggle('useRecoveryCode')"
                class="mt-6 text-sm font-medium text-emerald-brand hover:text-emerald-brand/80"
            >
                {{ $useRecoveryCode ? __('two_factor.use_authenticator_code') : __('two_factor.use_recovery_code') }}
            </button>
        </div>
    </main>
</div>
