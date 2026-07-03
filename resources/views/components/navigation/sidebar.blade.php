@props(['shell'])

@foreach ($shell->navigationGroups as $group)
    <flux:sidebar.group heading="{{ $group->heading }}" class="mf-sidebar-group">
        @foreach ($group->items as $item)
            @php
                $routePattern = match ($item->routeName) {
                    'centers.index' => 'centers.*',
                    'users.index' => 'users.*',
                    'security.index' => 'security.*',
                    default => $item->routeName,
                };
            @endphp
            @if ($item->spaNavigate)
                <flux:sidebar.item
                    :icon="$item->icon"
                    :href="route($item->routeName)"
                    :current="request()->routeIs($routePattern)"
                    class="mf-sidebar-item"
                    wire:navigate
                >
                    {{ $item->label }}
                </flux:sidebar.item>
            @else
                <flux:sidebar.item
                    :icon="$item->icon"
                    :href="route($item->routeName)"
                    :current="request()->routeIs($routePattern)"
                    class="mf-sidebar-item"
                >
                    {{ $item->label }}
                </flux:sidebar.item>
            @endif
        @endforeach
    </flux:sidebar.group>
@endforeach
