<x-ui.page>
    <header class="space-y-2">
        <flux:heading size="xl" class="font-display text-text-heading!">
            {{ __('dashboard.staff.title') }}
        </flux:heading>
        <flux:text class="text-text-muted!">
            {{ __('dashboard.staff.subtitle', ['center' => $centerName ?? '—']) }}
        </flux:text>
    </header>

    <x-ui.card :title="__('dashboard.staff.placeholder_title')">
        <flux:text class="text-text-muted!">
            {{ __('dashboard.staff.placeholder_description') }}
        </flux:text>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-ui.button
                variant="primary"
                icon="arrow-up-tray"
                href="{{ route('imports.create') }}"
                wire:navigate
            >
                {{ __('dashboard.actions.import_csv') }}
            </x-ui.button>
            <x-ui.button
                variant="secondary"
                icon="queue-list"
                href="{{ route('imports.index') }}"
                wire:navigate
            >
                {{ __('dashboard.actions.view_imports') }}
            </x-ui.button>
        </div>
    </x-ui.card>
</x-ui.page>
