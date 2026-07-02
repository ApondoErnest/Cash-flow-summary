@props([
    'roleLabel' => '',
    'roleInitials' => '',
])

<flux:dropdown position="top" align="start" class="w-full max-lg:w-auto">
    <flux:sidebar.profile
        :name="$roleLabel"
        :initials="$roleInitials"
        class="mf-sidebar-profile"
    />

    <flux:menu class="min-w-48">
        <flux:menu.heading>{{ $roleLabel }}</flux:menu.heading>
        <flux:menu.separator />

        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item
                type="submit"
                icon="arrow-right-start-on-rectangle"
                variant="danger"
                class="w-full cursor-pointer"
            >
                {{ __('auth.logout') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
