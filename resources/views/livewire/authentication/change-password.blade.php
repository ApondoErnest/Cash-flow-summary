<div class="mf-login-page relative flex min-h-dvh flex-col lg:flex-row">
    <x-authentication.guest-logout />

    <x-authentication.brand-panel
        :heading="__('password.change_title')"
        :description="config('app.name')"
    />

    <main class="mf-login-main relative flex flex-1 items-center justify-center px-6 py-10 sm:px-10 lg:px-14">
        <div class="mf-login-form-panel w-full max-w-md rounded-2xl p-8 sm:p-10" data-mf-change-password-form>
            <header class="mb-8">
                <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6">
                    {{ __('password.change_required') }}
                </flux:callout>

                <flux:heading size="lg" class="font-display text-text-heading!">
                    {{ __('password.change_heading') }}
                </flux:heading>
                <flux:text class="mt-2 text-text-muted!">
                    {{ __('password.change_prompt') }}
                </flux:text>
            </header>

            <form wire:submit="updatePassword" class="mf-login-form space-y-5">
                <flux:field>
                    <flux:label>{{ __('password.current_password') }}</flux:label>
                    <flux:input
                        wire:model="currentPassword"
                        type="password"
                        autocomplete="current-password"
                        viewable
                        required
                        autofocus
                    />
                    <flux:error name="currentPassword" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('password.new_password') }}</flux:label>
                    <flux:input
                        wire:model="password"
                        type="password"
                        autocomplete="new-password"
                        viewable
                        required
                    />
                    <flux:error name="password" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('password.confirm_password') }}</flux:label>
                    <flux:input
                        wire:model="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        viewable
                        required
                    />
                    <flux:error name="password_confirmation" />
                </flux:field>

                <flux:text class="text-sm text-text-muted!">
                    {{ __('password.policy_hint') }}
                </flux:text>

                <x-ui.button variant="primary" type="submit" class="mf-login-submit w-full justify-center" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="updatePassword">{{ __('password.update_password') }}</span>
                    <span wire:loading wire:target="updatePassword">{{ __('password.updating') }}</span>
                </x-ui.button>
            </form>
        </div>
    </main>
</div>
