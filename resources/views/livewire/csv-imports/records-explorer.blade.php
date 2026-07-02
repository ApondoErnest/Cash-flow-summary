<x-ui.page wide class="mf-records-explorer">
    <header class="space-y-4">
        <div class="space-y-2">
            <flux:heading size="xl" class="font-display text-text-heading!">
                {{ __('records.title') }}
            </flux:heading>
            <flux:text class="text-text-muted!">
                {{ $this->pageDescription }}
            </flux:text>
        </div>

        <div class="mf-records-explorer-center rounded-lg border border-slate-200/80 bg-white/70 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ $this->centerBannerLabel }}</p>
            <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->centerName }}</p>
        </div>
    </header>

    @if ($this->selectedRecord)
        <x-ui.card compact :title="__('records.detail_title')" class="mf-records-explorer-detail">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('records.columns.registration_date') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedRecord->registrationDate }} {{ $this->selectedRecord->registrationTime }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('records.columns.completion_date') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedRecord->completionDate ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('records.columns.customer') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedRecord->customerName }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('records.columns.licence_plate') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedRecord->licencePlate }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('records.columns.category') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedRecord->categoryCode }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('records.columns.inspection_type') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedRecord->inspectionTypeCode }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('records.columns.ht') }}</p>
                        <p class="mt-1 tabular-money text-sm font-medium text-text-heading">{{ $this->selectedRecord->netAmount }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('records.columns.vat') }}</p>
                        <p class="mt-1 tabular-money text-sm font-medium text-text-heading">{{ $this->selectedRecord->vatAmount }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('records.columns.ttc') }}</p>
                        <p class="mt-1 tabular-money text-sm font-semibold text-gold-brand">{{ $this->selectedRecord->grossAmount }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('records.columns.completion_status') }}</p>
                        <div class="mt-1">
                            <x-ui.status-badge :status="$this->selectedRecord->completionStatusVariant">
                                {{ $this->selectedRecord->completionStatusLabel }}
                            </x-ui.status-badge>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('records.columns.financial_status') }}</p>
                        <div class="mt-1">
                            <x-ui.status-badge :status="$this->selectedRecord->financialStatusVariant">
                                {{ $this->selectedRecord->financialStatusLabel }}
                            </x-ui.status-badge>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('records.columns.first_seen_at') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedRecord->firstSeenAt ?? '—' }}</p>
                    </div>
                </div>

                <flux:button variant="ghost" size="sm" wire:click="clearSelection" class="shrink-0">
                    {{ __('records.close_detail') }}
                </flux:button>
            </div>

            @if ($this->selectedRecord->firstImportId !== null)
                <div class="mt-4 border-t border-slate-200/80 pt-4">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        :href="route('imports.show', $this->selectedRecord->firstImportId)"
                        wire:navigate
                    >
                        {{ __('records.view_first_import', ['filename' => $this->selectedRecord->firstImportFilename ?? '']) }}
                    </flux:button>
                </div>
            @endif
        </x-ui.card>
    @endif

    <x-ui.table-panel
        :title="__('records.table_title')"
        :description="__('records.table_description')"
    >
        <x-slot:filters>
            <x-ui.filter-bar>
                <x-ui.filter-field :label="__('ui.filter.search')" :span="4">
                    <flux:input
                        wire:model.live.debounce.200ms="search"
                        icon="magnifying-glass"
                        :placeholder="__('records.search_placeholder')"
                        autocomplete="off"
                        class="w-full"
                    />
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.completion')" :span="2">
                    <flux:select wire:model.live="completionFilter" class="w-full">
                        <flux:select.option value="">{{ __('records.filters.all_completion') }}</flux:select.option>
                        @foreach ($this->completionOptions as $status)
                            @php($badge = \App\Modules\CsvImports\Support\RecordStatusPresenter::completion($status))
                            <flux:select.option value="{{ $status->value }}">{{ $badge['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.financial')" :span="2">
                    <flux:select wire:model.live="financialFilter" class="w-full">
                        <flux:select.option value="">{{ __('records.filters.all_financial') }}</flux:select.option>
                        @foreach ($this->financialOptions as $status)
                            @php($badge = \App\Modules\CsvImports\Support\RecordStatusPresenter::financial($status))
                            <flux:select.option value="{{ $status->value }}">{{ $badge['label'] }}</flux:select.option>
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

        @if ($this->rows === [])
            <flux:text class="text-text-muted!">{{ __('records.empty') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('records.columns.registration_date') }}</flux:table.column>
                    <flux:table.column>{{ __('records.columns.customer') }}</flux:table.column>
                    <flux:table.column>{{ __('records.columns.licence_plate') }}</flux:table.column>
                    <flux:table.column>{{ __('records.columns.category') }}</flux:table.column>
                    <flux:table.column>{{ __('records.columns.inspection_type') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('records.columns.ttc') }}</flux:table.column>
                    <flux:table.column>{{ __('records.columns.completion_status') }}</flux:table.column>
                    <flux:table.column>{{ __('records.columns.financial_status') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('records.columns.actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->rows as $row)
                        <flux:table.row wire:key="record-row-{{ $row->id }}">
                            <flux:table.cell>
                                <span class="block">{{ $row->registrationDate }}</span>
                                <span class="text-xs text-text-muted">{{ $row->registrationTime }}</span>
                            </flux:table.cell>
                            <flux:table.cell class="font-medium">{{ $row->customerName }}</flux:table.cell>
                            <flux:table.cell>{{ $row->licencePlate }}</flux:table.cell>
                            <flux:table.cell>{{ $row->categoryCode }}</flux:table.cell>
                            <flux:table.cell>{{ $row->inspectionTypeCode }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-money font-medium">{{ $row->grossAmount }}</flux:table.cell>
                            <flux:table.cell>
                                <x-ui.status-badge :status="$row->completionStatusVariant">{{ $row->completionStatusLabel }}</x-ui.status-badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <x-ui.status-badge :status="$row->financialStatusVariant">{{ $row->financialStatusLabel }}</x-ui.status-badge>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" wire:click="selectRecord({{ $row->id }})">
                                    {{ __('records.view_detail') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="mt-4">
                {{ $this->records->links() }}
            </div>
        @endif
    </x-ui.table-panel>
</x-ui.page>
