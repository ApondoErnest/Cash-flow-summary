@props([
    'title' => null,
    'description' => null,
])

<div
    {{ $attributes->merge(['class' => 'mf-table-panel overflow-visible rounded-lg border border-slate-200 bg-surface shadow-sm']) }}
    data-mf-table-panel
>
    @if ($title)
        <div class="mf-table-panel__header border-b border-slate-200 px-4 py-3 sm:px-6">
            <flux:heading size="lg" class="font-display text-text-heading!">{{ $title }}</flux:heading>
            @if ($description)
                <flux:text class="mt-1 text-text-muted!">{{ $description }}</flux:text>
            @endif
        </div>
    @endif

    @isset($filters)
        <div class="mf-table-panel__filters overflow-visible border-b border-slate-200/80 bg-gradient-to-b from-slate-50/95 to-white px-4 py-4 sm:px-6">
            {{ $filters }}
        </div>
    @endisset

    <div class="mf-table-panel__body overflow-x-auto overflow-y-visible px-4 py-3 sm:px-6">
        {{ $slot }}
    </div>
</div>
