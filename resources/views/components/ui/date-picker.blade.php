@props([
    'label' => null,
    'teleport' => true,
])

@php
    $wireModel = $attributes->wire('model');

    if ($wireModel !== null) {
        unset($attributes[$wireModel->directive]);
    }
@endphp

<div
    {{ $attributes->class('mf-date-picker') }}
    x-data="mfDatePicker({
        date: @entangle($wireModel->value()).live,
        floating: @js($teleport),
        placeholder: @js(__('ui.date_picker.placeholder')),
        labels: @js([
            'selectYear' => __('ui.date_picker.select_year'),
            'selectMonth' => __('ui.date_picker.select_month'),
            'clear' => __('ui.date_picker.clear'),
            'back' => __('ui.date_picker.back'),
        ]),
    })"
    x-on:keydown.escape.window="if (open) { $event.stopImmediatePropagation(); closePicker(); }"
    data-mf-date-picker
    @if (! $teleport) data-mf-date-picker-inline @endif
>
    @if ($label)
        <label class="mf-date-picker-label">{{ $label }}</label>
    @endif

    <div class="mf-date-picker-control">
        <button
            type="button"
            class="mf-date-picker-trigger"
            x-ref="trigger"
            x-on:click.stop="openPicker()"
            x-bind:aria-expanded="open"
            aria-haspopup="dialog"
        >
            <span
                class="mf-date-picker-value"
                x-text="displayValue || placeholder"
                x-bind:class="{ 'mf-date-picker-value--placeholder': !displayValue }"
            ></span>
            <flux:icon icon="calendar-days" variant="outline" class="mf-date-picker-icon size-5 shrink-0" />
        </button>

        @if ($teleport)
            <template x-teleport="body">
                <x-ui.date-picker-popover :floating="true" />
            </template>
        @else
            <x-ui.date-picker-popover :floating="false" />
        @endif
    </div>
</div>
