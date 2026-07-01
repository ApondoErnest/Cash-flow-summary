@props([
    'status' => 'neutral',
    'icon' => null,
])

@php
    $color = match ($status) {
        'success' => 'green',
        'warning' => 'amber',
        'error' => 'red',
        'info' => 'blue',
        'neutral' => 'zinc',
        default => 'zinc',
    };
@endphp

<flux:badge
    :color="$color"
    :icon="$icon"
    size="sm"
    {{ $attributes->merge(['class' => 'mf-status-badge mf-status-' . $status]) }}
    data-mf-status-badge="{{ $status }}"
>
    {{ $slot }}
</flux:badge>
