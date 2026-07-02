<x-ui.page wide>
    <header class="mf-manage-users-header flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 space-y-2">
            <flux:heading size="xl" class="font-display text-text-heading!">
                {{ __('user.manage.title') }}
            </flux:heading>
            <flux:text class="text-text-muted!">
                {{ __('user.manage.description') }}
            </flux:text>
        </div>

        <x-ui.button
            variant="primary"
            icon="plus-circle"
            href="{{ route('users.create') }}"
            wire:navigate
            class="shrink-0"
        >
            {{ __('user.manage.create') }}
        </x-ui.button>
    </header>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('status') }}
        </flux:callout>
    @endif

    @php($sessionTemporary = session('temporary_password'))
    @if ($temporaryPassword || is_array($sessionTemporary))
        <flux:callout variant="warning" icon="key" class="mf-temporary-password-callout">
            <flux:heading size="sm" class="font-display">{{ __('user.manage.temporary_password_title') }}</flux:heading>
            <flux:text class="mt-1 text-text-muted!">{{ __('user.manage.temporary_password_help') }}</flux:text>
            <div class="mt-3 rounded-lg bg-white/80 px-4 py-3 font-mono text-sm text-text-heading">
                <div><span class="text-text-muted">{{ __('user.manage.fields.username') }}:</span> {{ $temporaryPasswordUsername ?? ($sessionTemporary['username'] ?? '') }}</div>
                <div class="mt-1"><span class="text-text-muted">{{ __('auth.password') }}:</span> {{ $temporaryPassword ?? ($sessionTemporary['password'] ?? '') }}</div>
            </div>
            @if ($temporaryPassword)
                <flux:button variant="ghost" size="sm" wire:click="dismissTemporaryPassword" class="mt-3">
                    {{ __('user.manage.temporary_password_dismiss') }}
                </flux:button>
            @endif
        </flux:callout>
    @endif

    <x-ui.table-panel
        :title="__('user.manage.table_title')"
        :description="__('user.manage.table_description')"
    >
        <x-slot:filters>
            <x-ui.filter-bar>
                <x-ui.filter-field :label="__('ui.filter.search')" :span="3">
                    <flux:input
                        wire:model.live.debounce.200ms="search"
                        icon="magnifying-glass"
                        :placeholder="__('user.manage.search_placeholder')"
                        autocomplete="off"
                        class="w-full"
                    />
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.center')" :span="3">
                    <flux:select wire:model.live="centerFilter" class="w-full">
                        <flux:select.option value="">{{ __('user.manage.filters.all_centers') }}</flux:select.option>
                        @foreach ($this->centers as $center)
                            <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.role')" :span="3">
                    <flux:select wire:model.live="roleFilter" class="w-full">
                        <flux:select.option value="">{{ __('user.manage.filters.all_roles') }}</flux:select.option>
                        <flux:select.option value="center_manager">{{ __('roles.manager') }}</flux:select.option>
                        <flux:select.option value="cashier">{{ __('roles.cashier') }}</flux:select.option>
                        <flux:select.option value="owner">{{ __('roles.owner') }}</flux:select.option>
                    </flux:select>
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.status')" :span="3">
                    <flux:select wire:model.live="statusFilter" class="w-full">
                        <flux:select.option value="all">{{ __('user.manage.filters.all_statuses') }}</flux:select.option>
                        <flux:select.option value="active">{{ __('user.manage.filters.active') }}</flux:select.option>
                        <flux:select.option value="inactive">{{ __('user.manage.filters.inactive') }}</flux:select.option>
                    </flux:select>
                </x-ui.filter-field>
            </x-ui.filter-bar>
        </x-slot:filters>

        @if ($this->users->isEmpty())
            <div class="mf-manage-users-empty rounded-xl border border-dashed border-slate-200 bg-white/70 px-6 py-12 text-center">
                <flux:heading size="md" class="font-display text-text-heading!">
                    {{ __('user.manage.empty_title') }}
                </flux:heading>
                <flux:text class="mx-auto mt-2 max-w-md text-text-muted!">
                    {{ __('user.manage.empty_description') }}
                </flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('user.manage.columns.name') }}</flux:table.column>
                    <flux:table.column>{{ __('user.manage.columns.username') }}</flux:table.column>
                    <flux:table.column>{{ __('user.manage.columns.role') }}</flux:table.column>
                    <flux:table.column>{{ __('user.manage.columns.center') }}</flux:table.column>
                    <flux:table.column>{{ __('user.manage.columns.status') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('user.manage.columns.actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->users as $user)
                        <flux:table.row wire:key="user-row-{{ $user->id }}">
                            <flux:table.cell class="font-medium text-text-heading">
                                {{ $user->name }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="font-mono text-sm">{{ $user->username }}</span>
                            </flux:table.cell>
                            <flux:table.cell>{{ $roleLabel($user) }}</flux:table.cell>
                            <flux:table.cell class="text-text-muted">
                                {{ $user->center?->name ?? '—' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($user->is_active)
                                    <x-ui.status-badge status="success" icon="check-circle">
                                        {{ __('user.manage.status.active') }}
                                    </x-ui.status-badge>
                                @else
                                    <x-ui.status-badge status="neutral" icon="minus-circle">
                                        {{ __('user.manage.status.inactive') }}
                                    </x-ui.status-badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="inline-flex flex-wrap justify-end gap-2">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="pencil-square"
                                        href="{{ route('users.edit', $user) }}"
                                        wire:navigate
                                    >
                                        {{ __('user.manage.actions.edit') }}
                                    </flux:button>

                                    @if ((int) auth()->id() !== (int) $user->id)
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="key"
                                            wire:click="resetPassword({{ $user->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="resetPassword"
                                        >
                                            {{ __('user.manage.actions.reset_password') }}
                                        </flux:button>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.table-panel>
</x-ui.page>
