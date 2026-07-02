@if ($activeCenter !== null)
    <flux:dropdown position="bottom" align="start" class="mf-header-center-dropdown min-w-0 flex-1">
        <button
            type="button"
            class="mf-header-center mf-header-center--switchable w-full text-start"
            aria-haspopup="listbox"
            aria-label="{{ __('navigation.shell.switch_center') }}"
        >
            <span class="mf-header-center-mark" aria-hidden="true">
                <flux:icon icon="building-office-2" variant="outline" class="size-4" />
            </span>

            <span class="min-w-0 flex-1">
                <span class="mf-header-center-label">{{ __('navigation.shell.active_center') }}</span>
                <span class="mf-header-center-name">{{ $activeCenter->centerName }}</span>
            </span>

            <flux:icon icon="chevron-down" variant="mini" class="mf-header-center-chevron size-4 shrink-0" />
        </button>

        <flux:menu class="mf-header-center-menu min-w-72">
            <flux:menu.heading>{{ __('center.switcher.title') }}</flux:menu.heading>
            <flux:menu.separator />

            @foreach ($centers as $center)
                @php($isActive = (int) $activeCenter->centerId === (int) $center->id)
                <flux:menu.item
                    type="button"
                    wire:click="switchCenter({{ $center->id }})"
                    wire:key="switch-center-{{ $center->id }}"
                    icon="{{ $isActive ? 'check-circle' : 'building-office-2' }}"
                    class="{{ $isActive ? 'mf-header-center-menu-item--active' : '' }}"
                >
                    <span class="block min-w-0 text-start">
                        <span class="block truncate font-medium">{{ $center->name }}</span>
                        @if ($center->code || $center->city)
                            <span class="block truncate text-xs text-text-muted">
                                {{ collect([$center->code, $center->city])->filter()->implode(' · ') }}
                            </span>
                        @endif
                    </span>
                </flux:menu.item>
            @endforeach

            <flux:menu.separator />

            <flux:menu.item
                :href="route('center.select')"
                icon="arrow-path"
                wire:navigate
            >
                {{ __('center.switcher.open_selection') }}
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>
@else
    <a
        href="{{ route('center.select') }}"
        wire:navigate
        class="mf-header-center mf-header-center--prompt w-full"
    >
        <span class="mf-header-center-mark" aria-hidden="true">
            <flux:icon icon="building-office-2" variant="outline" class="size-4" />
        </span>

        <span class="min-w-0 flex-1">
            <span class="mf-header-center-label">{{ __('navigation.shell.active_center') }}</span>
            <span class="mf-header-center-name">{{ __('navigation.shell.no_active_center') }}</span>
        </span>

        <flux:icon icon="arrow-right-circle" variant="outline" class="size-5 shrink-0 text-emerald-brand" />
    </a>
@endif
