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

// Tersedia segera (bukan hanya saat alpine:init), dipakai modal gaji.
window.parseCurrencyId = parseCurrencyId;
window.formatCurrencyId = formatCurrencyId;

document.addEventListener('alpine:init', () => {
    Alpine.data('currencyField', (initial = 0, wireKey = null) => ({
        raw: Math.round(Number(initial) || 0),
        display: formatCurrencyId(initial),
        wireKey,

        init() {
            if (!this.wireKey) {
                return;
            }

            this.$watch(`$wire.${this.wireKey}`, (value) => {
                const n = Math.round(Number(value) || 0);
                if (n === this.raw) {
                    return;
                }

                this.raw = n;
                this.display = formatCurrencyId(n);
            });
        },

        onInput(event) {
            const digits = String(event.target.value).replace(/\D/g, '');
            this.raw = digits === '' ? 0 : parseInt(digits, 10);
            this.display = digits === '' ? '' : formatCurrencyId(this.raw);
            event.target.value = this.display;

            if (this.wireKey) {
                this.$wire.set(this.wireKey, this.raw);
            }
        },

        onBlur() {
            this.display = formatCurrencyId(this.raw);
        },
    }));
});
