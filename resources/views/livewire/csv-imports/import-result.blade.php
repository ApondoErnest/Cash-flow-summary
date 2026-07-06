@php($result = $this->result)

<x-ui.page class="max-w-3xl mf-import-result">
    <div class="space-y-6" data-mf-import-result>
        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-xl font-semibold text-text-heading">{{ __('csv_import.result.title') }}</h1>
                <x-ui.status-badge :status="$result->statusVariant">{{ $result->statusBadge }}</x-ui.status-badge>
            </div>

            <flux:callout
                :variant="$result->isExactFileDuplicate ? 'warning' : ($result->statusVariant === 'error' ? 'danger' : 'success')"
                :icon="$result->isExactFileDuplicate ? 'exclamation-triangle' : 'check-circle'"
                class="text-sm"
            >
                {{ $result->headline }}
            </flux:callout>

            @if ($this->isStaffView && $this->isCorrectionSubmission && $result->revisionsPending > 0)
                <flux:callout variant="info" icon="information-circle" class="text-sm">
                    {{ __('csv_import.result.correction.manager_follow_up') }}
                </flux:callout>
            @endif
        </div>

        @include('livewire.csv-imports.partials.import-summary-sections', ['result' => $result])

        <div class="flex flex-col gap-3 border-t border-slate-200/80 pt-5 sm:flex-row sm:flex-wrap sm:items-center">
            <flux:button variant="outline" :href="route('dashboard')" wire:navigate class="mf-btn-secondary">
                {{ __('csv_import.result.actions.dashboard') }}
            </flux:button>

            <flux:button variant="outline" :href="route('imports.show', $this->import)" wire:navigate class="mf-btn-secondary">
                {{ __('csv_import.result.actions.import_details') }}
            </flux:button>

            @if ($result->invalidRows > 0)
                <flux:button
                    variant="outline"
                    icon="arrow-down-tray"
                    :href="$this->importErrorDownloadUrl"
                    class="mf-btn-secondary"
                >
                    {{ __('csv_import.result.actions.download_errors') }}
                </flux:button>
            @endif

            @if ($this->isManagerView && $result->revisionsPending > 0)
                <flux:button variant="outline" :href="route('revisions.index')" wire:navigate class="mf-btn-secondary">
                    {{ __('csv_import.result.actions.view_revisions') }}
                </flux:button>
            @endif

            <flux:button variant="primary" icon="arrow-up-tray" :href="route('imports.create')" wire:navigate class="mf-btn-primary">
                {{ __('csv_import.result.actions.import_another') }}
            </flux:button>
        </div>
    </div>
</x-ui.page>
