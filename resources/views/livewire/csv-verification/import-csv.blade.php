<x-ui.page class="max-w-3xl mf-import-csv">
    @if ($this->isManagerView)
        <header class="mb-6 space-y-4">
            <div class="space-y-2">
                <flux:heading size="xl" class="font-display text-text-heading!">
                    {{ __('csv_verification.page.manager.title') }}
                </flux:heading>
                <flux:text class="text-text-muted!">
                    {{ __('csv_verification.page.manager.subtitle', ['center' => $this->centerName]) }}
                </flux:text>
            </div>

            <div class="mf-import-list-center rounded-lg border border-slate-200/80 bg-white/70 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">
                    {{ __('csv_verification.card.assigned_center_label') }}
                </p>
                <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->centerName }}</p>
            </div>

            <flux:callout variant="info" icon="information-circle" class="text-sm">
                {{ __('csv_verification.page.manager.correction_help') }}
            </flux:callout>
        </header>
    @endif

    <livewire:csv-verification.csv-verification-card />
</x-ui.page>
