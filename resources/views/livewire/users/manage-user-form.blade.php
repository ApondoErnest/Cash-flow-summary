<x-ui.page>
    <header class="space-y-2">
        <flux:button
            variant="ghost"
            size="sm"
            icon="arrow-left"
            href="{{ route('users.index') }}"
            wire:navigate
            class="!px-0"
        >
            {{ __('user.manage.back_to_list') }}
        </flux:button>

        <flux:heading size="xl" class="font-display text-text-heading!">
            {{ $isEditing ? __('user.manage.edit_title') : __('user.manage.create_title') }}
        </flux:heading>
        <flux:text class="text-text-muted!">
            {{ $isEditing ? __('user.manage.edit_description') : __('user.manage.create_description') }}
        </flux:text>
    </header>

    <x-ui.card>
        @if ($isOwnerAccount)
            <flux:callout variant="info" icon="information-circle" class="mb-6">
                {{ __('user.manage.owner_account_note') }}
            </flux:callout>
        @endif

        <form wire:submit="save" class="mf-manage-user-form space-y-6">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('user.manage.fields.name') }}</flux:label>
                    <flux:input wire:model="name" required autocomplete="name" />
                    <flux:error name="name" />
                </flux:field>

                @unless ($isOwnerAccount)
                    <flux:field>
                        <flux:label>{{ __('user.manage.fields.username') }}</flux:label>
                        <flux:input wire:model="username" required autocomplete="off" />
                        <flux:error name="username" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('user.manage.fields.role') }}</flux:label>
                        <flux:select wire:model="role">
                            @foreach ($assignableRoles as $assignableRole)
                                <flux:select.option value="{{ $assignableRole }}">
                                    {{ $assignableRole === \App\Support\Auth\RoleName::CenterManager ? __('roles.manager') : __('roles.cashier') }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="role" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>{{ __('user.manage.fields.center') }}</flux:label>
                        <flux:select wire:model="centerId">
                            @foreach ($this->centers as $center)
                                <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:description>{{ __('user.manage.fields.center_help') }}</flux:description>
                        <flux:error name="centerId" />
                    </flux:field>
                @endunless

                <flux:field>
                    <flux:label>{{ __('user.manage.fields.phone') }}</flux:label>
                    <flux:input wire:model="phone" type="tel" autocomplete="tel" />
                    <flux:error name="phone" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('user.manage.fields.email') }}</flux:label>
                    <flux:input wire:model="email" type="email" autocomplete="email" />
                    <flux:error name="email" />
                </flux:field>
            </div>

            @if ($isEditing && ! $isOwnerAccount)
                <flux:field variant="inline">
                    <flux:switch wire:model="is_active" />
                    <flux:label>{{ __('user.manage.fields.is_active') }}</flux:label>
                    <flux:description>{{ __('user.manage.fields.is_active_help') }}</flux:description>
                </flux:field>
            @endif

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-6">
                <x-ui.button
                    variant="primary"
                    type="submit"
                    icon="{{ $isEditing ? 'check-circle' : 'plus-circle' }}"
                    wire:loading.attr="disabled"
                    wire:target="save"
                >
                    <span wire:loading.remove wire:target="save">
                        {{ $isEditing ? __('user.manage.save') : __('user.manage.create') }}
                    </span>
                    <span wire:loading wire:target="save">{{ __('user.manage.saving') }}</span>
                </x-ui.button>

                <flux:button variant="ghost" href="{{ route('users.index') }}" wire:navigate>
                    {{ __('user.manage.cancel') }}
                </flux:button>
            </div>
        </form>
    </x-ui.card>
</x-ui.page>
