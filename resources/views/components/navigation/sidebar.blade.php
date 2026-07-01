@props(['shell'])

@foreach ($shell->navigationGroups as $group)
    <flux:sidebar.group heading="{{ $group->heading }}" class="midnight-sidebar-group">
        @foreach ($group->items as $item)
            <flux:sidebar.item
                :icon="$item->icon"
                :href="route($item->routeName)"
                :current="request()->routeIs($item->routeName)"
                x-on:click="window.matchMedia('(max-width: 1023px)').matches && $dispatch('flux-sidebar-toggle')"
            >
                {{ $item->label }}
            </flux:sidebar.item>
        @endforeach
    </flux:sidebar.group>
@endforeach
