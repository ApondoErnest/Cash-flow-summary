<div class="relative z-20 flex justify-end pb-2 lg:absolute lg:end-6 lg:top-6 lg:pb-0">
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <flux:button
            type="submit"
            variant="ghost"
            size="sm"
            icon="arrow-right-start-on-rectangle"
            class="mf-auth-logout !text-text-muted hover:!text-text-heading"
        >
            {{ __('auth.logout') }}
        </flux:button>
    </form>
</div>
