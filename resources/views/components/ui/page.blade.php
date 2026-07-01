@props(['wide' => false])

<div
    @class([
        'mf-page mx-auto flex w-full flex-col gap-6 sm:gap-8',
        'max-w-5xl' => ! $wide,
        'max-w-7xl' => $wide,
    ])
    {{ $attributes }}
>
    {{ $slot }}
</div>
