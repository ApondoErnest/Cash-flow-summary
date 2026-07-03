<x-ui.page class="mf-cashier-dashboard">
    <header class="space-y-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <flux:heading size="xl" class="font-display text-text-heading!">
                    {{ __('dashboard.cashier.title', ['center' => $dashboard->centerName]) }}
                </flux:heading>
                <flux:text class="text-text-muted!">
                    {{ __('dashboard.cashier.subtitle', ['date' => $dashboard->referenceDate]) }}
                </flux:text>
            </div>

            <x-ui.button
                variant="primary"
                icon="arrow-up-tray"
                href="{{ route('imports.create') }}"
                wire:navigate
                class="shrink-0"
            >
                {{ __('dashboard.actions.import_csv') }}
            </x-ui.button>
        </div>

        <div class="mf-cashier-dashboard-center rounded-lg border border-slate-200/80 bg-white/70 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('dashboard.cashier.center_label') }}</p>
            <p class="mt-1 text-sm font-medium text-text-heading">{{ $dashboard->centerName }}</p>
        </div>
    </header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-ui.stat-card
            :label="__('dashboard.cashier.stats.today_ttc')"
            :value="$dashboard->todayTtc"
            :context="__('dashboard.period.today')"
            :accent="true"
        />
        <x-ui.stat-card
            :label="__('dashboard.cashier.stats.yesterday_ttc')"
            :value="$dashboard->yesterdayTtc"
            :context="__('dashboard.period.yesterday')"
        />
        <x-ui.stat-card
            :label="__('dashboard.cashier.stats.active_records_today')"
            :value="(string) $dashboard->activeRecordsToday"
            :context="__('dashboard.period.today')"
        />
    </div>

    <x-ui.card :title="__('dashboard.cashier.submission_title')">
        @if ($dashboard->missingSubmissionDates !== [])
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>
                    {{ trans_choice('dashboard.cashier.missing_days_count', count($dashboard->missingSubmissionDates), ['count' => count($dashboard->missingSubmissionDates)]) }}
                </flux:callout.text>
            </flux:callout>
            @php($latestMissingDate = collect($dashboard->missingSubmissionDates)->last())
            <flux:text class="mt-3 text-sm text-text-muted!">
                {{ __('dashboard.cashier.latest_missing', ['date' => \Illuminate\Support\Carbon::parse($latestMissingDate)->format('d/m/Y')]) }}
            </flux:text>
        @else
            <flux:callout variant="success" icon="check-circle">
                <flux:callout.text>{{ __('dashboard.cashier.submission_clear') }}</flux:callout.text>
            </flux:callout>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('dashboard.cashier.recent_imports_title')">
        @if ($dashboard->recentImports === [])
            <flux:text class="text-text-muted!">{{ __('dashboard.empty.imports') }}</flux:text>
        @else
            <ul class="divide-y divide-slate-200/80">
                @foreach ($dashboard->recentImports as $importRow)
                    @php($badge = $this->importStatusBadge($importRow->status))
                    <li class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between" wire:key="cashier-import-row-{{ $importRow->id }}">
                        <div class="min-w-0">
                            <a href="{{ route('imports.show', $importRow->id) }}" wire:navigate class="font-medium text-text-heading hover:text-emerald-brand">
                                {{ $importRow->filename }}
                            </a>
                            <p class="mt-1 text-sm text-text-muted">{{ $importRow->importedAt }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="tabular-money text-sm font-medium text-text-heading">{{ $importRow->totalTtc }}</span>
                            <x-ui.status-badge :status="$badge['status']">{{ $badge['label'] }}</x-ui.status-badge>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4 border-t border-slate-200/80 pt-4">
                <x-ui.button
                    variant="secondary"
                    icon="queue-list"
                    href="{{ route('imports.index') }}"
                    wire:navigate
                >
                    {{ __('dashboard.actions.view_imports') }}
                </x-ui.button>
            </div>
        @endif
    </x-ui.card>
</x-ui.page>
