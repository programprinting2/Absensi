export function parseCurrencyId(value) {
    const digits = String(value ?? '').replace(/\D/g, '');
    if (digits === '') {
        return 0;
    }

    return parseInt(digits, 10);
}

export function formatCurrencyId(value) {
    const n = Math.round(Number(value) || 0);

    return n.toLocaleString('id-ID');
}

function caretAfterDigits(formatted, digitCount) {
    if (digitCount <= 0) {
        return 0;
    }

    let seen = 0;
    for (let i = 0; i < formatted.length; i++) {
        if (/\d/.test(formatted[i])) {
            seen++;
            if (seen >= digitCount) {
                return i + 1;
            }
        }
    }

    return formatted.length;
}

// Tersedia segera (bukan hanya saat alpine:init), dipakai modal gaji.
window.parseCurrencyId = parseCurrencyId;
window.formatCurrencyId = formatCurrencyId;

document.addEventListener('alpine:init', () => {
    Alpine.data('currencyField', (initial = 0, wireKey = null) => ({
        raw: Math.round(Number(initial) || 0),
        display: Number(initial) ? formatCurrencyId(initial) : '',
        wireKey,
        syncingFromWire: false,

        init() {
            if (!this.wireKey) {
                return;
            }

            this.$watch(`$wire.${this.wireKey}`, (value) => {
                const n = Math.round(Number(value) || 0);
                if (n === this.raw) {
                    return;
                }

                this.syncingFromWire = true;
                this.raw = n;
                this.display = n ? formatCurrencyId(n) : '';
                this.$nextTick(() => {
                    this.syncingFromWire = false;
                });
            });
        },

        onInput(event) {
            if (this.syncingFromWire) {
                return;
            }

            const el = event.target;
            const selection = el.selectionStart ?? String(el.value).length;
            const digitsBeforeCaret = String(el.value).slice(0, selection).replace(/\D/g, '').length;
            const digits = String(el.value).replace(/\D/g, '');

            this.raw = digits === '' ? 0 : parseInt(digits, 10);
            this.display = digits === '' ? '' : formatCurrencyId(this.raw);

            // Jangan pakai x-model + set value manual bersamaan; set langsung lalu pulihkan kursor.
            el.value = this.display;
            const pos = caretAfterDigits(this.display, digitsBeforeCaret);
            el.setSelectionRange(pos, pos);

            if (this.wireKey) {
                // false = update state Livewire tanpa request (hindari re-render saat mengetik)
                this.$wire.set(this.wireKey, this.raw, false);
            }
        },

        onBlur() {
            this.display = this.raw ? formatCurrencyId(this.raw) : '';

            if (this.wireKey) {
                // Sync ke server saat blur agar computed (cicilan/bulan) ikut terhitung
                this.$wire.set(this.wireKey, this.raw);
            }
        },
    }));
});
