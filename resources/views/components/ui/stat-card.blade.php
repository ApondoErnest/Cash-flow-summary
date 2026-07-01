@props([
    'label',
    'value',
    'context' => null,
    'accent' => false,
])

<x-ui.card compact {{ $attributes->merge(['class' => 'mf-stat-card']) }} data-mf-stat-card>
    <p class="text-sm font-medium text-text-muted">{{ $label }}</p>
    <p @class([
        'tabular-money mt-1 text-2xl font-semibold',
        'text-gold-brand' => $accent,
        'text-text-heading' => ! $accent,
    ])>
        {{ $value }}
    </p>
    @if ($context)
        <p class="mt-1 text-xs text-text-muted">{{ $context }}</p>
    @endif
</x-ui.card>
