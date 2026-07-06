<x-ui.page wide class="mf-settings-whatsapp">
    <header class="space-y-2">
        <flux:heading size="xl" class="font-display text-text-heading!">
            {{ __('settings.whatsapp.title') }}
        </flux:heading>
        <flux:text class="text-text-muted!">
            {{ __('settings.whatsapp.description') }}
        </flux:text>
    </header>

    @if ($isOutboundConfigured && $isWebhookConfigured)
        <flux:callout variant="success" icon="check-circle">
            {{ __('settings.whatsapp.configured_with_webhooks_notice') }}
        </flux:callout>
    @elseif ($isOutboundConfigured)
        <flux:callout variant="success" icon="check-circle">
            {{ __('settings.whatsapp.outbound_configured_notice') }}
        </flux:callout>
    @else
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('settings.whatsapp.incomplete_notice') }}
        </flux:callout>
    @endif

    <x-ui.card :title="__('settings.whatsapp.notifications_title')">
        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('settings.whatsapp.fields.owner_phone') }}</flux:label>
                <flux:input
                    type="tel"
                    wire:model="ownerPhone"
                    :placeholder="__('settings.whatsapp.placeholders.owner_phone')"
                />
                <flux:description>{{ __('settings.whatsapp.fields.owner_phone_help') }}</flux:description>
                <flux:error name="ownerPhone" />
            </flux:field>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('settings.whatsapp.fields.phone_number_id') }}</flux:label>
                    <flux:input
                        wire:model="phoneNumberId"
                        :placeholder="__('settings.whatsapp.placeholders.phone_number_id')"
                    />
                    <flux:error name="phoneNumberId" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('settings.whatsapp.fields.access_token') }}</flux:label>
                    <flux:input
                        type="password"
                        wire:model="accessToken"
                        :placeholder="$accessTokenConfigured
                            ? __('settings.whatsapp.placeholders.access_token_configured')
                            : __('settings.whatsapp.placeholders.access_token')"
                    />
                    <flux:description>
                        @if ($accessTokenConfigured)
                            {{ __('settings.whatsapp.fields.access_token_configured_help') }}
                        @else
                            {{ __('settings.whatsapp.fields.access_token_help') }}
                        @endif
                    </flux:description>
                    <flux:error name="accessToken" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('settings.whatsapp.fields.webhook_verify_token') }}</flux:label>
                    <flux:input
                        type="password"
                        wire:model="webhookVerifyToken"
                        :placeholder="$webhookVerifyTokenConfigured
                            ? __('settings.whatsapp.placeholders.webhook_verify_token_configured')
                            : __('settings.whatsapp.placeholders.webhook_verify_token')"
                    />
                    <flux:description>
                        @if ($webhookVerifyTokenConfigured)
                            {{ __('settings.whatsapp.fields.webhook_verify_token_configured_help') }}
                        @else
                            {{ __('settings.whatsapp.fields.webhook_verify_token_help') }}
                        @endif
                    </flux:description>
                    <flux:error name="webhookVerifyToken" />
                </flux:field>
            </div>

            @if ($isOutboundConfigured && ! $isWebhookConfigured)
                <flux:callout variant="info" icon="information-circle">
                    {{ __('settings.whatsapp.webhook_optional_notice') }}
                </flux:callout>
            @endif

            @if ($testMessageFeedback)
                <flux:callout variant="success" icon="check-circle">
                    {{ $testMessageFeedback }}
                </flux:callout>
            @endif

            @error('testMessage')
                <flux:callout variant="danger" icon="x-circle">
                    {{ $message }}
                </flux:callout>
            @enderror

            <div class="flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <flux:text class="text-sm text-text-muted!">
                    {{ __('settings.whatsapp.test_help') }}
                </flux:text>

                <x-ui.button
                    variant="secondary"
                    icon="paper-airplane"
                    type="button"
                    wire:click="sendTestMessage"
                    wire:loading.attr="disabled"
                    wire:target="sendTestMessage"
                    :disabled="! $isOutboundConfigured"
                >
                    <span wire:loading.remove wire:target="sendTestMessage">{{ __('settings.whatsapp.test_send') }}</span>
                    <span wire:loading wire:target="sendTestMessage">{{ __('settings.whatsapp.test_sending') }}</span>
                </x-ui.button>
            </div>

            <div class="flex justify-end border-t border-slate-200 pt-4">
                <x-ui.button
                    variant="primary"
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                >
                    <span wire:loading.remove wire:target="save">{{ __('settings.common.save_changes') }}</span>
                    <span wire:loading wire:target="save">{{ __('settings.whatsapp.saving') }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-ui.page>
