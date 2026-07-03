<x-ui.page
    @class([
        'max-w-3xl mf-import-csv',
        'mf-import-csv--compact' => $this->isStaffView,
    ])
>
    @if ($this->isStaffView)
        <header class="mf-import-csv-header mb-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <flux:heading size="lg" class="font-display text-text-heading!">
                        {{ $this->isCashierView
                            ? __('csv_verification.page.cashier.title')
                            : __('csv_verification.page.manager.title') }}
                    </flux:heading>
                    <flux:text class="text-sm text-text-muted!">
                        {{ $this->isCashierView
                            ? __('csv_verification.page.cashier.subtitle_compact')
                            : __('csv_verification.page.manager.subtitle_compact') }}
                    </flux:text>
                </div>

                <div class="mf-import-csv-center-pill shrink-0 rounded-lg border border-slate-200/80 bg-white/70 px-3 py-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">
                        {{ __('csv_verification.card.assigned_center_label') }}
                    </p>
                    <p class="mt-0.5 text-sm font-medium text-text-heading">{{ $this->centerName }}</p>
                </div>
            </div>
        </header>
    @endif

    <livewire:csv-verification.csv-verification-card />
</x-ui.page>
