const locale = () => document.documentElement.lang || 'en-GB';

const weekStartsOn = () => (locale().startsWith('fr') ? 1 : 0);

const weekdayLabels = () => {
    const start = weekStartsOn();
    const formatter = new Intl.DateTimeFormat(locale(), { weekday: 'short' });
    const order = start === 1 ? [1, 2, 3, 4, 5, 6, 7] : [7, 1, 2, 3, 4, 5, 6];

    return order.map((day) => formatter.format(new Date(2024, 0, day)));
};

const formatDisplayDate = (isoDate) => {
    if (!isoDate) {
        return '';
    }

    const [year, month, day] = isoDate.split('-').map(Number);

    if (!year || !month || !day) {
        return isoDate;
    }

    return new Intl.DateTimeFormat(locale(), {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(year, month - 1, day));
};

const formatMonthYear = (year, monthIndex) =>
    new Intl.DateTimeFormat(locale(), {
        month: 'long',
        year: 'numeric',
    }).format(new Date(year, monthIndex, 1));

const formatMonth = (year, monthIndex) =>
    new Intl.DateTimeFormat(locale(), { month: 'short' }).format(new Date(year, monthIndex, 1));

const parseIsoDate = (isoDate) => {
    if (!isoDate) {
        const now = new Date();

        return {
            year: now.getFullYear(),
            month: now.getMonth(),
        };
    }

    const [year, month] = isoDate.split('-').map(Number);

    return {
        year,
        month: month - 1,
    };
};

document.addEventListener('alpine:init', () => {
    Alpine.data('mfDatePicker', (config) => ({
        open: false,
        step: 'year',
        date: config.date,
        floating: config.floating !== false,
        placeholder: config.placeholder,
        labels: config.labels,
        viewYearPage: new Date().getFullYear() - 5,
        pickingYear: new Date().getFullYear(),
        pickingMonth: new Date().getMonth(),
        popoverStyle: {},

        get displayValue() {
            return formatDisplayDate(this.date);
        },

        get stepTitle() {
            if (this.step === 'year') {
                return this.labels.selectYear;
            }

            if (this.step === 'month') {
                return this.labels.selectMonth;
            }

            return formatMonthYear(this.pickingYear, this.pickingMonth);
        },

        get yearRangeLabel() {
            const end = this.viewYearPage + 11;

            return `${this.viewYearPage} – ${end}`;
        },

        get weekdays() {
            return weekdayLabels();
        },

        init() {
            this.syncFromDate();

            this._repositionPopover = () => {
                if (this.open) {
                    this.positionPopover();
                }
            };

            this.$watch('step', () => {
                if (this.open && this.floating) {
                    this.$nextTick(() => this.positionPopover());
                }
            });
        },

        positionPopover() {
            const trigger = this.$refs.trigger;
            const popover = this.$refs.popover;

            if (!trigger || !popover) {
                return;
            }

            const rect = trigger.getBoundingClientRect();
            const popoverWidth = Math.min(Math.max(rect.width, 296), window.innerWidth - 32);
            const popoverHeight = popover.offsetHeight || 320;
            const viewportPadding = 16;
            const gap = 8;

            let top = rect.bottom + gap;
            let left = rect.left;

            if (top + popoverHeight > window.innerHeight - viewportPadding) {
                top = rect.top - popoverHeight - gap;
            }

            if (top < viewportPadding) {
                top = viewportPadding;
            }

            if (left + popoverWidth > window.innerWidth - viewportPadding) {
                left = window.innerWidth - popoverWidth - viewportPadding;
            }

            if (left < viewportPadding) {
                left = viewportPadding;
            }

            this.popoverStyle = {
                top: `${Math.round(top)}px`,
                left: `${Math.round(left)}px`,
                width: `${Math.round(popoverWidth)}px`,
            };
        },

        bindRepositionListeners() {
            window.addEventListener('scroll', this._repositionPopover, true);
            window.addEventListener('resize', this._repositionPopover);
        },

        unbindRepositionListeners() {
            window.removeEventListener('scroll', this._repositionPopover, true);
            window.removeEventListener('resize', this._repositionPopover);
        },

        syncFromDate() {
            const parsed = parseIsoDate(this.date);
            this.pickingYear = parsed.year;
            this.pickingMonth = parsed.month;
            this.viewYearPage = Math.floor(parsed.year / 12) * 12;
        },

        openPicker() {
            this.syncFromDate();
            this.step = 'year';
            this.open = true;

            if (this.floating) {
                this.bindRepositionListeners();
                this.$nextTick(() => this.positionPopover());
            }
        },

        closePicker() {
            this.open = false;
            this.step = 'year';

            if (this.floating) {
                this.unbindRepositionListeners();
            }
        },

        years() {
            return Array.from({ length: 12 }, (_, index) => this.viewYearPage + index);
        },

        selectYear(year) {
            this.pickingYear = year;
            this.step = 'month';
        },

        months() {
            return Array.from({ length: 12 }, (_, index) => index);
        },

        monthLabel(monthIndex) {
            return formatMonth(this.pickingYear, monthIndex);
        },

        selectMonth(monthIndex) {
            this.pickingMonth = monthIndex;
            this.step = 'day';
        },

        back() {
            if (this.step === 'month') {
                this.step = 'year';
            } else if (this.step === 'day') {
                this.step = 'month';
            }
        },

        calendarDays() {
            const days = [];
            const firstOffset = this.firstWeekday();
            const daysInMonth = new Date(this.pickingYear, this.pickingMonth + 1, 0).getDate();
            const daysInPreviousMonth = new Date(this.pickingYear, this.pickingMonth, 0).getDate();

            for (let index = firstOffset - 1; index >= 0; index -= 1) {
                days.push({
                    day: daysInPreviousMonth - index,
                    current: false,
                });
            }

            for (let day = 1; day <= daysInMonth; day += 1) {
                days.push({
                    day,
                    current: true,
                });
            }

            let trailingDay = 1;

            while (days.length % 7 !== 0) {
                days.push({
                    day: trailingDay,
                    current: false,
                });
                trailingDay += 1;
            }

            return days;
        },

        firstWeekday() {
            const day = new Date(this.pickingYear, this.pickingMonth, 1).getDay();
            const start = weekStartsOn();

            return (day - start + 7) % 7;
        },

        selectDay(day, isCurrent) {
            if (!isCurrent) {
                return;
            }

            const month = String(this.pickingMonth + 1).padStart(2, '0');
            const dayValue = String(day).padStart(2, '0');
            this.date = `${this.pickingYear}-${month}-${dayValue}`;
            this.closePicker();
        },

        isSelectedDay(day, isCurrent) {
            if (!isCurrent || !this.date) {
                return false;
            }

            const [year, month, dayValue] = this.date.split('-').map(Number);

            return (
                year === this.pickingYear
                && month - 1 === this.pickingMonth
                && dayValue === day
            );
        },

        isWeekendColumn(columnIndex) {
            const start = weekStartsOn();
            const weekday = (start + columnIndex) % 7;

            return weekday === 0 || weekday === 6;
        },

        isWeekendDay(dayIndex) {
            return this.isWeekendColumn(dayIndex % 7);
        },

        clear() {
            this.date = '';
            this.closePicker();
        },

        prevYearPage() {
            this.viewYearPage -= 12;
        },

        nextYearPage() {
            this.viewYearPage += 12;
        },
    }));
});
