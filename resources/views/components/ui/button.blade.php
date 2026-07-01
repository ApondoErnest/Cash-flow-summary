@props([
    'variant' => 'primary',
    'icon' => null,
    'type' => 'button',
])

@php
    $fluxVariant = match ($variant) {
        'primary' => 'primary',
        'secondary' => 'outline',
        'destructive' => 'danger',
        'destructive-outline' => 'outline',
        default => 'primary',
    };

    $extraClass = match ($variant) {
        'secondary' => 'mf-btn-secondary',
        'approval' => 'mf-btn-approval',
        'destructive-outline' => 'mf-btn-destructive-outline',
        default => 'mf-btn-' . $variant,
    };
@endphp

@if ($variant === 'approval')
    <flux:button
        :type="$type"
        :icon="$icon"
        {{ $attributes->merge(['class' => $extraClass]) }}
        data-mf-button="approval"
    >
        {{ $slot }}
    </flux:button>
@else
    <flux:button
        :type="$type"
        :variant="$fluxVariant"
        :icon="$icon"
        {{ $attributes->merge(['class' => $extraClass]) }}
        data-mf-button="{{ $variant }}"
    >
        {{ $slot }}
    </flux:button>
@endif
