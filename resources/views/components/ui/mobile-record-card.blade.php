@props([
    'title' => '',
    'subtitle' => null,
    'icon' => 'building-office-2',
])

<article {{ $attributes->class('mf-mobile-record-card') }} data-mf-mobile-record-card>
    <div class="mf-mobile-record-card__accent" aria-hidden="true"></div>

    <div class="mf-mobile-record-card__top">
        <span class="mf-mobile-record-card__mark" aria-hidden="true">
            <flux:icon :icon="$icon" variant="outline" class="size-5" />
        </span>

        <div class="mf-mobile-record-card__heading min-w-0 flex-1">
            <div class="mf-mobile-record-card__title-row">
                <h3 class="mf-mobile-record-card__title">{{ $title }}</h3>
                @isset($aside)
                    <div class="mf-mobile-record-card__aside shrink-0">
                        {{ $aside }}
                    </div>
                @endisset
            </div>

            @if ($subtitle)
                <p class="mf-mobile-record-card__subtitle">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @isset($details)
        <dl class="mf-mobile-record-card__details">
            {{ $details }}
        </dl>
    @endisset

    @isset($actions)
        <div class="mf-mobile-record-card__actions">
            {{ $actions }}
        </div>
    @endisset
</article>
