@php($detail = $this->detail)
@php($result = $detail->result)

<x-ui.page class="max-w-3xl mf-import-detail">
    <div class="space-y-6" data-mf-import-detail>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-3">
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="arrow-left"
                    :href="route('imports.index')"
                    wire:navigate
                    class="mf-import-detail-back"
                >
                    {{ __('csv_import.detail.back_to_list') }}
                </flux:button>

                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-xl font-semibold text-text-heading">{{ $result->filename }}</h1>
                    <x-ui.status-badge :status="$result->statusVariant">{{ $result->statusBadge }}</x-ui.status-badge>
                </div>

                <flux:text class="text-text-muted!">{{ $result->headline }}</flux:text>
            </div>
        </div>

        @if ($this->isStaffView)
            <div class="mf-import-list-center rounded-lg border border-slate-200/80 bg-white/70 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">
                    {{ __('csv_import.page.staff.center_label') }}
                </p>
                <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->centerName }}</p>
            </div>
        @endif

        <x-ui.card compact :title="__('csv_import.detail.metadata')" class="mf-import-detail-section">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('csv_import.detail.uploaded_by') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-text-heading">{{ $detail->uploadedByName }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('csv_import.detail.completed_at') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-text-heading">{{ $detail->completedAt ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('csv_import.detail.file_size') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-text-heading">{{ $detail->fileSizeLabel }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('csv_import.detail.error_count') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-text-heading">{{ $detail->errorCount }}</dd>
                </div>
            </dl>
        </x-ui.card>

        @include('livewire.csv-imports.partials.import-summary-sections', ['result' => $result])

        @if ($detail->dayComparisons !== [])
            <x-ui.table-panel
                :title="__('csv_import.detail.day_comparisons_title')"
                :description="__('csv_import.detail.day_comparisons_description')"
                class="mf-import-detail-section"
            >
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('csv_import.detail.columns.business_date') }}</flux:table.column>
                        <flux:table.column>{{ __('csv_import.detail.columns.result') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('csv_import.detail.columns.existing_ttc') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('csv_import.detail.columns.proposed_ttc') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('csv_import.detail.columns.record_delta') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($detail->dayComparisons as $comparison)
                            <flux:table.row wire:key="import-day-comparison-{{ $comparison->businessDate }}">
                                <flux:table.cell>{{ $comparison->businessDate }}</flux:table.cell>
                                <flux:table.cell>
                                    <x-ui.status-badge :status="$comparison->resultVariant">{{ $comparison->resultLabel }}</x-ui.status-badge>
                                </flux:table.cell>
                                <flux:table.cell align="end" class="tabular-money">{{ $comparison->existingTtc }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-money">{{ $comparison->proposedTtc }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $comparison->recordCountDelta }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-ui.table-panel>
        @endif

        <div class="flex flex-col gap-3 border-t border-slate-200/80 pt-5 sm:flex-row sm:flex-wrap sm:items-center">
            <flux:button variant="outline" :href="route('imports.result', $import)" wire:navigate class="mf-btn-secondary">
                {{ __('csv_import.detail.actions.view_result') }}
            </flux:button>

            @if ($detail->errorCount > 0)
                <flux:button
                    variant="outline"
                    icon="arrow-down-tray"
                    :href="route('imports.errors.download', $import)"
                    class="mf-btn-secondary"
                >
                    {{ __('csv_import.detail.actions.download_errors') }}
                </flux:button>
            @endif

            <flux:button variant="outline" :href="route('dashboard')" wire:navigate class="mf-btn-secondary">
                {{ __('csv_import.result.actions.dashboard') }}
            </flux:button>

            <flux:button variant="primary" icon="arrow-up-tray" :href="route('imports.create')" wire:navigate class="mf-btn-primary">
                {{ __('csv_import.result.actions.import_another') }}
            </flux:button>
        </div>
    </div>
</x-ui.page>
