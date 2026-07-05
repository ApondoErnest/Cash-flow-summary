<x-ui.page wide class="mf-audit-log-list">
    <header class="space-y-2">
        <flux:heading size="xl" class="font-display text-text-heading!">
            {{ __('audit.list.title') }}
        </flux:heading>
        <flux:text class="text-text-muted!">
            {{ __('audit.list.description') }}
        </flux:text>
    </header>

    @if ($this->selectedLog)
        <x-ui.card :title="__('audit.list.detail_title')" compact>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-text-muted">{{ __('audit.list.columns.event') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $eventLabel($this->selectedLog->event) }}</p>
                        <p class="font-mono text-xs text-text-muted">{{ $this->selectedLog->event }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-text-muted">{{ __('audit.list.columns.occurred_at') }}</p>
                        <p class="mt-1 text-sm text-text-heading">{{ $this->selectedLog->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-text-muted">{{ __('audit.list.columns.user') }}</p>
                        <p class="mt-1 text-sm text-text-heading">{{ $this->selectedLog->user?->name ?? __('audit.list.system_user') }}</p>
                        @if ($this->selectedLog->user)
                            <p class="font-mono text-xs text-text-muted">{{ $this->selectedLog->user->username }}</p>
                        @endif
                    </div>

                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-text-muted">{{ __('audit.list.columns.center') }}</p>
                        <p class="mt-1 text-sm text-text-heading">{{ $this->selectedLog->center?->name ?? '—' }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-text-muted">{{ __('audit.list.columns.resource') }}</p>
                        <p class="mt-1 text-sm text-text-heading">{{ $resourceLabel($this->selectedLog->resource_type, $this->selectedLog->resource_id) ?? '—' }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-text-muted">{{ __('audit.list.columns.ip_address') }}</p>
                        <p class="mt-1 font-mono text-sm text-text-heading">{{ $this->selectedLog->ip_address ?? '—' }}</p>
                    </div>
                </div>

                <flux:button variant="ghost" size="sm" wire:click="clearSelection" class="shrink-0">
                    {{ __('audit.list.close_detail') }}
                </flux:button>
            </div>

            @if ($this->selectedLog->reason)
                <div class="mt-4 rounded-lg border border-slate-200 bg-white/70 px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-text-muted">{{ __('audit.list.reason') }}</p>
                    <p class="mt-1 text-sm text-text-heading">{{ $this->selectedLog->reason }}</p>
                </div>
            @endif

            @if ($this->selectedLog->old_values || $this->selectedLog->new_values)
                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    @if ($this->selectedLog->old_values)
                        <div class="rounded-lg border border-slate-200 bg-white/70 px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-text-muted">{{ __('audit.list.old_values') }}</p>
                            <pre class="mt-2 overflow-x-auto text-xs text-text-heading">{{ json_encode($this->selectedLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif

                    @if ($this->selectedLog->new_values)
                        <div class="rounded-lg border border-slate-200 bg-white/70 px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-text-muted">{{ __('audit.list.new_values') }}</p>
                            <pre class="mt-2 overflow-x-auto text-xs text-text-heading">{{ json_encode($this->selectedLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif
                </div>
            @endif
        </x-ui.card>
    @endif

    <x-ui.table-panel
        :title="__('audit.list.table_title')"
        :description="__('audit.list.table_description')"
    >
        <x-slot:filters>
            <x-ui.filter-bar>
                <x-ui.filter-field :label="__('ui.filter.search')" :span="4">
                    <flux:input
                        wire:model.live.debounce.200ms="search"
                        icon="magnifying-glass"
                        :placeholder="__('audit.list.search_placeholder')"
                        autocomplete="off"
                        class="w-full"
                    />
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.center')" :span="2">
                    <flux:select wire:model.live="centerFilter" class="w-full">
                        <flux:select.option value="">{{ __('audit.list.filters.all_centers') }}</flux:select.option>
                        @foreach ($this->centers as $center)
                            <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.event')" :span="2">
                    <flux:select wire:model.live="eventFilter" class="w-full">
                        <flux:select.option value="">{{ __('audit.list.filters.all_events') }}</flux:select.option>
                        @foreach ($this->availableEvents as $event)
                            <flux:select.option value="{{ $event }}">{{ $eventLabel($event) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.from')" :span="2">
                    <x-ui.date-picker wire:model.live="fromDate" />
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.to')" :span="2">
                    <x-ui.date-picker wire:model.live="toDate" />
                </x-ui.filter-field>
            </x-ui.filter-bar>
        </x-slot:filters>

        @if ($this->logs->isEmpty())
            <div class="mf-audit-log-empty rounded-xl border border-dashed border-slate-200 bg-white/70 px-6 py-12 text-center">
                <flux:heading size="md" class="font-display text-text-heading!">
                    {{ __('audit.list.empty_title') }}
                </flux:heading>
                <flux:text class="mx-auto mt-2 max-w-md text-text-muted!">
                    {{ __('audit.list.empty_description') }}
                </flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('audit.list.columns.occurred_at') }}</flux:table.column>
                    <flux:table.column>{{ __('audit.list.columns.event') }}</flux:table.column>
                    <flux:table.column>{{ __('audit.list.columns.user') }}</flux:table.column>
                    <flux:table.column>{{ __('audit.list.columns.center') }}</flux:table.column>
                    <flux:table.column>{{ __('audit.list.columns.resource') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('audit.list.columns.actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->logs as $log)
                        <flux:table.row wire:key="audit-log-row-{{ $log->id }}">
                            <flux:table.cell class="whitespace-nowrap text-sm text-text-muted">
                                {{ $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="font-medium text-text-heading">{{ $eventLabel($log->event) }}</div>
                                <div class="font-mono text-xs text-text-muted">{{ $log->event }}</div>
                            </flux:table.cell>
                            <flux:table.cell class="text-text-muted">
                                {{ $log->user?->name ?? __('audit.list.system_user') }}
                            </flux:table.cell>
                            <flux:table.cell class="text-text-muted">
                                {{ $log->center?->name ?? '—' }}
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-xs text-text-muted">
                                {{ $resourceLabel($log->resource_type, $log->resource_id) ?? '—' }}
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="eye"
                                    wire:click="selectLog({{ $log->id }})"
                                >
                                    {{ __('audit.list.actions.view') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            @if ($this->logs->hasPages())
                <div class="mt-4 border-t border-slate-200 pt-4">
                    {{ $this->logs->links() }}
                </div>
            @endif
        @endif
    </x-ui.table-panel>
</x-ui.page>
