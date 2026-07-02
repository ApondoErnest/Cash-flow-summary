@props([
    'heading' => null,
    'description' => null,
])

<aside
    {{ $attributes->class([
        'mf-login-brand relative flex flex-col justify-between overflow-hidden px-8 py-10 text-white lg:w-[44%] lg:px-12 lg:py-14',
    ]) }}
    data-mf-login-brand
>
    <div class="mf-login-brand-grid pointer-events-none" aria-hidden="true"></div>
    <div class="mf-login-brand-glow pointer-events-none" aria-hidden="true"></div>
    <div class="mf-login-brand-glow mf-login-brand-glow--gold pointer-events-none" aria-hidden="true"></div>

    <div class="mf-login-brand-content relative z-10">
        <div class="mf-login-brand-mark" aria-hidden="true">
            <flux:icon icon="chart-bar-square" variant="outline" class="size-7 text-white" />
        </div>

        <div class="mf-login-brand-eyebrow">
            <span class="mf-login-brand-eyebrow-line" aria-hidden="true"></span>
            <span>Midnight Finance</span>
        </div>

        @if ($heading)
            <h1 class="mf-login-brand-title">{{ $heading }}</h1>

            @if ($description)
                <p class="mf-login-brand-lead">{{ $description }}</p>
            @endif
        @else
            <h1 class="mf-login-brand-title">{{ config('app.name') }}</h1>

            <p class="mf-login-brand-lead">{{ __('auth.brand_tagline_lead') }}</p>

            <div class="mf-login-brand-roles">
                <p class="mf-login-brand-roles-label">{{ __('auth.brand_tagline_roles') }}</p>
                <ul class="mf-login-brand-role-list" role="list">
                    <li>
                        <span class="mf-login-brand-role-pill mf-login-brand-role-pill--owner">
                            {{ __('roles.owner') }}
                        </span>
                    </li>
                    <li>
                        <span class="mf-login-brand-role-pill mf-login-brand-role-pill--manager">
                            {{ __('roles.manager') }}
                        </span>
                    </li>
                    <li>
                        <span class="mf-login-brand-role-pill mf-login-brand-role-pill--cashier">
                            {{ __('roles.cashier') }}
                        </span>
                    </li>
                </ul>
            </div>
        @endif
    </div>

    <p class="mf-login-brand-footer relative z-10">
        {{ __('auth.brand_footer') }}
    </p>
</aside>
