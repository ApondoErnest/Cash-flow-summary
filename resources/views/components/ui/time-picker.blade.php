@props([
    'label' => null,
    'required' => false,
    'clearable' => false,
    'disabled' => false,
    'teleport' => true,
])

@php
    $wireModel = $attributes->wire('model');
    $use12Hour = app()->getLocale() === 'en';

    if ($wireModel !== null) {
        unset($attributes[$wireModel->directive]);
    }
@endphp

<div
    {{ $attributes->class(['mf-time-picker', 'mf-time-picker--disabled' => $disabled]) }}
    x-data="mfTimePicker({
        time: @entangle($wireModel->value()).live,
        floating: @js($teleport),
        use12Hour: @js($use12Hour),
        placeholder: @js(__('ui.time_picker.placeholder')),
        labels: @js([
            'hour' => __('ui.time_picker.hour'),
            'minute' => __('ui.time_picker.minute'),
            'period' => __('ui.time_picker.period'),
            'am' => __('ui.time_picker.am'),
            'pm' => __('ui.time_picker.pm'),
            'apply' => __('ui.time_picker.apply'),
            'clear' => __('ui.time_picker.clear'),
            'hint' => __('ui.time_picker.hint'),
        ]),
    })"
    x-on:keydown.escape.window="if (open) { $event.stopImmediatePropagation(); closePicker(); }"
    data-mf-time-picker
>
    @if ($label)
        <label class="mf-time-picker-label">
            {{ $label }}
            @if ($required)
                <span class="text-danger-600" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="mf-time-picker-control">
        <button
            type="button"
            class="mf-time-picker-trigger"
            x-ref="trigger"
            x-on:click.stop="if (! @js($disabled)) { openPicker(); }"
            x-bind:aria-expanded="open"
            aria-haspopup="dialog"
            @disabled($disabled)
        >
            <span
                class="mf-time-picker-value"
                x-text="displayValue || placeholder"
                x-bind:class="{ 'mf-time-picker-value--placeholder': !displayValue }"
            ></span>
            <flux:icon icon="clock" variant="outline" class="mf-time-picker-icon size-5 shrink-0" />
        </button>

        @unless ($disabled)
            @if ($teleport)
                <template x-teleport="body">
                    <x-ui.time-picker-popover :floating="true" :clearable="$clearable" />
                </template>
            @else
                <x-ui.time-picker-popover :floating="false" :clearable="$clearable" />
            @endif
        @endunless
    </div>
</div>
