<x-ui.page wide class="mf-daily-version-list">
    <header class="space-y-4">
        <div class="space-y-2">
            <flux:heading size="xl" class="font-display text-text-heading!">
                {{ __('daily_versions.list.title') }}
            </flux:heading>
            <flux:text class="text-text-muted!">
                {{ __('daily_versions.list.description') }}
            </flux:text>
        </div>

        <div class="mf-daily-version-list-center rounded-lg border border-slate-200/80 bg-white/70 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('daily_versions.list.center_label') }}</p>
            <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->centerName }}</p>
        </div>
    </header>

    @if ($this->selectedVersion)
        <x-ui.card compact :title="__('daily_versions.list.detail_title')" class="mf-daily-version-detail">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('daily_versions.list.columns.business_date') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedVersion->businessDate }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('daily_versions.list.columns.version') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">v{{ $this->selectedVersion->versionNumber }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('daily_versions.list.columns.status') }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <x-ui.status-badge :status="$this->selectedVersion->statusVariant">{{ $this->selectedVersion->statusLabel }}</x-ui.status-badge>
                            @if ($this->selectedVersion->isActiveSnapshot)
                                <x-ui.status-badge status="success">{{ __('daily_versions.list.active_snapshot') }}</x-ui.status-badge>
                            @endif
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('daily_versions.list.columns.record_count') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedVersion->recordCount }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('daily_versions.list.columns.ht') }}</p>
                        <p class="mt-1 tabular-money text-sm font-medium text-text-heading">{{ $this->selectedVersion->totalHt }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('daily_versions.list.columns.vat') }}</p>
                        <p class="mt-1 tabular-money text-sm font-medium text-text-heading">{{ $this->selectedVersion->totalVat }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('daily_versions.list.columns.ttc') }}</p>
                        <p class="mt-1 tabular-money text-sm font-semibold text-gold-brand">{{ $this->selectedVersion->totalTtc }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('daily_versions.list.columns.submitted_by') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedVersion->submittedByName ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('daily_versions.list.columns.approved_by') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedVersion->approvedByName ?? '—' }}</p>
                    </div>
                    @if ($this->selectedVersion->rejectedReason)
                        <div class="sm:col-span-2 lg:col-span-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('daily_versions.list.rejected_reason') }}</p>
                            <p class="mt-1 text-sm text-text-heading">{{ $this->selectedVersion->rejectedReason }}</p>
                        </div>
                    @endif
                </div>

                <flux:button variant="ghost" size="sm" wire:click="clearSelection" class="shrink-0">
                    {{ __('daily_versions.list.close_detail') }}
                </flux:button>
            </div>

            @if ($this->selectedVersion->importId !== null)
                <div class="mt-4 border-t border-slate-200/80 pt-4">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        :href="route('imports.show', $this->selectedVersion->importId)"
                        wire:navigate
                    >
                        {{ __('daily_versions.list.view_import', ['filename' => $this->selectedVersion->importFilename ?? '']) }}
                    </flux:button>
                </div>
            @endif
        </x-ui.card>
    @endif

    <x-ui.table-panel
        :title="__('daily_versions.list.table_title')"
        :description="__('daily_versions.list.table_description')"
    >
        <x-slot:filters>
            <x-ui.filter-bar>
                <x-ui.filter-field :label="__('ui.filter.status')" :span="4">
                    <flux:select wire:model.live="statusFilter" class="w-full">
                        <flux:select.option value="">{{ __('daily_versions.list.filters.all_statuses') }}</flux:select.option>
                        @foreach ($this->statusOptions as $status)
                            @php($badge = \App\Modules\DailyVersions\Support\DailyVersionStatusPresenter::badge($status))
                            <flux:select.option value="{{ $status->value }}">{{ $badge['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.from')" :span="4">
                    <x-ui.date-picker wire:model.live="fromDate" />
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.to')" :span="4">
                    <x-ui.date-picker wire:model.live="toDate" />
                </x-ui.filter-field>
            </x-ui.filter-bar>
        </x-slot:filters>

        @if ($this->rows === [])
            <flux:text class="text-text-muted!">{{ __('daily_versions.list.empty') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('daily_versions.list.columns.business_date') }}</flux:table.column>
                    <flux:table.column>{{ __('daily_versions.list.columns.version') }}</flux:table.column>
                    <flux:table.column>{{ __('daily_versions.list.columns.status') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('daily_versions.list.columns.record_count') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('daily_versions.list.columns.ttc') }}</flux:table.column>
                    <flux:table.column>{{ __('daily_versions.list.columns.submitted_by') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('daily_versions.list.columns.actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->rows as $row)
                        <flux:table.row wire:key="daily-version-row-{{ $row->id }}">
                            <flux:table.cell>{{ $row->businessDate }}</flux:table.cell>
                            <flux:table.cell>v{{ $row->versionNumber }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-ui.status-badge :status="$row->statusVariant">{{ $row->statusLabel }}</x-ui.status-badge>
                                    @if ($row->isActiveSnapshot)
                                        <x-ui.status-badge status="info">{{ __('daily_versions.list.active_snapshot') }}</x-ui.status-badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $row->recordCount }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-money font-medium">{{ $row->totalTtc }}</flux:table.cell>
                            <flux:table.cell>{{ $row->submittedByName ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" wire:click="selectVersion({{ $row->id }})">
                                    {{ __('daily_versions.list.view_detail') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="mt-4">
                {{ $this->versions->links() }}
            </div>
        @endif
    </x-ui.table-panel>
</x-ui.page>
