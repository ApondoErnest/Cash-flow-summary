const pad = (value) => String(value).padStart(2, '0');

const normalizeTime = (value) => {
    if (!value || typeof value !== 'string') {
        return '';
    }

    const match = value.trim().match(/^(\d{1,2}):(\d{2})/);

    if (!match) {
        return '';
    }

    const hour = Number(match[1]);
    const minute = Number(match[2]);

    if (Number.isNaN(hour) || Number.isNaN(minute) || hour > 23 || minute > 59) {
        return '';
    }

    return `${pad(hour)}:${pad(minute)}`;
};

const to12HourParts = (normalized) => {
    const [hour24, minute] = normalized.split(':').map(Number);
    const meridiem = hour24 >= 12 ? 'pm' : 'am';
    let hour12 = hour24 % 12;

    if (hour12 === 0) {
        hour12 = 12;
    }

    return {
        hour: String(hour12),
        minute: pad(minute),
        meridiem,
    };
};

const to24HourValue = (hour12, minute, meridiem) => {
    let hour = Number(hour12);
    const mins = pad(Number(minute));

    if (meridiem === 'am') {
        hour = hour === 12 ? 0 : hour;
    } else {
        hour = hour === 12 ? 12 : hour + 12;
    }

    return `${pad(hour)}:${mins}`;
};

const formatDisplay = (normalized, use12Hour) => {
    if (!normalized) {
        return '';
    }

    if (!use12Hour) {
        return normalized;
    }

    const parts = to12HourParts(normalized);
    const suffix = parts.meridiem === 'am' ? 'AM' : 'PM';

    return `${parts.hour}:${parts.minute} ${suffix}`;
};

document.addEventListener('alpine:init', () => {
    Alpine.data('mfTimePicker', (config) => ({
        open: false,
        time: config.time,
        floating: config.floating !== false,
        use12Hour: config.use12Hour === true,
        placeholder: config.placeholder,
        labels: config.labels,
        hour: config.use12Hour ? '6' : '18',
        minute: '00',
        meridiem: 'pm',
        popoverStyle: {},

        get hours() {
            if (this.use12Hour) {
                return Array.from({ length: 12 }, (_, index) => String(index + 1));
            }

            return Array.from({ length: 24 }, (_, index) => pad(index));
        },

        get minutes() {
            return Array.from({ length: 60 }, (_, index) => pad(index));
        },

        get displayValue() {
            return formatDisplay(normalizeTime(this.time), this.use12Hour);
        },

        init() {
            this.syncFromTime();

            this.$watch('time', () => this.syncFromTime());

            this._repositionPopover = () => {
                if (this.open) {
                    this.positionPopover();
                }
            };
        },

        syncFromTime() {
            const normalized = normalizeTime(this.time);

            if (!normalized) {
                if (this.use12Hour) {
                    this.hour = '6';
                    this.minute = '00';
                    this.meridiem = 'pm';
                } else {
                    this.hour = '18';
                    this.minute = '00';
                }

                return;
            }

            if (this.use12Hour) {
                const parts = to12HourParts(normalized);
                this.hour = parts.hour;
                this.minute = parts.minute;
                this.meridiem = parts.meridiem;

                return;
            }

            const [hour, minute] = normalized.split(':');
            this.hour = hour;
            this.minute = minute;
        },

        openPicker() {
            this.syncFromTime();
            this.open = true;
            this.$nextTick(() => this.positionPopover());
            window.addEventListener('resize', this._repositionPopover);
            window.addEventListener('scroll', this._repositionPopover, true);
        },

        closePicker() {
            this.open = false;
            window.removeEventListener('resize', this._repositionPopover);
            window.removeEventListener('scroll', this._repositionPopover, true);
        },

        positionPopover() {
            const trigger = this.$refs.trigger;
            const popover = this.$refs.popover;

            if (!trigger || !popover || !this.floating) {
                return;
            }

            const rect = trigger.getBoundingClientRect();
            const popoverWidth = Math.min(Math.max(rect.width, 320), window.innerWidth - 32);
            const popoverHeight = popover.offsetHeight || 180;
            const viewportPadding = 16;
            const gap = 8;

            let top = rect.bottom + gap;
            let left = rect.left;

            if (top + popoverHeight > window.innerHeight - viewportPadding) {
                top = rect.top - popoverHeight - gap;
            }

            if (left + popoverWidth > window.innerWidth - viewportPadding) {
                left = window.innerWidth - popoverWidth - viewportPadding;
            }

            if (left < viewportPadding) {
                left = viewportPadding;
            }

            this.popoverStyle = {
                position: 'fixed',
                top: `${Math.max(viewportPadding, top)}px`,
                left: `${left}px`,
                width: `${popoverWidth}px`,
                zIndex: 80,
            };
        },

        applySelection() {
            this.time = this.use12Hour
                ? to24HourValue(this.hour, this.minute, this.meridiem)
                : `${pad(Number(this.hour))}:${pad(Number(this.minute))}`;
            this.closePicker();
        },

        clearSelection() {
            this.time = '';
            this.syncFromTime();
            this.closePicker();
        },
    }));
});
