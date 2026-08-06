document.addEventListener('alpine:init', () => {
    Alpine.data('timeInput', (initial = '') => ({
        value: String(initial || '').slice(0, 5),

        onInput(event) {
            let digits = String(event.target.value).replace(/\D/g, '').slice(0, 4);

            if (digits.length >= 3) {
                this.value = `${digits.slice(0, 2)}:${digits.slice(2)}`;
            } else {
                this.value = digits;
            }

            event.target.value = this.value;
        },

        onBlur() {
            const match = String(this.value).match(/^(\d{1,2})(?::?(\d{0,2}))?$/);
            if (!match) {
                this.value = '';
                return;
            }

            let hours = parseInt(match[1], 10);
            let minutes = parseInt(match[2] || '0', 10);

            if (Number.isNaN(hours) || Number.isNaN(minutes)) {
                this.value = '';
                return;
            }

            hours = Math.min(Math.max(hours, 0), 23);
            minutes = Math.min(Math.max(minutes, 0), 59);
            this.value = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
        },
    }));
});

/**
 * Jam pulang = jam masuk + lama kerja (jam) + istirahat (menit). Format 24 jam HH:MM.
 */
window.calcWorkClockOut = function (clockIn, workHours, breakMinutes) {
    const match = String(clockIn || '').match(/^(\d{1,2}):(\d{2})$/);
    if (!match) {
        return '';
    }

    const start = (parseInt(match[1], 10) * 60) + parseInt(match[2], 10);
    const work = Math.round(parseFloat(workHours || 0) * 60);
    const brk = parseInt(breakMinutes || 0, 10) || 0;

    if (Number.isNaN(start) || Number.isNaN(work)) {
        return '';
    }

    let total = start + work + brk;
    total = ((total % 1440) + 1440) % 1440;

    const hours = Math.floor(total / 60);
    const minutes = total % 60;

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
};

window.formatTime24 = function (raw) {
    const match = String(raw || '').match(/^(\d{1,2})(?::?(\d{0,2}))?$/);
    if (!match) {
        return '';
    }

    let hours = parseInt(match[1], 10);
    let minutes = parseInt(match[2] || '0', 10);

    if (Number.isNaN(hours) || Number.isNaN(minutes)) {
        return '';
    }

    hours = Math.min(Math.max(hours, 0), 23);
    minutes = Math.min(Math.max(minutes, 0), 59);

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
};

window.maskTime24Input = function (raw) {
    const digits = String(raw || '').replace(/\D/g, '').slice(0, 4);

    return digits.length >= 3 ? `${digits.slice(0, 2)}:${digits.slice(2)}` : digits;
};
