/**
 * Form Buat Periode + Cutoff + Kalender (Alpine).
 * Field cutoff/bulan di-entangle dari Livewire.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('payrollCreateForm', (config = {}) => {
        const monthNames = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        function pad(n) {
            return String(n).padStart(2, '0');
        }

        function toYmd(d) {
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        }

        function daysInMonth(y, m) {
            return new Date(y, m, 0).getDate();
        }

        return {
            holidays: config.holidays || {},
            today: config.today || '',
            year: Number(config.year) || new Date().getFullYear(),
            month: Number(config.month) || (new Date().getMonth() + 1),

            // Entangled Livewire fields (passed from blade)
            startDay: config.startDay,
            endDay: config.endDay,
            jointLeave: config.jointLeave,
            refMonth: config.refMonth,
            refYear: config.refYear,

            get monthLabel() {
                return monthNames[this.month - 1] + ' ' + this.year;
            },

            periodBounds() {
                const y = Number(this.refYear) || this.year;
                const m = Number(this.refMonth) || this.month;
                const startDay = Math.min(Math.max(1, Number(this.startDay) || 1), 31);
                const endDay = Math.min(Math.max(1, Number(this.endDay) || 1), 31);
                let start;
                let end;

                if (startDay <= endDay) {
                    start = new Date(y, m - 1, Math.min(startDay, daysInMonth(y, m)));
                    end = new Date(y, m - 1, Math.min(endDay, daysInMonth(y, m)));
                } else {
                    const prev = new Date(y, m - 2, 1);
                    const py = prev.getFullYear();
                    const pm = prev.getMonth() + 1;
                    start = new Date(py, pm - 1, Math.min(startDay, daysInMonth(py, pm)));
                    end = new Date(y, m - 1, Math.min(endDay, daysInMonth(y, m)));
                }

                start.setHours(0, 0, 0, 0);
                end.setHours(0, 0, 0, 0);
                return { start, end };
            },

            eachPeriodDate(callback) {
                const { start, end } = this.periodBounds();
                const cur = new Date(start);
                while (cur <= end) {
                    callback(new Date(cur));
                    cur.setDate(cur.getDate() + 1);
                }
            },

            get periodLabel() {
                const { start, end } = this.periodBounds();
                const fmt = (d) => d.getDate() + ' ' + monthNames[d.getMonth()] + ' ' + d.getFullYear();
                return fmt(start) + ' — ' + fmt(end);
            },

            get totalDays() {
                let n = 0;
                this.eachPeriodDate(() => { n += 1; });
                return n;
            },

            get sundayCount() {
                let n = 0;
                this.eachPeriodDate((d) => {
                    if (d.getDay() === 0) n += 1;
                });
                return n;
            },

            get suggestedJointLeave() {
                let n = 0;
                this.eachPeriodDate((d) => {
                    if (d.getDay() === 0) return;
                    const hol = this.holidays[toYmd(d)];
                    if (hol && hol.is_joint_leave) n += 1;
                });
                return n;
            },

            get workDayCount() {
                const joint = Math.max(0, Number(this.jointLeave) || 0);
                return Math.max(0, this.totalDays - this.sundayCount - joint);
            },

            get cells() {
                const first = new Date(this.year, this.month - 1, 1);
                const startOffset = (first.getDay() + 6) % 7;
                const dim = daysInMonth(this.year, this.month);
                const { start, end } = this.periodBounds();
                const cells = [];

                for (let i = 0; i < startOffset; i++) {
                    cells.push({ key: 'e' + i, day: null, isSunday: false, isToday: false, inPeriod: false, holidayName: null, isJointLeave: false });
                }

                for (let d = 1; d <= dim; d++) {
                    const dateObj = new Date(this.year, this.month - 1, d);
                    const date = toYmd(dateObj);
                    const dow = dateObj.getDay();
                    const hol = this.holidays[date] || null;
                    cells.push({
                        key: date,
                        day: d,
                        isSunday: dow === 0,
                        isToday: date === this.today,
                        inPeriod: dateObj >= start && dateObj <= end,
                        holidayName: hol ? hol.name : null,
                        isJointLeave: hol ? !!hol.is_joint_leave : false,
                    });
                }

                return cells;
            },

            get monthHolidays() {
                const prefix = this.year + '-' + pad(this.month) + '-';
                return Object.keys(this.holidays)
                    .filter((d) => d.startsWith(prefix))
                    .sort()
                    .map((d) => ({ date: d, ...this.holidays[d] }));
            },

            cellClasses(cell) {
                if (!cell.day) {
                    return 'border-transparent bg-transparent';
                }
                const classes = ['border-gray-100', 'bg-gray-50/60'];
                if (cell.inPeriod) {
                    classes.push('ring-1', 'ring-indigo-200');
                }
                if (cell.isToday) {
                    classes.push('ring-2', 'ring-[#f7340d]', 'ring-inset');
                }
                if (cell.holidayName && !cell.isJointLeave) {
                    classes.push('bg-red-50', 'text-red-700', 'border-red-100');
                } else if (cell.isJointLeave) {
                    classes.push('bg-amber-50', 'text-amber-800', 'border-amber-100');
                } else if (cell.isSunday) {
                    classes.push('text-red-600');
                } else {
                    classes.push('text-gray-800');
                }
                return classes.join(' ');
            },

            formatDay(date) {
                const parts = date.split('-');
                return parts[2] + '/' + parts[1];
            },

            prevMonth() {
                if (this.month === 1) {
                    this.month = 12;
                    this.year -= 1;
                } else {
                    this.month -= 1;
                }
            },

            nextMonth() {
                if (this.month === 12) {
                    this.month = 1;
                    this.year += 1;
                } else {
                    this.month += 1;
                }
            },

            goToday() {
                const t = String(this.today).split('-');
                if (t.length === 3) {
                    this.year = parseInt(t[0], 10);
                    this.month = parseInt(t[1], 10);
                }
            },

            syncCalendarToPeriod() {
                this.year = Number(this.refYear) || this.year;
                this.month = Number(this.refMonth) || this.month;
            },

            applySuggestedJointLeave() {
                this.jointLeave = this.suggestedJointLeave;
            },
        };
    });
});
