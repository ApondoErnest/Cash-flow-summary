@props([
    'label' => null,
    'span' => 3,
    'srOnlyLabel' => false,
])

<div
    {{ $attributes->class([
        'mf-filter-field',
        'mf-filter-field--span-' . $span => $span >= 1 && $span <= 12,
    ]) }}
    data-mf-filter-field
>
    @if ($label)
        <label @class(['mf-filter-field__label', 'sr-only' => $srOnlyLabel])>{{ $label }}</label>
    @endif

    <div class="mf-filter-field__control">
        {{ $slot }}
    </div>
</div>
