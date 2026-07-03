<div class="mf-center-selection-page relative flex min-h-dvh flex-col lg:flex-row">
    <div class="mf-center-selection-toolbar relative z-20 flex items-center justify-end gap-2 px-6 pt-4 lg:absolute lg:end-6 lg:top-6 lg:px-0 lg:pt-0">
        <livewire:language-switcher />

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button
                type="submit"
                variant="ghost"
                size="sm"
                icon="arrow-right-start-on-rectangle"
                class="mf-auth-logout !text-text-muted hover:!text-text-heading"
            >
                <span class="hidden sm:inline">{{ __('auth.logout') }}</span>
            </flux:button>
        </form>
    </div>

    <x-authentication.brand-panel
        :heading="__('center.selection.brand_heading')"
        :description="__('center.selection.brand_description')"
        class="mf-center-selection-brand"
    >
        @if ($canReturnToDashboard)
            <x-slot:actions>
                <a href="{{ route('dashboard') }}" class="mf-center-selection-dashboard-btn">
                    <flux:icon icon="arrow-left" variant="mini" class="size-4 shrink-0" />
                    <span>{{ __('center.selection.back_to_dashboard') }}</span>
                </a>
            </x-slot:actions>
        @endif
    </x-authentication.brand-panel>

    <main class="mf-center-selection-main relative flex flex-1 items-center justify-center px-6 py-10 sm:px-10 lg:px-14">
        <div class="mf-center-selection-panel w-full max-w-xl rounded-2xl p-8 sm:p-10" data-mf-center-selection-panel>
            <header class="mb-8">
                @if ($statusMessage)
                    <flux:callout variant="warning" class="mb-6" icon="exclamation-triangle">
                        {{ $statusMessage }}
                    </flux:callout>
                @endif

                <div class="mf-center-selection-icon-wrap" aria-hidden="true">
                    <flux:icon icon="building-office-2" variant="outline" class="size-6 text-emerald-brand" />
                </div>

                <flux:heading size="lg" class="mt-5 font-display text-text-heading!">
                    {{ __('center.selection.title') }}
                </flux:heading>
                <flux:text class="mt-2 text-text-muted!">
                    {{ __('center.selection.description') }}
                </flux:text>
            </header>

            @if ($hasCenters)
                <div class="space-y-5">
                    <flux:field>
                        <flux:label>{{ __('center.selection.search_label') }}</flux:label>
                        <flux:input
                            wire:model.live.debounce.200ms="search"
                            icon="magnifying-glass"
                            :placeholder="__('center.selection.search_placeholder')"
                            autocomplete="off"
                        />
                    </flux:field>

                    <div class="mf-center-selection-list" role="listbox" aria-label="{{ __('center.selection.title') }}">
                        @forelse ($this->centers as $center)
                            @php($selected = (int) $centerId === (int) $center->id)
                            <button
                                type="button"
                                wire:key="center-option-{{ $center->id }}"
                                wire:click="selectCenter({{ $center->id }})"
                                @class([
                                    'mf-center-selection-option',
                                    'mf-center-selection-option--selected' => $selected,
                                ])
                                role="option"
                                aria-selected="{{ $selected ? 'true' : 'false' }}"
                            >
                                <span class="mf-center-selection-option-mark" aria-hidden="true">
                                    <flux:icon icon="building-office-2" variant="outline" class="size-5" />
                                </span>

                                <span class="min-w-0 flex-1 text-start">
                                    <span class="mf-center-selection-option-title">{{ $center->name }}</span>
                                    <span class="mf-center-selection-option-meta">
                                        @if ($center->code)
                                            <span>{{ $center->code }}</span>
                                        @endif
                                        @if ($center->city)
                                            <span>{{ $center->city }}</span>
                                        @endif
                                    </span>
                                </span>

                                @if ($selected)
                                    <flux:icon icon="check-circle" variant="solid" class="size-5 shrink-0 text-emerald-brand" />
                                @endif
                            </button>
                        @empty
                            <div class="mf-center-selection-empty-search rounded-xl border border-dashed border-slate-200 bg-white/70 px-4 py-8 text-center">
                                <flux:text class="text-text-muted!">
                                    {{ __('center.selection.no_search_results') }}
                                </flux:text>
                            </div>
                        @endforelse
                    </div>

                    <flux:error name="centerId" />

                    <flux:field variant="inline">
                        <flux:checkbox wire:model="rememberAsDefault" />
                        <flux:label>{{ __('center.selection.remember_default') }}</flux:label>
                        <flux:description>{{ __('center.selection.remember_default_help') }}</flux:description>
                    </flux:field>

                    <x-ui.button
                        variant="primary"
                        type="button"
                        icon="arrow-right-circle"
                        class="mf-center-selection-submit w-full justify-center"
                        wire:click="openCenter"
                        wire:loading.attr="disabled"
                        wire:target="openCenter"
                        :disabled="$centerId === null"
                    >
                        <span wire:loading.remove wire:target="openCenter">{{ __('center.selection.open_center') }}</span>
                        <span wire:loading wire:target="openCenter">{{ __('center.selection.opening') }}</span>
                    </x-ui.button>

                    @if ($canReturnToDashboard)
                        <x-ui.button
                            variant="secondary"
                            icon="arrow-left"
                            href="{{ route('dashboard') }}"
                            class="mf-center-selection-back w-full justify-center"
                        >
                            {{ __('center.selection.back_to_dashboard') }}
                        </x-ui.button>
                    @endif

                    @if ($centerCount > 1)
                        <flux:text class="text-center text-sm text-text-muted!">
                            {{ trans_choice('center.selection.center_count', $centerCount, ['count' => $centerCount]) }}
                        </flux:text>
                    @endif
                </div>
            @else
                <div class="mf-center-selection-empty rounded-2xl border border-dashed border-slate-200 bg-white/60 px-6 py-10 text-center">
                    <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-midnight-navy/5 text-midnight-navy">
                        <flux:icon icon="building-office-2" variant="outline" class="size-7" />
                    </div>

                    <flux:heading size="md" class="font-display text-text-heading!">
                        {{ __('center.selection.empty_title') }}
                    </flux:heading>
                    <flux:text class="mx-auto mt-2 max-w-sm text-text-muted!">
                        {{ __('center.selection.empty_description') }}
                    </flux:text>

                    <flux:button
                        variant="primary"
                        icon="plus-circle"
                        href="{{ route('centers.index') }}"
                        class="mf-btn-primary mf-center-selection-submit mt-8 w-full justify-center sm:w-auto"
                    >
                        {{ __('center.selection.create_center') }}
                    </flux:button>

                    @if ($canReturnToDashboard)
                        <x-ui.button
                            variant="secondary"
                            icon="arrow-left"
                            href="{{ route('dashboard') }}"
                            class="mf-center-selection-back mt-4 w-full justify-center sm:mx-auto sm:w-auto"
                        >
                            {{ __('center.selection.back_to_dashboard') }}
                        </x-ui.button>
                    @endif
                </div>
            @endif
        </div>
    </main>
</div>
