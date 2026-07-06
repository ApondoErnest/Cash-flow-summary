<x-ui.page wide>
    <header class="mf-owner-dashboard-header flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div class="min-w-0 space-y-2">
            <flux:heading size="xl" class="font-display text-text-heading!">
                {{ __('dashboard.owner.title', ['center' => $dashboard->centerName]) }}
            </flux:heading>
            <flux:text class="text-text-muted!">
                {{ __('dashboard.owner.subtitle', ['period' => $dashboard->periodLabel]) }}
            </flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-3">
        <div
            class="mf-dashboard-period-filter"
            x-data
            x-on:mousedown.capture="
                const select = $el.querySelector('select');
                if (! select || $event.target.tagName !== 'OPTION') return;
                if ($event.target.value === 'custom' && select.value === 'custom') {
                    $wire.openCustomPeriodModal();
                }
            "
        >
            <flux:select wire:model.live="period" class="min-w-40">
                @foreach ($periods as $periodOption)
                    <option value="{{ $periodOption->value }}">{{ $periodOption->label() }}</option>
                @endforeach
            </flux:select>
        </div>

            <x-ui.button
                variant="primary"
                icon="arrow-up-tray"
                href="{{ route('imports.create') }}"
                wire:navigate
            >
                {{ __('dashboard.actions.import_csv') }}
            </x-ui.button>

            <x-ui.button
                variant="secondary"
                icon="arrow-down-tray"
                href="{{ route('reports.index') }}"
                wire:navigate
            >
                {{ __('dashboard.actions.export') }}
            </x-ui.button>

            @if ($dashboard->lastImportAt)
                <flux:text class="text-sm text-text-muted!">
                    {{ __('dashboard.owner.last_import', ['datetime' => $dashboard->lastImportAt]) }}
                </flux:text>
            @endif
        </div>
    </header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card :label="__('reports.stats.total_ttc')" :value="$dashboard->totalTtc" :context="$dashboard->periodLabel" />
        <x-ui.stat-card :label="__('reports.stats.total_ht')" :value="$dashboard->totalHt" :context="$dashboard->periodLabel" />
        <x-ui.stat-card :label="__('reports.stats.total_vat')" :value="$dashboard->totalVat" :context="$dashboard->periodLabel" />
        <x-ui.stat-card
            :label="__('dashboard.stats.unique_records')"
            :value="(string) $dashboard->uniqueRecords"
            :context="__('dashboard.stats.active_snapshots')"
            :accent="true"
        />
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card :label="__('dashboard.stats.completed')" :value="(string) $dashboard->completedCount" />
        <x-ui.stat-card :label="__('dashboard.stats.unfinished')" :value="(string) $dashboard->unfinishedCount" />
        <x-ui.stat-card :label="__('dashboard.stats.zero_value')" :value="(string) $dashboard->zeroValueCount" />
        <x-ui.stat-card :label="__('dashboard.stats.duplicates_ignored')" :value="(string) $dashboard->duplicatesIgnored" />
    </div>

    <div class="space-y-4">
        <div>
            <flux:heading size="lg" class="font-display text-text-heading!">
                {{ __('dashboard.sections.category_breakdown') }}
            </flux:heading>
            <flux:text class="mt-1 text-text-muted!">
                {{ __('dashboard.sections.category_breakdown_description') }}
            </flux:text>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
            @foreach ($dashboard->categoryCounts as $categoryCount)
                <x-ui.stat-card
                    :label="$categoryCount->code"
                    :value="(string) $categoryCount->count"
                    :context="$dashboard->periodLabel"
                />
            @endforeach

            <x-ui.stat-card
                label="CV"
                :value="(string) $dashboard->cvInspectionCount"
                :context="$dashboard->periodLabel"
                :accent="true"
            />
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <x-ui.card :title="__('dashboard.sections.revenue_trend')" class="xl:col-span-8">
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach ($trendOptions as $trendOption)
                    <button
                        type="button"
                        wire:click="$set('trend', '{{ $trendOption->value }}')"
                        @class([
                            'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                            'bg-midnight-navy text-white shadow-sm' => $trend === $trendOption->value,
                            'bg-slate-100 text-text-muted hover:bg-slate-200' => $trend !== $trendOption->value,
                        ])
                    >
                        {{ $trendOption->label() }}
                    </button>
                @endforeach
            </div>

            @if ($dashboard->trend === [])
                <flux:text class="text-text-muted!">{{ __('dashboard.empty.trend') }}</flux:text>
            @else
                <div class="space-y-3">
                    @foreach ($dashboard->trend as $point)
                        @php($width = $dashboard->trendMaxTtc > 0 ? max(4, ($point->totalTtcNumeric / $dashboard->trendMaxTtc) * 100) : 0)
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                                <span class="text-text-muted">{{ $point->label }}</span>
                                <span class="tabular-money font-medium text-text-heading">{{ $point->totalTtc }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-slate-100">
                                <div
                                    class="h-2 rounded-full bg-emerald-brand transition-all"
                                    style="width: {{ $width }}%"
                                ></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('dashboard.sections.submission_alerts')" class="xl:col-span-4">
            <div class="space-y-3">
                @foreach ($dashboard->alerts as $alert)
                    <flux:callout
                        :variant="match ($alert->type) {
                            'error' => 'danger',
                            'warning' => 'warning',
                            'success' => 'success',
                            default => 'secondary',
                        }"
                        class="text-sm"
                    >
                        @if ($alert->href)
                            <a href="{{ $alert->href }}" wire:navigate class="underline decoration-current/40 underline-offset-2">
                                {{ $alert->message }}
                            </a>
                        @else
                            {{ $alert->message }}
                        @endif
                    </flux:callout>
                @endforeach
            </div>
        </x-ui.card>
    </div>

    <x-ui.table-panel
        :title="__('dashboard.sections.recent_imports')"
        :description="__('dashboard.sections.recent_imports_description')"
    >
        @if ($dashboard->recentImports === [])
            <flux:text class="text-text-muted!">{{ __('dashboard.empty.imports') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('dashboard.table.date') }}</flux:table.column>
                    <flux:table.column>{{ __('dashboard.table.file') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('reports.stats.total_ttc') }}</flux:table.column>
                    <flux:table.column>{{ __('dashboard.table.status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($dashboard->recentImports as $importRow)
                        @php($badge = $this->importStatusBadge($importRow->status))
                        <flux:table.row wire:key="import-row-{{ $importRow->id }}">
                            <flux:table.cell>{{ $importRow->importedAt }}</flux:table.cell>
                            <flux:table.cell>
                                <a href="{{ route('imports.show', $importRow->id) }}" wire:navigate class="font-medium text-text-heading hover:text-emerald-brand">
                                    {{ $importRow->filename }}
                                </a>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-money font-medium">
                                {{ $importRow->totalTtc }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <x-ui.status-badge :status="$badge['status']">
                                    {{ $badge['label'] }}
                                </x-ui.status-badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.table-panel>

    <flux:modal
        wire:model.self="showCustomPeriodModal"
        wire:cancel="cancelCustomPeriod"
        class="mf-dashboard-period-modal md:max-w-lg"
    >
        <div class="mf-dashboard-period-modal-body space-y-6">
            <div class="mf-dashboard-period-modal-header space-y-3 pe-8">
                <div class="mf-dashboard-period-modal-icon" aria-hidden="true">
                    <flux:icon icon="calendar-days" variant="outline" class="size-5 text-emerald-brand" />
                </div>
                <div class="space-y-2">
                    <flux:heading size="lg" class="font-display text-text-heading!">
                        {{ __('dashboard.period.modal_title') }}
                    </flux:heading>
                    <flux:text class="text-text-muted!">
                        {{ __('dashboard.period.modal_description') }}
                    </flux:text>
                </div>
            </div>

            <div class="mf-dashboard-period-modal-fields grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-ui.date-picker
                    wire:model="customFromDate"
                    :teleport="false"
                    :label="__('dashboard.period.fields.from')"
                />
                <x-ui.date-picker
                    wire:model="customToDate"
                    :teleport="false"
                    :label="__('dashboard.period.fields.to')"
                />
            </div>

            <div class="mf-dashboard-period-modal-actions flex flex-wrap items-center justify-end gap-3">
                <x-ui.button variant="secondary" wire:click="cancelCustomPeriod">
                    {{ __('dashboard.period.cancel') }}
                </x-ui.button>
                <x-ui.button variant="primary" wire:click="applyCustomPeriod" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="applyCustomPeriod">{{ __('dashboard.period.apply') }}</span>
                    <span wire:loading wire:target="applyCustomPeriod">{{ __('dashboard.period.apply') }}</span>
                </x-ui.button>
            </div>
        </div>
    </flux:modal>
</x-ui.page>
