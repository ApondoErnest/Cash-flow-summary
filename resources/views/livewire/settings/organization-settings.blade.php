<x-ui.page wide class="mf-settings-organization">
    <header class="space-y-2">
        <flux:heading size="xl" class="font-display text-text-heading!">
            {{ __('settings.organization.title') }}
        </flux:heading>
        <flux:text class="text-text-muted!">
            {{ __('settings.organization.description') }}
        </flux:text>
    </header>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('status') }}
        </flux:callout>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card
            :label="__('settings.organization.stats.currency')"
            :value="$organization->currency"
        />
        <x-ui.stat-card
            :label="__('settings.organization.stats.timezone')"
            :value="$organization->timezone"
        />
        <x-ui.stat-card
            :label="__('settings.organization.stats.language')"
            :value="strtoupper($defaultLanguage)"
        />
        <x-ui.stat-card
            :label="__('settings.organization.stats.status')"
            :value="$organization->is_active ? __('settings.organization.status.active') : __('settings.organization.status.inactive')"
            :accent="$organization->is_active"
        />
    </div>

    <x-ui.card :title="__('settings.organization.profile_title')">
        <form wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('settings.organization.fields.name') }}</flux:label>
                    <flux:input wire:model="name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('settings.organization.fields.code') }}</flux:label>
                    <flux:input wire:model="code" class="uppercase" />
                    <flux:description>{{ __('settings.organization.fields.code_help') }}</flux:description>
                    <flux:error name="code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('settings.organization.fields.default_language') }}</flux:label>
                    <flux:select wire:model.live="defaultLanguage">
                        <flux:select.option value="fr">{{ __('settings.organization.languages.fr') }}</flux:select.option>
                        <flux:select.option value="en">{{ __('settings.organization.languages.en') }}</flux:select.option>
                    </flux:select>
                    <flux:description>{{ __('settings.organization.fields.default_language_help') }}</flux:description>
                    <flux:error name="defaultLanguage" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('settings.organization.fields.currency') }}</flux:label>
                    <flux:input :value="$organization->currency" disabled />
                    <flux:description>{{ __('settings.organization.fields.readonly_help') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('settings.organization.fields.timezone') }}</flux:label>
                    <flux:input :value="$organization->timezone" disabled />
                    <flux:description>{{ __('settings.organization.fields.readonly_help') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('settings.organization.fields.contact_email') }}</flux:label>
                    <flux:input type="email" wire:model="contactEmail" />
                    <flux:error name="contactEmail" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('settings.organization.fields.contact_phone') }}</flux:label>
                    <flux:input type="tel" wire:model="contactPhone" />
                    <flux:error name="contactPhone" />
                </flux:field>
            </div>

            <div class="flex justify-end border-t border-slate-200 pt-4">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">{{ __('settings.common.save_changes') }}</span>
                    <span wire:loading wire:target="save">{{ __('settings.organization.saving') }}</span>
                </flux:button>
            </div>
        </form>
    </x-ui.card>
</x-ui.page>
