@props([
    'floating' => true,
    'clearable' => false,
])

@php
    $minutes = array_map(static fn (int $m): string => str_pad((string) $m, 2, '0', STR_PAD_LEFT), range(0, 59));
@endphp

<div
    x-ref="popover"
    x-show="open"
    x-cloak
    x-bind:style="floating ? popoverStyle : {}"
    x-on:click.outside="closePicker()"
    @class([
        'mf-time-picker-popover',
        'mf-time-picker-popover--floating' => $floating,
        'mf-time-picker-popover--inline' => ! $floating,
    ])
    role="dialog"
    aria-modal="true"
>
    <div class="mf-time-picker-popover-header">
        <p class="mf-time-picker-hint" x-text="labels.hint"></p>
    </div>

    <div class="mf-time-picker-popover-body" x-bind:class="{ 'mf-time-picker-popover-body--12h': use12Hour }">
        <label class="mf-time-picker-field">
            <span x-text="labels.hour"></span>
            <select class="mf-time-picker-select" x-model="hour">
                <template x-for="value in hours" x-bind:key="`hour-${use12Hour ? '12' : '24'}-${value}`">
                    <option x-bind:value="value" x-text="value"></option>
                </template>
            </select>
        </label>

        <span class="mf-time-picker-separator" aria-hidden="true">:</span>

        <label class="mf-time-picker-field">
            <span x-text="labels.minute"></span>
            <select class="mf-time-picker-select" x-model="minute">
                @foreach ($minutes as $minute)
                    <option value="{{ $minute }}">{{ $minute }}</option>
                @endforeach
            </select>
        </label>

        <label class="mf-time-picker-field mf-time-picker-field--period" x-show="use12Hour" x-cloak>
            <span x-text="labels.period"></span>
            <select class="mf-time-picker-select" x-model="meridiem">
                <option value="am" x-text="labels.am"></option>
                <option value="pm" x-text="labels.pm"></option>
            </select>
        </label>
    </div>

    <div class="mf-time-picker-popover-footer">
        @if ($clearable)
            <button type="button" class="mf-time-picker-footer-btn" x-on:click="clearSelection()" x-text="labels.clear"></button>
        @endif
        <button type="button" class="mf-time-picker-footer-btn mf-time-picker-footer-btn--primary" x-on:click="applySelection()" x-text="labels.apply"></button>
    </div>
</div>
