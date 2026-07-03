@php($summary = $this->summary)

@if ($summary)
    <div class="mf-csv-verification-summary space-y-6" data-mf-csv-verification-summary>
        @if ($this->isCorrectionMode)
            <flux:callout variant="warning" icon="exclamation-triangle" class="text-sm">
                {{ $this->isStaffView
                    ? __('csv_verification.correction.manager_submit_notice')
                    : __('csv_verification.correction.owner_submit_notice') }}
            </flux:callout>
        @endif

        <flux:callout variant="success" icon="check-circle" class="text-sm">
            {{ __('csv_verification.card.ready_message', ['filename' => $summary->filename]) }}
        </flux:callout>

        <x-ui.card compact :title="__('csv_verification.summary.file_information')" class="mf-csv-verification-summary-section">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('csv_verification.summary.filename') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-text-heading">{{ $summary->filename }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('csv_verification.summary.center') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-text-heading">{{ $summary->centerName }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('csv_verification.summary.language') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-text-heading">{{ $summary->sourceLanguage }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('csv_verification.summary.actual_period') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-text-heading">{{ $summary->actualPeriod ?? '—' }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card compact :title="__('csv_verification.summary.footer_totals')" class="mf-csv-verification-summary-section">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat-card
                    :label="__('csv_verification.summary.total_records')"
                    :value="$summary->footerCount"
                />
                <x-ui.stat-card
                    :label="__('csv_verification.summary.total_ht')"
                    :value="$summary->footerHt"
                />
                <x-ui.stat-card
                    :label="__('csv_verification.summary.total_vat')"
                    :value="$summary->footerVat"
                />
                <x-ui.stat-card
                    :label="__('csv_verification.summary.total_ttc')"
                    :value="$summary->footerTtc"
                    :accent="true"
                    class="mf-csv-verification-ttc-stat"
                />
            </div>
        </x-ui.card>

        <x-ui.card compact :title="__('csv_verification.summary.verification_status')" class="mf-csv-verification-summary-section">
            <div class="space-y-2">
                @foreach ($summary->checks as $check)
                    <div class="mf-csv-verification-check flex items-center justify-between gap-3 rounded-lg border border-slate-200/80 px-3 py-2.5">
                        <span class="text-sm font-medium text-text-heading">{{ $check->label }}</span>
                        <x-ui.status-badge :status="$check->passed ? 'success' : 'error'">
                            {{ $check->passed ? __('csv_verification.summary.passed') : __('csv_verification.summary.failed') }}
                        </x-ui.status-badge>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card compact :title="__('csv_verification.summary.compact_stats')" class="mf-csv-verification-summary-section">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-ui.stat-card :label="__('csv_verification.summary.stats.completed')" :value="(string) $summary->completed" />
                <x-ui.stat-card :label="__('csv_verification.summary.stats.unfinished')" :value="(string) $summary->unfinished" />
                <x-ui.stat-card :label="__('csv_verification.summary.stats.revenue_generating')" :value="(string) $summary->revenueGenerating" />
                <x-ui.stat-card :label="__('csv_verification.summary.stats.zero_value')" :value="(string) $summary->zeroValue" />
                <x-ui.stat-card :label="__('csv_verification.summary.stats.invalid_rows')" :value="(string) $summary->invalidRows" />
                <x-ui.stat-card :label="__('csv_verification.summary.stats.exact_duplicates')" :value="(string) $summary->exactDuplicates" />
                <x-ui.stat-card :label="__('csv_verification.summary.stats.new_unique')" :value="(string) $summary->newUnique" />
                <x-ui.stat-card :label="__('csv_verification.summary.stats.probable_duplicates')" :value="(string) $summary->probableDuplicates" />
            </div>
        </x-ui.card>

        @if ($summary->warnings !== [])
            <x-ui.card compact :title="__('csv_verification.summary.warnings_title')" class="mf-csv-verification-summary-section">
                <div class="space-y-2">
                    @foreach ($summary->warnings as $warning)
                        <flux:callout variant="warning" icon="exclamation-triangle" class="text-sm">
                            {{ $warning }}
                        </flux:callout>
                    @endforeach
                </div>
            </x-ui.card>
        @endif

        @if ($this->canDownloadErrorReport)
            <div class="flex justify-end">
                <flux:button
                    variant="outline"
                    icon="arrow-down-tray"
                    :href="$this->errorReportDownloadUrl"
                    class="mf-btn-secondary"
                >
                    {{ __('csv_verification.summary.download_errors') }}
                </flux:button>
            </div>
        @endif

        @error('import')
            <flux:error>{{ $message }}</flux:error>
        @enderror

        <div class="flex flex-col gap-3 border-t border-slate-200/80 pt-5 sm:flex-row sm:items-center sm:justify-end">
            <x-ui.button
                variant="secondary"
                wire:click="reject"
                wire:loading.attr="disabled"
                wire:target="reject,import"
                :disabled="$isImporting"
            >
                {{ __('csv_verification.card.reject') }}
            </x-ui.button>

            <x-ui.button
                variant="primary"
                icon="arrow-down-tray"
                wire:click="import"
                wire:loading.attr="disabled"
                wire:target="import,reject"
                :disabled="$isImporting || ! $summary->canImport"
            >
                <span wire:loading.remove wire:target="import">{{ $this->commitActionLabel }}</span>
                <span wire:loading wire:target="import">{{ $this->commitActionLoadingLabel }}</span>
            </x-ui.button>
        </div>
    </div>
@endif
