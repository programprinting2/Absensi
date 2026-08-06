document.addEventListener('alpine:init', () => {
    Alpine.data('parameterAutocomplete', (config = {}) => ({
        open: false,
        query: '',
        options: Array.isArray(config.options) ? config.options : [],
        highlighted: -1,
        prop: config.prop,
        apiBase: config.apiBase || null,
        canCrud: !!config.canCrud,
        csrf: config.csrf || '',
        menuStyle: {},
        editingId: null,
        editValue: '',
        saving: false,
        _onReposition: null,

        get filtered() {
            const q = (this.query || '').trim().toLowerCase();
            if (!q) {
                return this.options;
            }

            return this.options.filter((o) => {
                const label = (o.label || '').toLowerCase();
                const value = (o.value || '').toLowerCase();

                return label.includes(q) || value.includes(q);
            });
        },

        init() {
            this.query = this.$wire.get(this.prop) || '';

            this.$watch(() => this.$wire.get(this.prop), (value) => {
                if ((value || '') !== this.query) {
                    this.query = value || '';
                }
            });

            this._onReposition = () => {
                if (this.open) {
                    this.updateMenuPosition();
                }
            };

            window.addEventListener('scroll', this._onReposition, true);
            window.addEventListener('resize', this._onReposition);
        },

        destroy() {
            if (this._onReposition) {
                window.removeEventListener('scroll', this._onReposition, true);
                window.removeEventListener('resize', this._onReposition);
            }
        },

        async request(url, options = {}) {
            const res = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                ...options,
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                throw new Error(
                    data.message ||
                        (data.errors && Object.values(data.errors).flat()[0]) ||
                        'Request gagal',
                );
            }

            return data;
        },

        updateMenuPosition() {
            if (!this.$refs.input) {
                return;
            }

            const rect = this.$refs.input.getBoundingClientRect();
            const width = Math.max(rect.width, 300);
            const spaceBelow = window.innerHeight - rect.bottom;
            const showAbove = spaceBelow < 260 && rect.top > spaceBelow;

            this.menuStyle = {
                position: 'fixed',
                left: `${rect.left}px`,
                width: `${width}px`,
                zIndex: 100,
                ...(showAbove
                    ? { bottom: `${window.innerHeight - rect.top + 4}px`, top: 'auto' }
                    : { top: `${rect.bottom + 4}px`, bottom: 'auto' }),
            };
        },

        openDropdown() {
            this.open = true;
            this.editingId = null;
            this.$nextTick(() => this.updateMenuPosition());
        },

        closeDropdown(event) {
            const target = event && event.target;

            if (target) {
                if (target.closest('[data-ac-menu]')) {
                    return;
                }

                if (this.$refs.input && (target === this.$refs.input || this.$refs.input.contains(target))) {
                    return;
                }
            }

            if (this.editingId) {
                return;
            }

            this.$wire.set(this.prop, this.query || '', false);
            this.open = false;
            this.highlighted = -1;
        },

        onInput() {
            this.highlighted = -1;
            this.editingId = null;
            this.openDropdown();
        },

        clearQuery() {
            this.query = '';
            this.$wire.set(this.prop, '', false);
            this.openDropdown();
            this.$refs.input.focus();
        },

        selectOption(option) {
            this.query = option.value;
            this.$wire.set(this.prop, option.value, false);
            this.open = false;
            this.highlighted = -1;
            this.editingId = null;
        },

        startEdit(option) {
            this.editingId = option.id;
            this.editValue = option.label;
            this.open = true;
            this.$nextTick(() => this.updateMenuPosition());
        },

        cancelEdit() {
            this.editingId = null;
            this.editValue = '';
        },

        async saveEdit(option) {
            const name = (this.editValue || '').trim();

            if (!name || !this.apiBase || this.saving) {
                return;
            }

            this.saving = true;

            try {
                const updated = await this.request(`${this.apiBase}/${option.id}`, {
                    method: 'PUT',
                    body: JSON.stringify({ name, value: name, is_active: true }),
                });

                const next = {
                    id: updated.id,
                    label: updated.name,
                    value: updated.value || updated.name,
                };

                const idx = this.options.findIndex((o) => o.id === option.id);

                if (idx >= 0) {
                    this.options.splice(idx, 1, next);
                }

                if (this.query === option.value || this.$wire.get(this.prop) === option.value) {
                    this.query = next.value;
                    this.$wire.set(this.prop, next.value, false);
                }

                this.cancelEdit();
            } catch (e) {
                alert(e.message || 'Gagal mengubah data.');
            } finally {
                this.saving = false;
            }
        },

        async deleteOption(option) {
            if (!this.apiBase) {
                return;
            }

            if (!confirm(`Hapus "${option.label}" dari master parameter?`)) {
                return;
            }

            try {
                await this.request(`${this.apiBase}/${option.id}`, { method: 'DELETE' });
                this.options = this.options.filter((o) => o.id !== option.id);

                if (this.query === option.value || this.$wire.get(this.prop) === option.value) {
                    this.query = '';
                    this.$wire.set(this.prop, '', false);
                }
            } catch (e) {
                alert(e.message || 'Gagal menghapus data.');
            }
        },

        async createOption() {
            const name = (this.query || '').trim();

            if (!name || !this.apiBase || this.saving) {
                return;
            }

            this.saving = true;

            try {
                const created = await this.request(this.apiBase, {
                    method: 'POST',
                    body: JSON.stringify({ name, value: name, is_active: true }),
                });

                const option = {
                    id: created.id,
                    label: created.name,
                    value: created.value || created.name,
                };

                this.options.push(option);
                this.options.sort((a, b) => a.label.localeCompare(b.label, 'id'));
                this.selectOption(option);
            } catch (e) {
                alert(e.message || 'Gagal menambah data.');
            } finally {
                this.saving = false;
            }
        },

        onKeydown(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.openDropdown();

                if (!this.filtered.length) {
                    return;
                }

                this.highlighted = Math.min(this.highlighted + 1, this.filtered.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.highlighted = Math.max(this.highlighted - 1, 0);
            } else if (e.key === 'Enter') {
                e.preventDefault();

                if (this.editingId) {
                    return;
                }

                if (this.filtered.length && this.highlighted >= 0) {
                    this.selectOption(this.filtered[this.highlighted]);
                } else if (this.filtered.length === 1) {
                    this.selectOption(this.filtered[0]);
                } else if (this.canCrud && this.query.trim() && this.filtered.length === 0) {
                    this.createOption();
                } else if (this.filtered.length && this.highlighted < 0) {
                    this.selectOption(this.filtered[0]);
                }
            } else if (e.key === 'Escape') {
                this.cancelEdit();
                this.open = false;
            }
        },
    }));
});
