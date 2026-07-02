@props(['result'])

<x-ui.card compact :title="__('csv_import.result.file_information')" class="mf-import-summary-section">
    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('csv_import.result.filename') }}</dt>
            <dd class="mt-1 text-sm font-medium text-text-heading">{{ $result->filename }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('csv_import.result.center') }}</dt>
            <dd class="mt-1 text-sm font-medium text-text-heading">{{ $result->centerName }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('csv_import.result.import_mode') }}</dt>
            <dd class="mt-1 text-sm font-medium text-text-heading">{{ $result->importModeLabel }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('csv_import.result.language') }}</dt>
            <dd class="mt-1 text-sm font-medium text-text-heading">{{ $result->sourceLanguage }}</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('csv_import.result.actual_period') }}</dt>
            <dd class="mt-1 text-sm font-medium text-text-heading">{{ $result->actualPeriod ?? '—' }}</dd>
        </div>
    </dl>
</x-ui.card>

<x-ui.card compact :title="__('csv_import.result.row_impact')" class="mf-import-summary-section">
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-ui.stat-card :label="__('csv_import.result.stats.source_rows')" :value="(string) $result->sourceRows" />
        <x-ui.stat-card :label="__('csv_import.result.stats.new_unique')" :value="(string) $result->newUnique" />
        <x-ui.stat-card :label="__('csv_import.result.stats.duplicates_ignored')" :value="(string) $result->duplicatesIgnored" />
        <x-ui.stat-card :label="__('csv_import.result.stats.invalid_rows')" :value="(string) $result->invalidRows" />
    </div>
</x-ui.card>

<x-ui.card compact :title="__('csv_import.result.daily_impact')" class="mf-import-summary-section">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-ui.stat-card :label="__('csv_import.result.stats.active_days')" :value="(string) $result->activeDays" />
        <x-ui.stat-card :label="__('csv_import.result.stats.unchanged_days')" :value="(string) $result->unchangedDays" />
        <x-ui.stat-card
            :label="__('csv_import.result.stats.revisions_pending')"
            :value="(string) $result->revisionsPending"
            :accent="$result->revisionsPending > 0"
        />
    </div>
</x-ui.card>

<x-ui.card compact :title="__('csv_import.result.footer_totals')" class="mf-import-summary-section">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-ui.stat-card :label="__('csv_import.result.total_ht')" :value="$result->footerHt" />
        <x-ui.stat-card :label="__('csv_import.result.total_vat')" :value="$result->footerVat" />
        <x-ui.stat-card
            :label="__('csv_import.result.total_ttc')"
            :value="$result->footerTtc"
            :accent="true"
            class="mf-import-summary-ttc-stat"
        />
    </div>
</x-ui.card>

<x-ui.card compact :title="__('csv_import.result.whatsapp_title')" class="mf-import-summary-section">
    <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200/80 px-3 py-2.5">
        <span class="text-sm font-medium text-text-heading">{{ __('csv_import.result.whatsapp_label') }}</span>
        <x-ui.status-badge :status="$result->whatsappVariant">{{ $result->whatsappStatus }}</x-ui.status-badge>
    </div>
</x-ui.card>

@if ($result->warnings !== [])
    <x-ui.card compact :title="__('csv_import.result.warnings_title')" class="mf-import-summary-section">
        <div class="space-y-2">
            @foreach ($result->warnings as $warning)
                <flux:callout variant="warning" icon="exclamation-triangle" class="text-sm">
                    {{ $warning }}
                </flux:callout>
            @endforeach
        </div>
    </x-ui.card>
@endif

@if ($result->revisionsPending > 0)
    <flux:callout variant="warning" icon="clock" class="text-sm">
        {{ trans_choice('csv_import.result.revisions_notice', $result->revisionsPending, ['count' => $result->revisionsPending]) }}
    </flux:callout>
@endif
