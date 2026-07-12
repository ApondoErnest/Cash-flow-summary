<x-ui.page wide>
    <header class="mf-operating-calendar-header mf-page-header">
        <x-ui.back-link :href="route('centers.index')">
            {{ __('center.calendar.back_to_centers') }}
        </x-ui.back-link>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="mf-page-header__intro min-w-0">
                <flux:heading size="xl" class="font-display text-text-heading!">
                    {{ __('center.calendar.title', ['center' => $center->name]) }}
                </flux:heading>
                <flux:text class="text-text-muted!">
                    {{ __('center.calendar.description') }}
                </flux:text>
            </div>

            <x-ui.button
                variant="secondary"
                icon="pencil-square"
                href="{{ route('centers.edit', $center) }}"
                class="shrink-0"
            >
                {{ __('center.calendar.edit_center') }}
            </x-ui.button>
        </div>
    </header>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('status') }}
        </flux:callout>
    @endif

    <x-ui.card :title="__('center.calendar.weekly_title')" class="mf-operating-calendar-weekly">
        <flux:text class="mb-5 text-text-muted!">
            {{ __('center.calendar.weekly_description') }}
        </flux:text>

        <div class="space-y-3">
            @foreach ($displayDayOrder as $dayOfWeek)
                <div
                    wire:key="weekly-day-{{ $dayOfWeek }}"
                    class="mf-operating-calendar-day grid grid-cols-1 gap-3 rounded-xl border border-slate-200/80 bg-white/60 p-4 sm:grid-cols-[minmax(0,10rem)_auto_1fr_1fr] sm:items-center"
                >
                    <div class="font-display font-semibold text-text-heading">
                        {{ $dayLabel($dayOfWeek) }}
                    </div>

                    <flux:field variant="inline" class="sm:justify-self-start">
                        <flux:switch wire:model.live="weeklyDays.{{ $dayOfWeek }}.is_open" />
                        <flux:label>{{ __('center.calendar.fields.open') }}</flux:label>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('center.calendar.fields.open_time') }}</flux:label>
                        <x-ui.time-picker
                            wire:model="weeklyDays.{{ $dayOfWeek }}.open_time"
                            :disabled="! ($weeklyDays[$dayOfWeek]['is_open'] ?? false)"
                        />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('center.calendar.fields.close_time') }}</flux:label>
                        <x-ui.time-picker
                            wire:model="weeklyDays.{{ $dayOfWeek }}.close_time"
                            :disabled="! ($weeklyDays[$dayOfWeek]['is_open'] ?? false)"
                        />
                    </flux:field>
                </div>
            @endforeach
        </div>

        <div class="mt-6 border-t border-slate-200 pt-5">
            <x-ui.button
                variant="primary"
                type="button"
                icon="check-circle"
                wire:click="saveWeeklySchedule"
                wire:loading.attr="disabled"
                wire:target="saveWeeklySchedule"
            >
                <span wire:loading.remove wire:target="saveWeeklySchedule">{{ __('center.calendar.save_weekly') }}</span>
                <span wire:loading wire:target="saveWeeklySchedule">{{ __('center.calendar.saving') }}</span>
            </x-ui.button>
        </div>
    </x-ui.card>

    <x-ui.card :title="__('center.calendar.exceptions_title')" class="mf-operating-calendar-exceptions">
        <flux:text class="mb-5 text-text-muted!">
            {{ __('center.calendar.exceptions_description') }}
        </flux:text>

        <form wire:submit="saveException" class="mf-operating-calendar-exception-form mb-8 space-y-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-5">
            <flux:heading size="sm" class="font-display text-text-heading!">
                {{ $editingExceptionId ? __('center.calendar.edit_exception') : __('center.calendar.add_exception') }}
            </flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:field>
                    <flux:label>{{ __('center.calendar.fields.exception_date') }}</flux:label>
                    <x-ui.date-picker wire:model="exception_date" />
                    <flux:error name="exception_date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('center.calendar.fields.exception_type') }}</flux:label>
                    <flux:select wire:model.live="exception_type">
                        @foreach (\App\Enums\CalendarExceptionType::cases() as $type)
                            <flux:select.option value="{{ $type->value }}">
                                {{ $exceptionTypeLabel($type->value) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="exception_type" />
                </flux:field>

                @if ($exception_type === \App\Enums\CalendarExceptionType::SpecialOpen->value)
                    <flux:field>
                        <flux:label>{{ __('center.calendar.fields.open_time') }}</flux:label>
                        <x-ui.time-picker wire:model="exception_open_time" />
                        <flux:error name="exception_open_time" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('center.calendar.fields.close_time') }}</flux:label>
                        <x-ui.time-picker wire:model="exception_close_time" />
                        <flux:error name="exception_close_time" />
                    </flux:field>
                @endif

                <flux:field class="sm:col-span-2 lg:col-span-4">
                    <flux:label>{{ __('center.calendar.fields.notes') }}</flux:label>
                    <flux:textarea wire:model="exception_notes" rows="2" />
                    <flux:error name="exception_notes" />
                </flux:field>
            </div>

            <div class="flex flex-wrap gap-3">
                <x-ui.button
                    variant="primary"
                    type="submit"
                    icon="{{ $editingExceptionId ? 'check-circle' : 'plus-circle' }}"
                    wire:loading.attr="disabled"
                    wire:target="saveException"
                >
                    <span wire:loading.remove wire:target="saveException">
                        {{ $editingExceptionId ? __('center.calendar.update_exception') : __('center.calendar.add_exception') }}
                    </span>
                    <span wire:loading wire:target="saveException">{{ __('center.calendar.saving') }}</span>
                </x-ui.button>

                @if ($editingExceptionId)
                    <x-ui.button type="button" variant="secondary" wire:click="cancelExceptionEdit">
                        {{ __('center.calendar.cancel_edit') }}
                    </x-ui.button>
                @endif
            </div>
        </form>

        @if ($exceptions->isEmpty())
            <div class="rounded-xl border border-dashed border-slate-200 bg-white/70 px-6 py-10 text-center">
                <flux:text class="text-text-muted!">{{ __('center.calendar.exceptions_empty') }}</flux:text>
            </div>
        @else
            <x-ui.table-panel>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('center.calendar.fields.exception_date') }}</flux:table.column>
                        <flux:table.column>{{ __('center.calendar.fields.exception_type') }}</flux:table.column>
                        <flux:table.column>{{ __('center.calendar.fields.hours') }}</flux:table.column>
                        <flux:table.column>{{ __('center.calendar.fields.notes') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('center.manage.columns.actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($exceptions as $exception)
                            <flux:table.row wire:key="exception-row-{{ $exception->id }}">
                                <flux:table.cell class="font-medium">
                                    {{ $exception->exception_date->format('Y-m-d') }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-ui.status-badge
                                        :status="match ($exception->type) {
                                            'holiday' => 'info',
                                            'closure' => 'warning',
                                            'special_open' => 'success',
                                            default => 'neutral',
                                        }"
                                    >
                                        {{ $exceptionTypeLabel($exception->type) }}
                                    </x-ui.status-badge>
                                </flux:table.cell>
                                <flux:table.cell class="text-text-muted">
                                    @if ($exception->type === \App\Enums\CalendarExceptionType::SpecialOpen->value)
                                        {{ $formatTimeForDisplay($exception->open_time) }} – {{ $formatTimeForDisplay($exception->close_time) }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="max-w-xs truncate text-text-muted">
                                    {{ $exception->notes ?: '—' }}
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="inline-flex flex-wrap justify-end gap-2">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="pencil-square"
                                            wire:click="editException({{ $exception->id }})"
                                        >
                                            {{ __('center.manage.actions.edit') }}
                                        </flux:button>
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="trash"
                                            wire:click="deleteException({{ $exception->id }})"
                                            wire:confirm="{{ __('center.calendar.delete_confirm') }}"
                                            class="!text-red-600"
                                        >
                                            {{ __('center.calendar.delete_exception') }}
                                        </flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-ui.table-panel>
        @endif
    </x-ui.card>
</x-ui.page>
