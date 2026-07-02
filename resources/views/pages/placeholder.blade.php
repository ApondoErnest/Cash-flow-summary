<x-layouts.app>
    <x-ui.page class="max-w-3xl">
        <flux:heading size="xl" class="font-display text-text-heading!">
            {{ __("pages.{$pageKey}") }}
        </flux:heading>
        <flux:text class="mt-2 text-text-muted!">
            {{ __('pages.placeholder_description') }}
        </flux:text>
    </x-ui.page>
</x-layouts.app>
