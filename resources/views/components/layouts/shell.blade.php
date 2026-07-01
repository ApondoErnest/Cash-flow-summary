<div class="mf-app-shell min-h-dvh w-full">
    <flux:sidebar
        collapsible="mobile"
        sticky
        class="midnight-sidebar !border-e !border-white/10"
    >
        <flux:sidebar.header>
            <flux:sidebar.brand
                href="{{ route('dashboard') }}"
                name="{{ config('app.name') }}"
                class="!text-white"
            />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <x-navigation.sidebar :shell="$shell" />
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <flux:sidebar.profile
            :name="$shell->role->label()"
            :initials="$shell->role->initials()"
        />
    </flux:sidebar>

    <flux:header class="mf-app-header !border-b !border-slate-200 !bg-surface shadow-sm">
        <div class="flex w-full items-center gap-3 sm:gap-4">
            <flux:sidebar.toggle class="lg:hidden shrink-0" icon="bars-3" />

            <div class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase tracking-wide text-text-muted">{{ $shell->centerLabel }}</p>
                    <p class="truncate font-display text-sm font-semibold text-text-heading sm:text-base">
                        {{ $shell->centerName }}
                    </p>
                </div>

                <div class="mf-header-actions flex shrink-0 items-center gap-1 sm:gap-2">
                    @if ($shell->showsCenterSwitcher)
                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="building-office-2"
                            class="max-lg:aspect-square max-lg:w-10 max-lg:px-0"
                            aria-label="Switch center"
                        >
                            <span class="hidden lg:inline">Switch center</span>
                        </flux:button>
                    @endif
                    <flux:button variant="ghost" size="sm" icon="bell" square aria-label="Notifications" />
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="user-circle"
                        class="max-sm:aspect-square max-sm:w-10 max-sm:px-0"
                        aria-label="{{ $shell->role->label() }}"
                    >
                        <span class="hidden sm:inline">{{ $shell->role->label() }}</span>
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:header>

    <flux:main class="mf-app-main !bg-app-bg !p-4 sm:!p-6 lg:!p-8">
        {{ $slot }}
    </flux:main>
</div>
