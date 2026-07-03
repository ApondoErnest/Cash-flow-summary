<x-ui.page wide class="mf-admin-mobile-page">
    <header class="mf-admin-mobile-header mf-manage-centers-header flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 space-y-2">
            <flux:heading size="xl" class="font-display text-text-heading!">
                {{ __('center.manage.title') }}
            </flux:heading>
            <flux:text class="text-text-muted!">
                {{ __('center.manage.description') }}
            </flux:text>
        </div>

        <x-ui.button
            variant="primary"
            icon="plus-circle"
            href="{{ route('centers.create') }}"
            class="max-md:w-full shrink-0"
        >
            {{ __('center.manage.create') }}
        </x-ui.button>
    </header>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('status') }}
        </flux:callout>
    @endif

    <x-ui.table-panel
        class="mf-admin-mobile-panel"
        :title="__('center.manage.table_title')"
        :description="__('center.manage.table_description')"
    >
        <x-slot:filters>
            <x-ui.filter-bar>
                <x-ui.filter-field :label="__('ui.filter.search')" :span="6">
                    <flux:input
                        wire:model.live.debounce.200ms="search"
                        icon="magnifying-glass"
                        :placeholder="__('center.manage.search_placeholder')"
                        autocomplete="off"
                        class="w-full"
                    />
                </x-ui.filter-field>
            </x-ui.filter-bar>
        </x-slot:filters>

        @if ($this->centers->isEmpty())
            <div class="mf-manage-centers-empty rounded-xl border border-dashed border-slate-200 bg-white/70 px-6 py-12 text-center">
                <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-midnight-navy/5 text-midnight-navy">
                    <flux:icon icon="building-office-2" variant="outline" class="size-7" />
                </div>

                <flux:heading size="md" class="font-display text-text-heading!">
                    {{ __('center.manage.empty_title') }}
                </flux:heading>
                <flux:text class="mx-auto mt-2 max-w-md text-text-muted!">
                    {{ __('center.manage.empty_description') }}
                </flux:text>

                <x-ui.button
                    variant="primary"
                    icon="plus-circle"
                    href="{{ route('centers.create') }}"
                    class="mt-6"
                >
                    {{ __('center.manage.create') }}
                </x-ui.button>
            </div>
        @else
            <x-ui.mobile-record-list :label="trans_choice('center.selection.center_count', $this->centers->count(), ['count' => $this->centers->count()])">
                @foreach ($this->centers as $center)
                    <x-ui.mobile-record-card
                        wire:key="center-mobile-{{ $center->id }}"
                        :title="$center->name"
                        :subtitle="$center->code"
                        icon="building-office-2"
                    >
                        <x-slot:aside>
                            @if ($center->is_active)
                                <x-ui.status-badge status="success" icon="check-circle">
                                    {{ __('center.manage.status.active') }}
                                </x-ui.status-badge>
                            @else
                                <x-ui.status-badge status="neutral" icon="minus-circle">
                                    {{ __('center.manage.status.inactive') }}
                                </x-ui.status-badge>
                            @endif
                        </x-slot:aside>

                        <x-slot:details>
                            <x-ui.mobile-record-detail :label="__('center.manage.columns.location')">
                                {{ $locationLabel($center) !== '' ? $locationLabel($center) : '—' }}
                            </x-ui.mobile-record-detail>
                            <x-ui.mobile-record-detail :label="__('center.manage.columns.users')">
                                {{ $center->active_users_count }}
                            </x-ui.mobile-record-detail>
                        </x-slot:details>

                        <x-slot:actions>
                            @if ($center->is_active)
                                <x-ui.mobile-record-action
                                    variant="primary"
                                    type="button"
                                    icon="arrow-right-circle"
                                    wire:click="openCenter({{ $center->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="openCenter"
                                >
                                    {{ __('center.manage.actions.open_center_mobile') }}
                                </x-ui.mobile-record-action>
                            @endif

                            <x-ui.mobile-record-action
                                variant="secondary"
                                icon="pencil-square"
                                :href="route('centers.edit', $center)"
                            >
                                {{ __('center.manage.actions.edit') }}
                            </x-ui.mobile-record-action>

                            <x-ui.mobile-record-action
                                variant="secondary"
                                icon="calendar-days"
                                :href="route('centers.calendar', $center)"
                            >
                                {{ __('center.manage.actions.calendar') }}
                            </x-ui.mobile-record-action>
                        </x-slot:actions>
                    </x-ui.mobile-record-card>
                @endforeach
            </x-ui.mobile-record-list>

            <div class="mf-manage-list-table hidden min-w-0 md:block">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('center.manage.columns.name') }}</flux:table.column>
                    <flux:table.column>{{ __('center.manage.columns.code') }}</flux:table.column>
                    <flux:table.column>{{ __('center.manage.columns.location') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('center.manage.columns.users') }}</flux:table.column>
                    <flux:table.column>{{ __('center.manage.columns.status') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('center.manage.columns.actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->centers as $center)
                        <flux:table.row wire:key="center-row-{{ $center->id }}">
                            <flux:table.cell class="font-medium text-text-heading">
                                {{ $center->name }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="font-mono text-sm">{{ $center->code }}</span>
                            </flux:table.cell>
                            <flux:table.cell class="text-text-muted">
                                {{ $locationLabel($center) !== '' ? $locationLabel($center) : '—' }}
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">
                                {{ $center->active_users_count }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($center->is_active)
                                    <x-ui.status-badge status="success" icon="check-circle">
                                        {{ __('center.manage.status.active') }}
                                    </x-ui.status-badge>
                                @else
                                    <x-ui.status-badge status="neutral" icon="minus-circle">
                                        {{ __('center.manage.status.inactive') }}
                                    </x-ui.status-badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="mf-manage-centers-actions inline-flex flex-wrap items-center justify-end gap-2">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="pencil-square"
                                        href="{{ route('centers.edit', $center) }}"
                                    >
                                        {{ __('center.manage.actions.edit') }}
                                    </flux:button>

                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="calendar-days"
                                        href="{{ route('centers.calendar', $center) }}"
                                    >
                                        {{ __('center.manage.actions.calendar') }}
                                    </flux:button>

                                    @if ($center->is_active)
                                        <flux:button
                                            variant="primary"
                                            size="sm"
                                            icon="arrow-right-circle"
                                            wire:click="openCenter({{ $center->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="openCenter"
                                            class="mf-btn-primary"
                                        >
                                            {{ __('center.manage.actions.open_center') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            </div>
        @endif
    </x-ui.table-panel>
</x-ui.page>
