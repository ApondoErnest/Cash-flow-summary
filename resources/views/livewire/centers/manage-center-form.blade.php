<x-ui.page>
    <header class="mf-page-header">
        <x-ui.back-link :href="route('centers.index')">
            {{ __('center.manage.back_to_list') }}
        </x-ui.back-link>

        <div class="mf-page-header__intro">
            <flux:heading size="xl" class="font-display text-text-heading!">
                {{ $isEditing ? __('center.manage.edit_title') : __('center.manage.create_title') }}
            </flux:heading>
            <flux:text class="text-text-muted!">
                {{ $isEditing ? __('center.manage.edit_description') : __('center.manage.create_description') }}
            </flux:text>
        </div>
    </header>

    <x-ui.card>
        <form wire:submit="save" class="mf-manage-center-form space-y-6">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('center.manage.fields.name') }}</flux:label>
                    <flux:input wire:model="name" required autocomplete="organization" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('center.manage.fields.code') }}</flux:label>
                    <flux:input wire:model="code" required class="uppercase" autocomplete="off" />
                    <flux:description>{{ __('center.manage.fields.code_help') }}</flux:description>
                    <flux:error name="code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('center.manage.fields.default_language') }}</flux:label>
                    <flux:select wire:model="default_language">
                        <flux:select.option value="fr">{{ __('center.manage.languages.fr') }}</flux:select.option>
                        <flux:select.option value="en">{{ __('center.manage.languages.en') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="default_language" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('center.manage.fields.city') }}</flux:label>
                    <flux:input wire:model="city" autocomplete="address-level2" />
                    <flux:error name="city" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('center.manage.fields.region') }}</flux:label>
                    <flux:input wire:model="region" autocomplete="address-level1" />
                    <flux:error name="region" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('center.manage.fields.address') }}</flux:label>
                    <flux:input wire:model="address" autocomplete="street-address" />
                    <flux:error name="address" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('center.manage.fields.phone') }}</flux:label>
                    <flux:input wire:model="phone" type="tel" autocomplete="tel" />
                    <flux:error name="phone" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('center.manage.fields.submission_deadline') }}</flux:label>
                    <flux:input wire:model="submission_deadline" type="time" step="60" />
                    <flux:description>{{ __('center.manage.fields.submission_deadline_help') }}</flux:description>
                    <flux:error name="submission_deadline" />
                </flux:field>
            </div>

            @if ($isEditing)
                <flux:field variant="inline">
                    <flux:switch wire:model="is_active" />
                    <flux:label>{{ __('center.manage.fields.is_active') }}</flux:label>
                    <flux:description>{{ __('center.manage.fields.is_active_help') }}</flux:description>
                </flux:field>
            @endif

            <flux:field variant="inline">
                <flux:checkbox wire:model="setAsDefault" />
                <flux:label>{{ __('center.manage.fields.set_as_default') }}</flux:label>
                <flux:description>{{ __('center.manage.fields.set_as_default_help') }}</flux:description>
            </flux:field>

            <div class="mf-form-actions">
                <x-ui.button
                    variant="primary"
                    type="submit"
                    icon="{{ $isEditing ? 'check-circle' : 'plus-circle' }}"
                    wire:loading.attr="disabled"
                    wire:target="save"
                >
                    <span wire:loading.remove wire:target="save">
                        {{ $isEditing ? __('center.manage.save') : __('center.manage.create') }}
                    </span>
                    <span wire:loading wire:target="save">{{ __('center.manage.saving') }}</span>
                </x-ui.button>

                <x-ui.button variant="secondary" href="{{ route('centers.index') }}">
                    {{ __('center.manage.cancel') }}
                </x-ui.button>

                @if ($isEditing)
                    <x-ui.button
                        variant="secondary"
                        icon="calendar-days"
                        href="{{ route('centers.calendar', $center) }}"
                    >
                        {{ __('center.manage.actions.calendar') }}
                    </x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>
</x-ui.page>
