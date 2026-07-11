<div class="mf-app-shell min-h-dvh w-full">
    <flux:sidebar
        collapsible="mobile"
        class="midnight-sidebar mf-sidebar !border-e-0"
    >
        <flux:sidebar.header class="mf-sidebar-header">
            <flux:sidebar.brand
                href="{{ route('dashboard') }}"
                name="{{ config('app.name') }}"
                class="mf-sidebar-brand"
                wire:navigate
            >
                <x-slot:logo>
                    <div class="mf-sidebar-brand-mark" aria-hidden="true">
                        <x-brand.icon size="sm" class="!size-full !rounded-[inherit]" />
                    </div>
                </x-slot:logo>
            </flux:sidebar.brand>
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <x-navigation.sidebar :shell="$shell" />
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <x-navigation.user-menu
            :role-label="$shell->role->label()"
            :role-initials="$shell->role->initials()"
        />
    </flux:sidebar>

    <flux:header class="mf-app-header">
        <div class="mf-header-inner">
            <flux:sidebar.toggle class="mf-header-mobile-toggle lg:hidden" icon="bars-3" />

            @if ($shell->showsCenterSwitcher)
                <livewire:centers.center-switcher />
            @else
                <div class="mf-header-center">
                    <div class="mf-header-center-mark" aria-hidden="true">
                        <flux:icon icon="building-office-2" variant="outline" class="size-4" />
                    </div>
                    <div class="min-w-0">
                        <p class="mf-header-center-label">{{ $shell->centerLabel }}</p>
                        <p class="mf-header-center-name">{{ $shell->centerName }}</p>
                    </div>
                </div>
            @endif

            <div class="mf-header-toolbar" data-mf-header-toolbar>
                <livewire:language-switcher />

                <span class="mf-header-toolbar-divider" aria-hidden="true"></span>

                <form method="POST" action="{{ route('logout') }}" class="mf-header-logout-form">
                    @csrf
                    <flux:button
                        type="submit"
                        variant="ghost"
                        size="sm"
                        icon="arrow-right-start-on-rectangle"
                        class="mf-header-tool-btn mf-header-tool-btn--logout"
                        :aria-label="__('auth.logout')"
                    >
                        <span class="hidden md:inline">{{ __('auth.logout') }}</span>
                    </flux:button>
                </form>
            </div>
        </div>
    </flux:header>

    <flux:main class="mf-app-main !bg-app-bg !p-4 sm:!p-6 lg:!p-8">
        {{ $slot }}
    </flux:main>
</div>
