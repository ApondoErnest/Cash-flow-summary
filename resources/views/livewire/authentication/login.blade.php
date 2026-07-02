<div class="mf-login-page relative flex min-h-dvh flex-col lg:flex-row">
    <x-authentication.brand-panel />

    <main class="mf-login-main relative flex flex-1 items-center justify-center px-6 py-10 sm:px-10 lg:px-14">
        <div class="mf-login-form-panel w-full max-w-md rounded-2xl p-8 sm:p-10" data-mf-login-form>
            <header class="mb-8">
                @if ($authStatus === 'session_expired')
                    <flux:callout variant="warning" class="mb-6" icon="clock">
                        {{ __('auth.session_expired') }}
                    </flux:callout>
                @endif

                <flux:heading size="lg" class="font-display text-text-heading!">
                    {{ __('auth.sign_in') }}
                </flux:heading>
                <flux:text class="mt-2 text-text-muted!">
                    {{ __('auth.credentials_prompt') }}
                </flux:text>
            </header>

            <form wire:submit="authenticate" class="mf-login-form space-y-5">
                <flux:field>
                    <flux:label>{{ __('auth.username') }}</flux:label>
                    <flux:input
                        wire:model="username"
                        autocomplete="username"
                        placeholder="owner"
                        required
                    />
                    <flux:error name="username" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('auth.password') }}</flux:label>
                    <flux:input
                        wire:model="password"
                        type="password"
                        autocomplete="current-password"
                        viewable
                        required
                    />
                    <flux:error name="password" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('locale.language') }}</flux:label>
                    <flux:select wire:model.live="locale">
                        @foreach (App\Support\Locale\AppLocale::supported() as $code)
                            <flux:select.option value="{{ $code }}">
                                {{ __('locale.' . ($code === 'en' ? 'english' : 'french')) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:checkbox wire:model="remember" :label="__('auth.remember_me')" />

                <x-ui.button variant="primary" type="submit" class="mf-login-submit w-full justify-center" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="authenticate">{{ __('auth.sign_in') }}</span>
                    <span wire:loading wire:target="authenticate">{{ __('auth.signing_in') }}</span>
                </x-ui.button>
            </form>
        </div>
    </main>
</div>
