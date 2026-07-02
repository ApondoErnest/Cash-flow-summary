@props(['shell'])

@foreach ($shell->navigationGroups as $group)
    <flux:sidebar.group heading="{{ $group->heading }}" class="mf-sidebar-group">
        @foreach ($group->items as $item)
            <flux:sidebar.item
                :icon="$item->icon"
                :href="route($item->routeName)"
                :current="request()->routeIs($item->routeName)"
                class="mf-sidebar-item"
                wire:navigate
            >
                {{ $item->label }}
            </flux:sidebar.item>
        @endforeach
    </flux:sidebar.group>
@endforeach
