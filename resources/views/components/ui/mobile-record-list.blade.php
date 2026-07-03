@props([
    'label' => null,
])

<div {{ $attributes->class('mf-mobile-record-list md:hidden') }} data-mf-mobile-record-list>
    @if ($label)
        <p class="mf-mobile-record-list__label">{{ $label }}</p>
    @endif

    {{ $slot }}
</div>
