<x-ui.page wide class="mf-anomaly-list">
    <header class="space-y-4">
        <div class="space-y-2">
            <flux:heading size="xl" class="font-display text-text-heading!">
                {{ __('anomalies.title') }}
            </flux:heading>
            <flux:text class="text-text-muted!">
                {{ __('anomalies.description') }}
            </flux:text>
        </div>

        <div class="mf-anomaly-list-center rounded-lg border border-slate-200/80 bg-white/70 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('anomalies.center_label') }}</p>
            <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->centerName }}</p>
        </div>
    </header>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">
            {{ session('status') }}
        </flux:callout>
    @endif

    @if ($this->selectedAnomaly)
        <x-ui.card compact :title="__('anomalies.detail_title')" class="mf-anomaly-detail">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('anomalies.columns.type') }}</p>
                        <div class="mt-1">
                            <x-ui.status-badge :status="$this->selectedAnomaly->typeVariant">{{ $this->selectedAnomaly->typeLabel }}</x-ui.status-badge>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('anomalies.columns.status') }}</p>
                        <div class="mt-1">
                            <x-ui.status-badge :status="$this->selectedAnomaly->resolutionVariant">{{ $this->selectedAnomaly->resolutionLabel }}</x-ui.status-badge>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('anomalies.detail.detected_at') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedAnomaly->detectedAt }}</p>
                    </div>
                    @if ($this->selectedAnomaly->resolvedAt)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('anomalies.detail.resolved_at') }}</p>
                            <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedAnomaly->resolvedAt }}</p>
                        </div>
                    @endif
                    <div class="sm:col-span-2 lg:col-span-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('anomalies.columns.description') }}</p>
                        <p class="mt-1 text-sm text-text-heading">{{ $this->selectedAnomaly->description }}</p>
                    </div>
                </div>

                <flux:button variant="ghost" size="sm" wire:click="clearSelection" class="shrink-0">
                    {{ __('anomalies.close_detail') }}
                </flux:button>
            </div>

            @if ($this->selectedAnomaly->metadataRows !== [])
                <div class="mt-4 border-t border-slate-200/80 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('anomalies.metadata_title') }}</p>
                    <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($this->selectedAnomaly->metadataRows as $row)
                            <div>
                                <dt class="text-xs text-text-muted">{{ $row['label'] }}</dt>
                                <dd class="mt-1 text-sm font-medium text-text-heading">{{ $row['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-200/80 pt-4">
                @if ($this->selectedAnomaly->importId !== null)
                    <flux:button
                        variant="ghost"
                        size="sm"
                        :href="route('imports.show', $this->selectedAnomaly->importId)"
                        wire:navigate
                    >
                        {{ __('anomalies.view_import', ['filename' => $this->selectedAnomaly->importFilename ?? '']) }}
                    </flux:button>
                @endif

                @if ($this->selectedAnomaly->canResolve)
                    <flux:button variant="primary" size="sm" wire:click="resolve" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="resolve">{{ __('anomalies.mark_resolved') }}</span>
                        <span wire:loading wire:target="resolve">{{ __('anomalies.resolving') }}</span>
                    </flux:button>
                @endif
            </div>

            @error('resolve')
                <flux:callout variant="danger" icon="exclamation-circle" class="mt-4">
                    {{ $message }}
                </flux:callout>
            @enderror
        </x-ui.card>
    @endif

    <x-ui.table-panel
        :title="__('anomalies.table_title')"
        :description="__('anomalies.table_description')"
    >
        <x-slot:filters>
            <x-ui.filter-bar>
                <x-ui.filter-field :label="__('ui.filter.type')" :span="3">
                    <flux:select wire:model.live="typeFilter" class="w-full">
                        <flux:select.option value="">{{ __('anomalies.filters.all_types') }}</flux:select.option>
                        @foreach ($this->typeOptions as $type)
                            @php($badge = \App\Modules\Reports\Support\AnomalyStatusPresenter::typeBadge($type))
                            <flux:select.option value="{{ $type->value }}">{{ $badge['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.status')" :span="3">
                    <flux:select wire:model.live="resolutionFilter" class="w-full">
                        <flux:select.option value="">{{ __('anomalies.filters.all_resolutions') }}</flux:select.option>
                        <flux:select.option value="open">{{ __('anomalies.filters.open') }}</flux:select.option>
                        <flux:select.option value="resolved">{{ __('anomalies.filters.resolved') }}</flux:select.option>
                    </flux:select>
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.from')" :span="3">
                    <x-ui.date-picker wire:model.live="fromDate" />
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.to')" :span="3">
                    <x-ui.date-picker wire:model.live="toDate" />
                </x-ui.filter-field>
            </x-ui.filter-bar>
        </x-slot:filters>

        @if ($this->rows === [])
            <flux:text class="text-text-muted!">{{ __('anomalies.empty') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('anomalies.columns.type') }}</flux:table.column>
                    <flux:table.column>{{ __('anomalies.columns.description') }}</flux:table.column>
                    <flux:table.column>{{ __('anomalies.columns.status') }}</flux:table.column>
                    <flux:table.column>{{ __('anomalies.columns.detected_at') }}</flux:table.column>
                    <flux:table.column>{{ __('anomalies.columns.import') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('anomalies.columns.actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->rows as $row)
                        <flux:table.row wire:key="anomaly-row-{{ $row->id }}">
                            <flux:table.cell>
                                <x-ui.status-badge :status="$row->typeVariant">{{ $row->typeLabel }}</x-ui.status-badge>
                            </flux:table.cell>
                            <flux:table.cell class="max-w-xs truncate">{{ $row->description }}</flux:table.cell>
                            <flux:table.cell>
                                <x-ui.status-badge :status="$row->resolutionVariant">{{ $row->resolutionLabel }}</x-ui.status-badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $row->detectedAt }}</flux:table.cell>
                            <flux:table.cell>{{ $row->importFilename ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" wire:click="selectAnomaly({{ $row->id }})">
                                    {{ __('anomalies.view_detail') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="mt-4">
                {{ $this->anomalies->links() }}
            </div>
        @endif
    </x-ui.table-panel>
</x-ui.page>
