<div
    class="mf-locale-switcher"
    data-mf-language-switcher
    role="group"
    aria-label="{{ __('locale.language') }}"
>
    @foreach ($supported as $code)
        <button
            type="button"
            wire:click="switch('{{ $code }}')"
            wire:key="locale-{{ $code }}"
            @class([
                'mf-locale-switcher-btn',
                'mf-locale-switcher-btn--active' => $current === $code,
            ])
            aria-pressed="{{ $current === $code ? 'true' : 'false' }}"
            aria-label="{{ __('locale.switch_to', ['language' => __('locale.' . ($code === 'en' ? 'english' : 'french'))]) }}"
        >
            {{ strtoupper($code) }}
        </button>
    @endforeach
</div>
