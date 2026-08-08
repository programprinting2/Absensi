/**
 * Date picker kalender untuk halaman Absensi (Alpine).
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('attendanceDatePicker', (config = {}) => {
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

        const initial = String(config.selectedDate || config.today || '').split('-');
        const initYear = initial.length === 3 ? parseInt(initial[0], 10) : new Date().getFullYear();
        const initMonth = initial.length === 3 ? parseInt(initial[1], 10) : (new Date().getMonth() + 1);

        return {
            open: false,
            holidays: config.holidays || {},
            today: config.today || '',
            selectedDate: config.selectedDate || config.today || '',
            year: initYear,
            month: initMonth,

            get monthLabel() {
                return monthNames[this.month - 1] + ' ' + this.year;
            },

            get cells() {
                const first = new Date(this.year, this.month - 1, 1);
                const startOffset = (first.getDay() + 6) % 7;
                const dim = daysInMonth(this.year, this.month);
                const cells = [];

                for (let i = 0; i < startOffset; i++) {
                    cells.push({
                        key: 'e' + i,
                        day: null,
                        date: null,
                        isSunday: false,
                        isToday: false,
                        isSelected: false,
                        holidayName: null,
                        isJointLeave: false,
                    });
                }

                for (let d = 1; d <= dim; d++) {
                    const dateObj = new Date(this.year, this.month - 1, d);
                    const date = toYmd(dateObj);
                    const dow = dateObj.getDay();
                    const hol = this.holidays[date] || null;
                    cells.push({
                        key: date,
                        day: d,
                        date,
                        isSunday: dow === 0,
                        isToday: date === this.today,
                        isSelected: date === this.selectedDate,
                        holidayName: hol ? hol.name : null,
                        isJointLeave: hol ? !!hol.is_joint_leave : false,
                    });
                }

                return cells;
            },

            cellClasses(cell) {
                if (!cell.day) {
                    return 'border-transparent bg-transparent cursor-default';
                }
                const classes = ['border-gray-100', 'bg-white', 'cursor-pointer', 'hover:bg-indigo-50', 'hover:border-indigo-200'];
                if (cell.isSelected) {
                    classes.push('ring-2', 'ring-[#f7340d]', 'ring-inset', 'bg-orange-50');
                } else if (cell.isToday) {
                    classes.push('ring-1', 'ring-indigo-300', 'ring-inset');
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

            openDialog() {
                const fromWire = this.$wire?.selectedDate;
                if (fromWire) {
                    this.selectedDate = fromWire;
                }
                const parts = String(this.selectedDate || this.today).split('-');
                if (parts.length === 3) {
                    this.year = parseInt(parts[0], 10);
                    this.month = parseInt(parts[1], 10);
                }
                this.open = true;
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

            pickDay(cell) {
                if (!cell.date) return;
                this.selectedDate = cell.date;
                this.open = false;
                this.$wire.setSelectedDate(cell.date);
            },
        };
    });
});
