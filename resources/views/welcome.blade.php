<x-layouts.app>
    <x-ui.page>
        <header class="space-y-2">
            <flux:heading size="xl" class="font-display text-text-heading!">
                {{ config('app.name') }}
            </flux:heading>
            <flux:text class="text-text-muted!">
                {{ __('welcome.preview_subtitle') }}
            </flux:text>
        </header>

        <x-ui.card :title="__('welcome.sections.summary_cards')">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.stat-card :label="__('welcome.stat.ttc')" value="2 450 000,00" :context="__('welcome.stat.today')" />
                <x-ui.stat-card :label="__('welcome.stat.ht')" value="2 071 186,44" :context="__('welcome.stat.today')" />
                <x-ui.stat-card :label="__('welcome.stat.vat')" value="378 813,56" :context="__('welcome.stat.today')" />
                <x-ui.stat-card :label="__('welcome.stat.unique_records')" value="1 284" :accent="true" :context="__('welcome.stat.active_center')" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('welcome.sections.buttons')">
            <div class="flex flex-wrap gap-3">
                <x-ui.button variant="primary" icon="shield-check">{{ __('welcome.buttons.verify') }}</x-ui.button>
                <x-ui.button variant="primary" icon="check-circle">{{ __('welcome.buttons.import') }}</x-ui.button>
                <x-ui.button variant="secondary" icon="arrow-down-tray">{{ __('welcome.buttons.export') }}</x-ui.button>
                <x-ui.button variant="approval" icon="check-badge">{{ __('welcome.buttons.approve_revision') }}</x-ui.button>
                <x-ui.button variant="destructive-outline" icon="x-circle">{{ __('welcome.buttons.reject') }}</x-ui.button>
                <x-ui.button variant="destructive" icon="trash">{{ __('welcome.buttons.delete') }}</x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('welcome.sections.status_badges')">
            <div class="flex flex-wrap gap-2">
                <x-ui.status-badge status="success" icon="check-circle">{{ __('welcome.badges.imported') }}</x-ui.status-badge>
                <x-ui.status-badge status="warning" icon="exclamation-triangle">{{ __('welcome.badges.pending_review') }}</x-ui.status-badge>
                <x-ui.status-badge status="error" icon="x-circle">{{ __('welcome.badges.failed') }}</x-ui.status-badge>
                <x-ui.status-badge status="info" icon="information-circle">{{ __('welcome.badges.processing') }}</x-ui.status-badge>
                <x-ui.status-badge status="neutral">{{ __('welcome.badges.draft') }}</x-ui.status-badge>
            </div>
        </x-ui.card>

        <x-ui.table-panel
            :title="__('welcome.table.recent_imports')"
            :description="__('welcome.table.recent_imports_description')"
        >
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('welcome.table.date') }}</flux:table.column>
                    <flux:table.column>{{ __('welcome.table.file') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('welcome.stat.ttc') }}</flux:table.column>
                    <flux:table.column>{{ __('welcome.table.status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    <flux:table.row>
                        <flux:table.cell>2026-07-01</flux:table.cell>
                        <flux:table.cell>cashier-july-01.csv</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-money font-medium">2 450 000,00</flux:table.cell>
                        <flux:table.cell>
                            <x-ui.status-badge status="success">{{ __('welcome.badges.complete') }}</x-ui.status-badge>
                        </flux:table.cell>
                    </flux:table.row>
                    <flux:table.row>
                        <flux:table.cell>2026-06-30</flux:table.cell>
                        <flux:table.cell>cashier-june-30.csv</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-money font-medium">2 118 400,00</flux:table.cell>
                        <flux:table.cell>
                            <x-ui.status-badge status="warning">{{ __('welcome.badges.revision_pending') }}</x-ui.status-badge>
                        </flux:table.cell>
                    </flux:table.row>
                </flux:table.rows>
            </flux:table>
        </x-ui.table-panel>

        <x-ui.card :title="__('welcome.sections.palette')">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div class="flex items-center gap-3">
                    <span class="size-10 shrink-0 rounded-md bg-midnight-navy"></span>
                    <span class="text-sm text-text-body">{{ __('welcome.palette.midnight_navy') }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="size-10 shrink-0 rounded-md bg-emerald-brand"></span>
                    <span class="text-sm text-text-body">{{ __('welcome.palette.emerald_brand') }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="size-10 shrink-0 rounded-md bg-gold-brand"></span>
                    <span class="text-sm text-text-body">{{ __('welcome.palette.gold_brand') }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="size-10 shrink-0 rounded-md border border-slate-200 bg-app-bg"></span>
                    <span class="text-sm text-text-body">{{ __('welcome.palette.app_bg') }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="size-10 shrink-0 rounded-md border border-slate-200 bg-surface"></span>
                    <span class="text-sm text-text-body">{{ __('welcome.palette.surface') }}</span>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('welcome.sections.typography')">
            <p class="font-display text-xl font-semibold text-text-heading">{{ __('welcome.typography.display_heading') }}</p>
            <p class="mt-2 text-text-body">{{ __('welcome.typography.body_text') }}</p>
            <p class="tabular-money mt-2 text-2xl font-semibold text-emerald-brand">
                1 234 567,89 HT
            </p>
        </x-ui.card>

        <x-ui.card :title="__('welcome.sections.heroicons')">
            <flux:text class="mb-4 text-text-muted!">{{ __('welcome.heroicons.description') }}</flux:text>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ([
                    ['home', __('welcome.heroicons.dashboard')],
                    ['building-office-2', __('welcome.heroicons.centers')],
                    ['users', __('welcome.heroicons.users')],
                    ['banknotes', __('welcome.heroicons.cashier')],
                    ['arrow-up-tray', __('welcome.heroicons.csv_import')],
                    ['document-currency-dollar', __('welcome.heroicons.records')],
                    ['chart-bar-square', __('welcome.heroicons.reports')],
                    ['bell', __('welcome.heroicons.notifications')],
                    ['cog-6-tooth', __('welcome.heroicons.settings')],
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
