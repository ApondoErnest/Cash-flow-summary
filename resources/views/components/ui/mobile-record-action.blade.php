@props([
    'variant' => 'secondary',
    'icon' => null,
    'href' => null,
    'type' => 'button',
])

@php
    $actionClass = match ($variant) {
        'primary' => 'mf-btn-primary mf-mobile-record-card__action mf-mobile-record-card__action--primary',
        'danger' => 'mf-mobile-record-card__action mf-mobile-record-card__action--danger',
        default => 'mf-mobile-record-card__action mf-mobile-record-card__action--secondary',
    };

    $fluxVariant = match ($variant) {
        'primary' => 'primary',
        'danger' => 'danger',
        default => 'ghost',
    };
@endphp

@if ($variant === 'danger')
    <x-ui.button
        :variant="'destructive-outline'"
        size="sm"
        :icon="$icon"
        :type="$type"
        :href="$href"
        {{ $attributes->merge(['class' => $actionClass]) }}
    >
        {{ $slot }}
    </x-ui.button>
@else
    <flux:button
        :variant="$fluxVariant"
        size="sm"
        :icon="$icon"
        :type="$type"
        :href="$href"
        {{ $attributes->merge(['class' => $actionClass]) }}
    >
        {{ $slot }}
    </flux:button>
@endif
