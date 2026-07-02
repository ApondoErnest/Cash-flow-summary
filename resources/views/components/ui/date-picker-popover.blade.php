@props([
    'floating' => true,
])

<div
    x-cloak
    x-show="open"
    x-transition.opacity.duration.150ms
    x-ref="popover"
    @class([
        'mf-date-picker-popover',
        'mf-date-picker-popover--floating' => $floating,
        'mf-date-picker-popover--inline' => ! $floating,
    ])
    x-bind:style="floating ? popoverStyle : null"
    role="dialog"
    aria-modal="true"
    x-on:click.outside="if (!$refs.trigger.contains($event.target)) closePicker()"
>
    <div class="mf-date-picker-popover-header">
        <div class="mf-date-picker-popover-nav">
            <button
                type="button"
                class="mf-date-picker-nav-btn"
                x-show="step !== 'year'"
                x-on:click="back()"
                x-bind:aria-label="labels.back"
            >
                <flux:icon icon="chevron-left" variant="mini" class="size-4" />
            </button>

            <button
                type="button"
                class="mf-date-picker-nav-btn"
                x-show="step === 'year'"
                x-on:click="prevYearPage()"
                aria-label="Previous years"
            >
                <flux:icon icon="chevron-left" variant="mini" class="size-4" />
            </button>
        </div>

        <div class="mf-date-picker-popover-title">
            <span class="mf-date-picker-step-label" x-show="step !== 'day'" x-text="stepTitle"></span>
            <span class="mf-date-picker-step-value" x-show="step === 'month'" x-text="pickingYear"></span>
            <span class="mf-date-picker-step-value" x-show="step === 'day'" x-text="stepTitle"></span>
            <span class="mf-date-picker-step-range" x-show="step === 'year'" x-text="yearRangeLabel"></span>
        </div>

        <div class="mf-date-picker-popover-nav mf-date-picker-popover-nav--end">
            <button
                type="button"
                class="mf-date-picker-nav-btn"
                x-show="step === 'year'"
                x-on:click="nextYearPage()"
                aria-label="Next years"
            >
                <flux:icon icon="chevron-right" variant="mini" class="size-4" />
            </button>
        </div>
    </div>

    <div class="mf-date-picker-popover-body">
        <div class="mf-date-picker-grid mf-date-picker-grid--years" x-show="step === 'year'">
            <template x-for="year in years()" x-bind:key="`year-${year}`">
                <button
                    type="button"
                    class="mf-date-picker-cell"
                    x-on:click="selectYear(year)"
                    x-text="year"
                    x-bind:class="{ 'mf-date-picker-cell--selected': date && Number(date.split('-')[0]) === year }"
                ></button>
            </template>
        </div>

        <div class="mf-date-picker-grid mf-date-picker-grid--months" x-show="step === 'month'" x-cloak>
            <template x-for="monthIndex in months()" x-bind:key="`month-${monthIndex}`">
                <button
                    type="button"
                    class="mf-date-picker-cell"
                    x-on:click="selectMonth(monthIndex)"
                    x-text="monthLabel(monthIndex)"
                    x-bind:class="{
                        'mf-date-picker-cell--selected':
                            date
                            && Number(date.split('-')[0]) === pickingYear
                            && Number(date.split('-')[1]) - 1 === monthIndex,
                    }"
                ></button>
            </template>
        </div>

        <div x-show="step === 'day'" x-cloak>
            <div class="mf-date-picker-weekdays">
                <template x-for="(weekday, index) in weekdays" x-bind:key="`weekday-${weekday}`">
                    <span
                        class="mf-date-picker-weekday"
                        x-text="weekday"
                        x-bind:class="{ 'mf-date-picker-weekday--weekend': isWeekendColumn(index) }"
                    ></span>
                </template>
            </div>

            <div class="mf-date-picker-grid mf-date-picker-grid--days">
                <template x-for="(cell, index) in calendarDays()" x-bind:key="`day-${index}-${cell.day}`">
                    <button
                        type="button"
                        class="mf-date-picker-day"
                        x-text="cell.day"
                        x-on:click="selectDay(cell.day, cell.current)"
                        x-bind:disabled="!cell.current"
                        x-bind:class="{
                            'mf-date-picker-day--muted': !cell.current,
                            'mf-date-picker-day--weekend': cell.current && isWeekendDay(index),
                            'mf-date-picker-day--selected': isSelectedDay(cell.day, cell.current),
                        }"
                    ></button>
                </template>
            </div>
        </div>
    </div>

    <div class="mf-date-picker-popover-footer">
        <button type="button" class="mf-date-picker-clear" x-on:click="clear()" x-text="labels.clear"></button>
    </div>
</div>
