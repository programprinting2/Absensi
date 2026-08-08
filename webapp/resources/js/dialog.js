function inferDanger(message = '') {
    return /hapus|batalkan|reset|finalisasi|buka finalisasi|delete|remove|putus/i.test(String(message));
}

function getHub() {
    return window.__dialogHub || null;
}

window.appConfirm = (message, options = {}) => {
    const hub = getHub();
    if (!hub) {
        return Promise.resolve(window.confirm(String(message)));
    }

    return hub.show({
        mode: 'confirm',
        message: String(message || ''),
        title: options.title || 'Konfirmasi',
        confirmLabel: options.confirmLabel || 'Ya',
        cancelLabel: options.cancelLabel || 'Batal',
        danger: options.danger ?? inferDanger(message),
    });
};

window.appAlert = (message, options = {}) => {
    const hub = getHub();
    if (!hub) {
        window.alert(String(message));
        return Promise.resolve(true);
    }

    return hub.show({
        mode: 'alert',
        message: String(message || ''),
        title: options.title || 'Informasi',
        confirmLabel: options.confirmLabel || 'OK',
        danger: !!options.danger,
    });
};

window.appPromptConfirm = (question, expected, options = {}) => {
    const hub = getHub();
    if (!hub) {
        return Promise.resolve(window.prompt(String(question)) === expected);
    }

    return hub.show({
        mode: 'prompt',
        message: String(question || ''),
        title: options.title || 'Konfirmasi',
        confirmLabel: options.confirmLabel || 'Ya',
        cancelLabel: options.cancelLabel || 'Batal',
        danger: options.danger ?? true,
        promptExpected: expected,
    });
};

/** Untuk form submit: onclick/onsubmit="return confirmSubmit(event, 'Hapus?')" */
window.confirmSubmit = (event, message, options = {}) => {
    event.preventDefault();
    const target = event.currentTarget || event.target;
    const form = target?.tagName === 'FORM'
        ? target
        : (target?.form || target?.closest?.('form'));
    if (!form) {
        return false;
    }

    window.appConfirm(message, options).then((ok) => {
        if (ok) {
            HTMLFormElement.prototype.submit.call(form);
        }
    });

    return false;
};

document.addEventListener('alpine:init', () => {
    Alpine.data('dialogHub', () => ({
        open: false,
        mode: 'confirm',
        title: 'Konfirmasi',
        message: '',
        confirmLabel: 'Ya',
        cancelLabel: 'Batal',
        danger: false,
        promptValue: '',
        promptExpected: null,
        _resolve: null,

        init() {
            window.__dialogHub = this;
        },

        show(options = {}) {
            if (this.open && this._resolve) {
                this._resolve(false);
                this._resolve = null;
            }

            return new Promise((resolve) => {
                this.mode = options.mode || 'confirm';
                this.title = options.title || (this.mode === 'alert' ? 'Informasi' : 'Konfirmasi');
                this.message = options.message || '';
                this.confirmLabel = options.confirmLabel || (this.mode === 'alert' ? 'OK' : 'Ya');
                this.cancelLabel = options.cancelLabel || 'Batal';
                this.danger = !!options.danger;
                this.promptValue = '';
                this.promptExpected = options.promptExpected ?? null;
                this._resolve = resolve;
                this.open = true;

                this.$nextTick(() => {
                    if (this.mode === 'prompt') {
                        this.$refs.promptInput?.focus();
                    } else {
                        this.$refs.confirmBtn?.focus();
                    }
                });
            });
        },

        accept() {
            if (this.mode === 'prompt') {
                this.close(this.promptValue === this.promptExpected);
                return;
            }
            this.close(true);
        },

        cancel() {
            this.close(this.mode === 'alert');
        },

        close(result) {
            this.open = false;
            const resolve = this._resolve;
            this._resolve = null;
            resolve?.(!!result);
        },
    }));
});

export function installLivewireConfirm(Livewire) {
    if (!Livewire?.hook) {
        return;
    }

    // Directive bawaan Livewire tidak bisa dioverride (early-return jika nama sudah ada).
    // Hook ini jalan setelah handler bawaan, lalu mengganti window.confirm dengan dialog app.
    Livewire.hook('directive.init', ({ el, directive }) => {
        if (directive.value !== 'confirm') {
            return;
        }

        let message = String(directive.expression || '').replaceAll('\\n', '\n');
        const shouldPrompt = directive.modifiers.includes('prompt');
        if (message === '') {
            message = 'Apakah Anda yakin?';
        }

        el.__livewire_confirm = (action, instead) => {
            const reject = typeof instead === 'function' ? instead : () => {};

            if (shouldPrompt) {
                const [question, expected] = message.split('|');
                if (!expected) {
                    console.warn('Livewire: Must provide expectation with wire:confirm.prompt');
                    reject();
                    return;
                }

                window.appPromptConfirm(question, expected).then((ok) => {
                    if (ok) {
                        action();
                    } else {
                        reject();
                    }
                });
                return;
            }

            window.appConfirm(message).then((ok) => {
                if (ok) {
                    action();
                } else {
                    reject();
                }
            });
        };
    });
}
