@props([
    'title' => null,
    'compact' => false,
])

<section
    {{ $attributes->merge([
        'class' => 'mf-card rounded-lg border border-slate-200 bg-surface shadow-sm ' . ($compact ? 'p-4' : 'p-6'),
    ]) }}
    data-mf-card
>
    @if ($title)
        <flux:heading size="lg" class="mb-4 font-display text-text-heading!">
            {{ $title }}
        </flux:heading>
    @endif

    {{ $slot }}
</section>
