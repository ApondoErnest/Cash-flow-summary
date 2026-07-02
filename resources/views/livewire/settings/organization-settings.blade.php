@php
    $contact = is_array($organization->contact_details) ? $organization->contact_details : [];
    $contactEmail = $contact['email'] ?? null;
    $contactPhone = $contact['phone'] ?? null;
    $notSet = __('settings.common.not_set');
@endphp

<x-ui.page wide class="mf-settings-organization">
    <header class="space-y-2">
        <flux:heading size="xl" class="font-display text-text-heading!">
            {{ __('settings.organization.title') }}
        </flux:heading>
        <flux:text class="text-text-muted!">
            {{ __('settings.organization.description') }}
        </flux:text>
    </header>

    <x-admin.settings-shell-notice />

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
            :value="strtoupper($organization->default_language)"
        />
        <x-ui.stat-card
            :label="__('settings.organization.stats.status')"
            :value="$organization->is_active ? __('settings.organization.status.active') : __('settings.organization.status.inactive')"
            :accent="$organization->is_active"
        />
    </div>

    <x-ui.card :title="__('settings.organization.profile_title')">
        <form class="space-y-6" onsubmit="return false;">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('settings.organization.fields.name') }}</flux:label>
                    <flux:input :value="$organization->name" disabled />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('settings.organization.fields.code') }}</flux:label>
                    <flux:input :value="$organization->code" disabled class="uppercase" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('settings.organization.fields.default_language') }}</flux:label>
                    <flux:input :value="strtoupper($organization->default_language)" disabled />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('settings.organization.fields.currency') }}</flux:label>
                    <flux:input :value="$organization->currency" disabled />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('settings.organization.fields.timezone') }}</flux:label>
                    <flux:input :value="$organization->timezone" disabled />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('settings.organization.fields.contact_email') }}</flux:label>
                    <flux:input :value="$contactEmail ?: $notSet" disabled />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('settings.organization.fields.contact_phone') }}</flux:label>
                    <flux:input :value="$contactPhone ?: $notSet" disabled />
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
