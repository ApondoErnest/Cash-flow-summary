@props([
    'size' => 'md',
])

@php
    $class = match ($size) {
        'sm' => 'size-8',
        'lg' => 'size-14',
        'xl' => 'size-16',
        default => 'size-10',
    };
@endphp

<img
    {{ $attributes->class([$class, 'mf-brand-icon shrink-0 rounded-[22%]']) }}
    src="{{ asset('brand/verified-cash-shield.svg') }}"
    width="64"
    height="64"
    alt=""
    decoding="async"
/>
