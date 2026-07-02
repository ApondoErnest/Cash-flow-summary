<x-ui.page wide class="mf-center-report">
    <header class="mf-center-report-header flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div class="space-y-4">
            <div class="space-y-2">
                <flux:heading size="xl" class="font-display text-text-heading!">
                    {{ __('reports.title') }}
                </flux:heading>
                <flux:text class="text-text-muted!">
                    {{ $this->pageDescription }}
                </flux:text>
            </div>

            <div class="mf-center-report-center rounded-lg border border-slate-200/80 bg-white/70 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ $this->centerBannerLabel }}</p>
                <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->centerName }}</p>
            </div>
        </div>

        <div
            class="mf-center-report-period-filter"
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
    </header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card
            :label="__('reports.stats.total_ttc')"
            :value="$report->totalTtc"
            :context="$report->periodLabel"
            :accent="true"
        />
        <x-ui.stat-card
            :label="__('reports.stats.total_ht')"
            :value="$report->totalHt"
            :context="$report->periodLabel"
        />
        <x-ui.stat-card
            :label="__('reports.stats.total_vat')"
            :value="$report->totalVat"
            :context="$report->periodLabel"
        />
        <x-ui.stat-card
            :label="__('reports.stats.record_count')"
            :value="(string) $report->recordCount"
            :context="trans_choice('reports.stats.days_with_data', $report->daysWithData, ['count' => $report->daysWithData])"
        />
    </div>

    @if ($report->missingSubmissionDates !== [])
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>
                {{ trans_choice('reports.missing_submissions.title', count($report->missingSubmissionDates), ['count' => count($report->missingSubmissionDates)]) }}
            </flux:callout.heading>
            <flux:callout.text>
                {{ __('reports.missing_submissions.description', ['period' => $report->periodLabel]) }}
            </flux:callout.text>
            <ul class="mt-2 list-inside list-disc text-sm text-text-muted">
                @foreach (array_slice($report->missingSubmissionDates, -5) as $missingDate)
                    <li>{{ \Illuminate\Support\Carbon::parse($missingDate)->format('d/m/Y') }}</li>
                @endforeach
                @if (count($report->missingSubmissionDates) > 5)
                    <li>{{ __('reports.missing_submissions.and_more', ['count' => count($report->missingSubmissionDates) - 5]) }}</li>
                @endif
            </ul>
        </flux:callout>
    @endif

    <x-ui.table-panel
        :title="__('reports.table.title')"
        :description="__('reports.table.description', ['period' => $report->periodLabel])"
    >
        @if ($report->dailyRows === [])
            <flux:text class="text-text-muted!">{{ __('reports.empty') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('reports.columns.business_date') }}</flux:table.column>
                    <flux:table.column>{{ __('reports.columns.record_count') }}</flux:table.column>
                    <flux:table.column>{{ __('reports.columns.ht') }}</flux:table.column>
                    <flux:table.column>{{ __('reports.columns.vat') }}</flux:table.column>
                    <flux:table.column>{{ __('reports.columns.ttc') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($report->dailyRows as $row)
                        <flux:table.row wire:key="report-day-{{ $row->businessDateIso }}">
                            <flux:table.cell class="font-medium text-text-heading">{{ $row->businessDate }}</flux:table.cell>
                            <flux:table.cell>{{ $row->recordCount }}</flux:table.cell>
                            <flux:table.cell class="tabular-money">{{ $row->totalHt }}</flux:table.cell>
                            <flux:table.cell class="tabular-money">{{ $row->totalVat }}</flux:table.cell>
                            <flux:table.cell class="tabular-money font-semibold text-gold-brand">{{ $row->totalTtc }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.table-panel>

    @if (! $this->isManagerView)
        <flux:callout variant="secondary" icon="arrow-down-tray">
            <flux:callout.heading>{{ __('reports.export.coming_soon_title') }}</flux:callout.heading>
            <flux:callout.text>{{ __('reports.export.coming_soon_description') }}</flux:callout.text>
        </flux:callout>
    @endif

    <flux:modal
        wire:model.self="showCustomPeriodModal"
        wire:cancel="cancelCustomPeriod"
        class="mf-center-report-period-modal md:max-w-lg"
    >
        <div class="space-y-6">
            <div class="space-y-3 pe-8">
                <div aria-hidden="true">
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

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
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

            <div class="flex flex-wrap items-center justify-end gap-3">
                <x-ui.button variant="secondary" wire:click="cancelCustomPeriod">
                    {{ __('dashboard.period.cancel') }}
                </x-ui.button>
                <x-ui.button variant="primary" wire:click="applyCustomPeriod" wire:loading.attr="disabled">
                    {{ __('dashboard.period.apply') }}
                </x-ui.button>
            </div>
        </div>
    </flux:modal>
</x-ui.page>
