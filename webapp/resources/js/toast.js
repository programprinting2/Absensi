document.addEventListener('alpine:init', () => {
    Alpine.data('toastHub', (initial = []) => ({
        toasts: [],
        nextId: 1,

        init() {
            (initial || []).forEach((t) => this.push(t));

            if (Array.isArray(window.__INITIAL_TOASTS)) {
                window.__INITIAL_TOASTS.forEach((t) => this.push(t));
                window.__INITIAL_TOASTS = [];
            }

            window.showToast = (message, type = 'success') => {
                this.push({ message, type });
            };
        },

        push(toast) {
            const normalized = this.normalize(toast);
            if (!normalized) {
                return;
            }

            const id = this.nextId++;
            const item = { id, ...normalized };

            this.toasts.push(item);
            window.setTimeout(() => this.dismiss(id), toast?.duration ?? 4200);
        },

        normalize(toast) {
            if (!toast) {
                return null;
            }

            // Livewire / Alpine event detail may be nested or array-wrapped.
            let data = toast;
            if (Array.isArray(data)) {
                data = data[0];
            }
            if (data?.detail) {
                data = data.detail;
            }
            if (Array.isArray(data)) {
                data = data[0];
            }

            if (!data?.message) {
                return null;
            }

            return {
                type: data.type === 'error' || data.type === 'danger' ? 'error' : (data.type || 'success'),
                message: String(data.message),
            };
        },

        dismiss(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id);
        },
    }));
});
