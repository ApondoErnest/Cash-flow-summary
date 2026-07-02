<x-ui.page wide class="mf-import-list">
    <header class="space-y-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <flux:heading size="xl" class="font-display text-text-heading!">
                    {{ __('csv_import.list.title') }}
                </flux:heading>
                <flux:text class="text-text-muted!">
                    {{ $this->pageDescription }}
                </flux:text>
            </div>

            <flux:button variant="primary" icon="arrow-up-tray" :href="route('imports.create')" wire:navigate class="mf-btn-primary shrink-0">
                {{ __('csv_import.list.import_csv') }}
            </flux:button>
        </div>

        <div class="mf-import-list-center rounded-lg border border-slate-200/80 bg-white/70 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ $this->centerBannerLabel }}</p>
            <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->centerName }}</p>
        </div>
    </header>

    <x-ui.table-panel
        :title="__('csv_import.list.table_title')"
        :description="__('csv_import.list.table_description')"
    >
        <x-slot:filters>
            <x-ui.filter-bar>
                <x-ui.filter-field :label="__('ui.filter.search')" :span="4">
                    <flux:input
                        wire:model.live.debounce.200ms="search"
                        icon="magnifying-glass"
                        :placeholder="__('csv_import.list.search_placeholder')"
                        autocomplete="off"
                        class="w-full"
                    />
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.status')" :span="2">
                    <flux:select wire:model.live="statusFilter" class="w-full">
                        <flux:select.option value="">{{ __('csv_import.list.filters.all_statuses') }}</flux:select.option>
                        @foreach ($this->statusOptions as $status)
                            @php($badge = \App\Modules\CsvImports\Support\ImportStatusPresenter::badge($status))
                            <flux:select.option value="{{ $status->value }}">{{ $badge['label'] }}</flux:select.option>
                        @endforeach
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
            <flux:text class="text-text-muted!">{{ __('csv_import.list.empty') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('csv_import.list.columns.date') }}</flux:table.column>
                    <flux:table.column>{{ __('csv_import.list.columns.file') }}</flux:table.column>
                    <flux:table.column>{{ __('csv_import.list.columns.mode') }}</flux:table.column>
                    <flux:table.column>{{ __('csv_import.list.columns.period') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('csv_import.list.columns.ttc') }}</flux:table.column>
                    <flux:table.column>{{ __('csv_import.list.columns.uploaded_by') }}</flux:table.column>
                    <flux:table.column>{{ __('csv_import.list.columns.status') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('csv_import.list.columns.actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->rows as $row)
                        <flux:table.row wire:key="import-list-row-{{ $row->id }}">
                            <flux:table.cell>{{ $row->importedAt }}</flux:table.cell>
                            <flux:table.cell class="font-medium">{{ $row->filename }}</flux:table.cell>
                            <flux:table.cell>{{ $row->importModeLabel }}</flux:table.cell>
                            <flux:table.cell>{{ $row->actualPeriod ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-money font-medium">{{ $row->totalTtc }}</flux:table.cell>
                            <flux:table.cell>{{ $row->uploadedByName }}</flux:table.cell>
                            <flux:table.cell>
                                <x-ui.status-badge :status="$row->statusVariant">{{ $row->statusLabel }}</x-ui.status-badge>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    :href="route('imports.show', $row->id)"
                                    wire:navigate
                                >
                                    {{ __('csv_import.list.view_detail') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="mt-4">
                {{ $this->imports->links() }}
            </div>
        @endif
    </x-ui.table-panel>
</x-ui.page>
