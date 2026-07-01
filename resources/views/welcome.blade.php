<x-layouts.app>
    <x-ui.page>
        <header class="space-y-2">
            <flux:heading size="xl" class="font-display text-text-heading!">
                {{ config('app.name') }}
            </flux:heading>
            <flux:text class="text-text-muted!">
                Design system preview · Steps 19–24 · resize viewport or use DevTools device mode
            </flux:text>
        </header>

        <x-ui.card title="Summary cards">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.stat-card label="TTC" value="2 450 000,00" context="Today" />
                <x-ui.stat-card label="HT" value="2 071 186,44" context="Today" />
                <x-ui.stat-card label="VAT" value="378 813,56" context="Today" />
                <x-ui.stat-card label="Unique records" value="1 284" :accent="true" context="Active center" />
            </div>
        </x-ui.card>

        <x-ui.card title="Buttons">
            <div class="flex flex-wrap gap-3">
                <x-ui.button variant="primary" icon="shield-check">Verify</x-ui.button>
                <x-ui.button variant="primary" icon="check-circle">Import</x-ui.button>
                <x-ui.button variant="secondary" icon="arrow-down-tray">Export</x-ui.button>
                <x-ui.button variant="approval" icon="check-badge">Approve revision</x-ui.button>
                <x-ui.button variant="destructive-outline" icon="x-circle">Reject</x-ui.button>
                <x-ui.button variant="destructive" icon="trash">Delete</x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card title="Status badges">
            <div class="flex flex-wrap gap-2">
                <x-ui.status-badge status="success" icon="check-circle">Imported</x-ui.status-badge>
                <x-ui.status-badge status="warning" icon="exclamation-triangle">Pending review</x-ui.status-badge>
                <x-ui.status-badge status="error" icon="x-circle">Failed</x-ui.status-badge>
                <x-ui.status-badge status="info" icon="information-circle">Processing</x-ui.status-badge>
                <x-ui.status-badge status="neutral">Draft</x-ui.status-badge>
            </div>
        </x-ui.card>

        <x-ui.table-panel
            title="Recent imports"
            description="Compact table pattern for dashboards and history pages."
        >
            <flux:table.columns>
                <flux:table.column>Date</flux:table.column>
                <flux:table.column>File</flux:table.column>
                <flux:table.column align="end">TTC</flux:table.column>
                <flux:table.column>Status</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                <flux:table.row>
                    <flux:table.cell>2026-07-01</flux:table.cell>
                    <flux:table.cell>cashier-july-01.csv</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-money font-medium">2 450 000,00</flux:table.cell>
                    <flux:table.cell>
                        <x-ui.status-badge status="success">Complete</x-ui.status-badge>
                    </flux:table.cell>
                </flux:table.row>
                <flux:table.row>
                    <flux:table.cell>2026-06-30</flux:table.cell>
                    <flux:table.cell>cashier-june-30.csv</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-money font-medium">2 118 400,00</flux:table.cell>
                    <flux:table.cell>
                        <x-ui.status-badge status="warning">Revision pending</x-ui.status-badge>
                    </flux:table.cell>
                </flux:table.row>
            </flux:table.rows>
        </x-ui.table-panel>

        <x-ui.card title="Palette">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div class="flex items-center gap-3">
                    <span class="size-10 shrink-0 rounded-md bg-midnight-navy"></span>
                    <span class="text-sm text-text-body">midnight-navy</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="size-10 shrink-0 rounded-md bg-emerald-brand"></span>
                    <span class="text-sm text-text-body">emerald-brand</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="size-10 shrink-0 rounded-md bg-gold-brand"></span>
                    <span class="text-sm text-text-body">gold-brand</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="size-10 shrink-0 rounded-md border border-slate-200 bg-app-bg"></span>
                    <span class="text-sm text-text-body">app-bg</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="size-10 shrink-0 rounded-md border border-slate-200 bg-surface"></span>
                    <span class="text-sm text-text-body">surface</span>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Typography">
            <p class="font-display text-xl font-semibold text-text-heading">Manrope display heading</p>
            <p class="mt-2 text-text-body">Inter body text for UI and forms.</p>
            <p class="tabular-money mt-2 text-2xl font-semibold text-emerald-brand">
                1 234 567,89 HT
            </p>
        </x-ui.card>

        <x-ui.card title="Heroicons via Flux">
            <flux:text class="mb-4 text-text-muted!">Outline nav icons — no extra icon packages (NFR-002).</flux:text>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ([
                    ['home', 'Dashboard'],
                    ['building-office-2', 'Centers'],
                    ['users', 'Users'],
                    ['banknotes', 'Cashier'],
                    ['arrow-up-tray', 'CSV import'],
                    ['document-currency-dollar', 'Records'],
                    ['chart-bar-square', 'Reports'],
                    ['bell', 'Notifications'],
                    ['cog-6-tooth', 'Settings'],
                ] as [$icon, $label])
                    <li class="flex items-center gap-2 text-sm text-text-body">
                        <flux:icon :icon="$icon" variant="outline" class="size-5 text-emerald-brand" />
                        {{ $label }}
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    </x-ui.page>
</x-layouts.app>
