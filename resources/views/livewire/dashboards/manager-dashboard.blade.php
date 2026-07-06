<x-ui.page wide class="mf-manager-dashboard">
    <header class="mf-manager-dashboard-header flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div class="min-w-0 space-y-2">
            <flux:heading size="xl" class="font-display text-text-heading!">
                {{ __('dashboard.manager.title', ['center' => $dashboard->centerName]) }}
            </flux:heading>
            <flux:text class="text-text-muted!">
                {{ __('dashboard.manager.subtitle') }}
            </flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-3">
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
                icon="queue-list"
                href="{{ route('imports.index') }}"
                wire:navigate
            >
                {{ __('dashboard.actions.view_imports') }}
            </x-ui.button>

            @if ($dashboard->lastImportAt)
                <flux:text class="text-sm text-text-muted!">
                    {{ __('dashboard.manager.last_import', ['datetime' => $dashboard->lastImportAt]) }}
                </flux:text>
            @endif
        </div>
    </header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-ui.stat-card
            :label="__('dashboard.manager.stats.today_ttc')"
            :value="$dashboard->todayTtc"
            :context="__('dashboard.period.today')"
            :accent="true"
        />
        <x-ui.stat-card
            :label="__('dashboard.manager.stats.yesterday_ttc')"
            :value="$dashboard->yesterdayTtc"
            :context="__('dashboard.period.yesterday')"
        />
        <x-ui.stat-card
            :label="__('dashboard.manager.stats.week_ttc')"
            :value="$dashboard->weekTtc"
            :context="__('dashboard.period.week')"
        />
        <x-ui.stat-card
            :label="__('dashboard.manager.stats.month_ttc')"
            :value="$dashboard->monthTtc"
            :context="__('dashboard.period.month')"
        />
        <x-ui.stat-card
            :label="__('dashboard.manager.stats.year_ttc')"
            :value="$dashboard->yearTtc"
            :context="__('dashboard.period.year')"
        />
        <x-ui.stat-card
            :label="__('dashboard.manager.stats.active_records_today')"
            :value="(string) $dashboard->activeRecordsToday"
            :context="__('dashboard.period.today')"
        />
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
            @if ($dashboard->missingSubmissionDates !== [])
                <div class="mb-4 rounded-lg border border-slate-200/80 bg-slate-50/80 px-3 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">
                        {{ __('dashboard.manager.submission_status') }}
                    </p>
                    <p class="mt-1 text-sm text-text-heading">
                        {{ trans_choice('dashboard.manager.missing_days_count', count($dashboard->missingSubmissionDates), ['count' => count($dashboard->missingSubmissionDates)]) }}
                    </p>
                    <p class="mt-1 text-xs text-text-muted">
                        @php($latestMissingDate = collect($dashboard->missingSubmissionDates)->last())
                        {{ __('dashboard.manager.latest_missing', ['date' => \Illuminate\Support\Carbon::parse($latestMissingDate)->format('d/m/Y')]) }}
                    </p>
                </div>
            @endif

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
        :description="__('dashboard.manager.recent_imports_description')"
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
                        <flux:table.row wire:key="manager-import-row-{{ $importRow->id }}">
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
</x-ui.page>
