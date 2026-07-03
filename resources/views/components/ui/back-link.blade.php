@props([
    'href' => null,
    'icon' => 'arrow-left',
    'spaNavigate' => false,
])

@php
    $linkAttributes = $attributes
        ->class('mf-back-link')
        ->merge($spaNavigate ? ['wire:navigate' => true] : []);
@endphp

<a href="{{ $href }}" {{ $linkAttributes }}>
    <flux:icon :icon="$icon" variant="mini" class="mf-back-link__icon" />
    <span>{{ $slot }}</span>
</a>
