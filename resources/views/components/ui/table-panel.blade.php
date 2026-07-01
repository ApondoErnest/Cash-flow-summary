@props([
    'title' => null,
    'description' => null,
])

<div
    {{ $attributes->merge(['class' => 'mf-table-panel overflow-hidden rounded-lg border border-slate-200 bg-surface shadow-sm']) }}
    data-mf-table-panel
>
    @if ($title)
        <div class="border-b border-slate-200 px-4 py-3 sm:px-6">
            <flux:heading size="lg" class="font-display text-text-heading!">{{ $title }}</flux:heading>
            @if ($description)
                <flux:text class="mt-1 text-text-muted!">{{ $description }}</flux:text>
            @endif
        </div>
    @endif

    <div class="overflow-x-auto px-4 py-3 sm:px-6">
        <flux:table>{{ $slot }}</flux:table>
    </div>
</div>
