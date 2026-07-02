<x-ui.page wide class="mf-settings-whatsapp">
    <header class="space-y-2">
        <flux:heading size="xl" class="font-display text-text-heading!">
            {{ __('settings.whatsapp.title') }}
        </flux:heading>
        <flux:text class="text-text-muted!">
            {{ __('settings.whatsapp.description') }}
        </flux:text>
    </header>

    <x-admin.settings-shell-notice />

    <flux:callout variant="warning" icon="shield-exclamation" class="mf-settings-whatsapp-deployment">
        {{ __('settings.whatsapp.deployment_notice') }}
    </flux:callout>

    <x-ui.card :title="__('settings.whatsapp.notifications_title')">
        <form class="space-y-6" onsubmit="return false;">
            <flux:field>
                <flux:label>{{ __('settings.whatsapp.fields.owner_phone') }}</flux:label>
                <flux:input
                    type="tel"
                    :placeholder="__('settings.whatsapp.placeholders.owner_phone')"
                    disabled
                />
                <flux:description>{{ __('settings.whatsapp.fields.owner_phone_help') }}</flux:description>
            </flux:field>
        </form>
    </x-ui.card>

    <x-ui.card :title="__('settings.whatsapp.api_title')">
        <form class="space-y-6" onsubmit="return false;">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('settings.whatsapp.fields.phone_number_id') }}</flux:label>
                    <flux:input
                        :placeholder="__('settings.whatsapp.placeholders.phone_number_id')"
                        disabled
                    />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('settings.whatsapp.fields.access_token') }}</flux:label>
                    <flux:input
                        type="password"
                        :placeholder="__('settings.whatsapp.placeholders.access_token')"
                        disabled
                    />
                    <flux:description>{{ __('settings.whatsapp.fields.access_token_help') }}</flux:description>
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('settings.whatsapp.fields.webhook_verify_token') }}</flux:label>
                    <flux:input
                        :placeholder="__('settings.whatsapp.placeholders.webhook_verify_token')"
                        disabled
                    />
                </flux:field>
            </div>

            <div class="flex justify-end border-t border-slate-200 pt-4">
                <flux:button variant="primary" disabled>
                    {{ __('settings.common.save_changes') }}
                </flux:button>
            </div>
        </form>
    </x-ui.card>
</x-ui.page>
