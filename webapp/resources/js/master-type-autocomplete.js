document.addEventListener('alpine:init', () => {
    Alpine.data('masterTypeAutocomplete', (config = {}) => ({
        open: false,
        query: '',
        selectedId: config.selectedId || '',
        options: Array.isArray(config.options) ? [...config.options] : [],
        highlighted: -1,
        apiBase: config.apiBase || null,
        canCrud: config.canCrud !== false,
        csrf: config.csrf || '',
        createDefaults: config.createDefaults || {},
        placeholder: config.placeholder || '',
        menuStyle: {},
        editingId: null,
        editValue: '',
        saving: false,
        _onReposition: null,
        _onOptions: null,

        get filtered() {
            const q = (this.query || '').trim().toLowerCase();
            if (!q) {
                return this.options;
            }

            return this.options.filter((o) => {
                const label = (o.label || o.name || '').toLowerCase();
                const value = (o.value || o.name || '').toLowerCase();

                return label.includes(q) || value.includes(q);
            });
        },

        init() {
            this.$nextTick(() => this.syncQueryFromId());

            this.$watch('selectedId', () => this.syncQueryFromId());

            if (config.storeKey) {
                this._onOptions = () => {
                    const store = Alpine.store(config.storeKey);
                    if (store?.options) {
                        this.options = store.options;
                        this.syncQueryFromId();
                    }
                };
                this.$watch(() => Alpine.store(config.storeKey)?.options, () => this._onOptions());
                this._onOptions();
            }

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

        syncQueryFromId() {
            if (!this.selectedId) {
                if (!this.open) {
                    this.query = '';
                }
                return;
            }

            const match = this.options.find((o) => o.id === this.selectedId);
            if (match) {
                this.query = match.label || match.name || match.value || '';
            }
        },

        pushOptionsToStore() {
            if (!config.storeKey) {
                return;
            }

            const store = Alpine.store(config.storeKey);
            if (store) {
                store.options = [...this.options];
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
            const width = Math.max(rect.width, 320);
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

            this.syncQueryFromId();
            this.open = false;
            this.highlighted = -1;
        },

        onInput() {
            this.highlighted = -1;
            this.editingId = null;
            this.selectedId = '';
            this.openDropdown();
        },

        clearQuery() {
            this.query = '';
            this.selectedId = '';
            this.openDropdown();
            this.$refs.input.focus();
        },

        selectOption(option) {
            this.selectedId = option.id;
            this.query = option.label || option.name || option.value || '';
            this.open = false;
            this.highlighted = -1;
            this.editingId = null;
        },

        startEdit(option) {
            this.editingId = option.id;
            this.editValue = option.name || option.label || '';
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
                    body: JSON.stringify({ name, is_active: true, ...this.createDefaults }),
                });

                const next = {
                    id: updated.id,
                    name: updated.name,
                    label: updated.label || updated.name,
                    value: updated.value || updated.name,
                    ...updated,
                };

                const idx = this.options.findIndex((o) => o.id === option.id);
                if (idx >= 0) {
                    this.options.splice(idx, 1, next);
                }

                this.pushOptionsToStore();

                if (this.selectedId === option.id) {
                    this.query = next.label;
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

            if (!confirm(`Hapus "${option.label || option.name}" dari master?`)) {
                return;
            }

            try {
                await this.request(`${this.apiBase}/${option.id}`, { method: 'DELETE' });
                this.options = this.options.filter((o) => o.id !== option.id);
                this.pushOptionsToStore();

                if (this.selectedId === option.id) {
                    this.selectedId = '';
                    this.query = '';
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
                    body: JSON.stringify({ name, ...this.createDefaults }),
                });

                const option = {
                    id: created.id,
                    name: created.name,
                    label: created.label || created.name,
                    value: created.value || created.name,
                    ...created,
                };

                this.options.push(option);
                this.options.sort((a, b) => (a.label || a.name).localeCompare(b.label || b.name, 'id'));
                this.pushOptionsToStore();
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
                if (!this.filtered.length) return;
                this.highlighted = Math.min(this.highlighted + 1, this.filtered.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.highlighted = Math.max(this.highlighted - 1, 0);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (this.editingId) return;

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

    Alpine.data('employeeSalaryModal', (config = {}) => ({
        open: false,
        loading: false,
        saving: false,
        employeeName: '',
        updateUrl: '',
        baseSalary: 0,
        effectiveDate: '',
        allowances: [],
        deductions: [],
        history: [],
        csrf: config.csrf || '',
        allowanceApi: config.allowanceApi || '',
        deductionApi: config.deductionApi || '',
        rowKey: 0,

        init() {
            Alpine.store('salaryAllowanceTypes', { options: [] });
            Alpine.store('salaryDeductionTypes', { options: [] });

            this._onOpenSalary = (e) => {
                const employeeId = e.detail?.employeeId;
                if (employeeId) {
                    this.openFor(employeeId);
                }
            };

            window.addEventListener('open-employee-salary', this._onOpenSalary);
        },

        destroy() {
            if (this._onOpenSalary) {
                window.removeEventListener('open-employee-salary', this._onOpenSalary);
            }
        },

        get dailySalary() {
            return (Number(this.baseSalary) || 0) / 26;
        },

        get hourlySalary() {
            return this.dailySalary / 8;
        },

        formatRp(amount) {
            const n = Math.round(Number(amount) || 0);
            return 'Rp ' + n.toLocaleString('id-ID');
        },

        formatCurrency(value) {
            return window.formatCurrencyId(value);
        },

        applyCurrencyInput(event) {
            const digits = String(event.target.value).replace(/\D/g, '');
            const n = digits === '' ? 0 : parseInt(digits, 10);
            event.target.value = digits === '' ? '' : window.formatCurrencyId(n);
            return n;
        },

        async openFor(employeeId) {
            this.loading = true;
            this.open = true;
            this.history = [];

            try {
                const res = await fetch(`/employees/${employeeId}/salary/payload`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.message || 'Gagal memuat data gaji');
                }

                this.employeeName = data.employee.full_name;
                this.updateUrl = data.update_url;
                this.baseSalary = data.base_salary;
                this.effectiveDate = data.effective_date;
                this.history = data.history || [];
                this.allowances = (data.allowances || []).map((a) => ({
                    ...a,
                    _key: ++this.rowKey,
                }));
                this.deductions = (data.deductions || []).map((d) => ({
                    ...d,
                    _key: ++this.rowKey,
                }));

                Alpine.store('salaryAllowanceTypes').options = data.allowance_types || [];
                Alpine.store('salaryDeductionTypes').options = data.deduction_types || [];
            } catch (e) {
                alert(e.message || 'Gagal memuat pengaturan gaji.');
                this.open = false;
            } finally {
                this.loading = false;
            }
        },

        close() {
            this.open = false;
        },

        addAllowance() {
            this.allowances.push({
                _key: ++this.rowKey,
                allowance_type_id: '',
                amount: 0,
            });
        },

        addDeduction() {
            this.deductions.push({
                _key: ++this.rowKey,
                deduction_type_id: '',
                value: 0,
            });
        },

        removeAllowance(index) {
            this.allowances.splice(index, 1);
        },

        removeDeduction(index) {
            this.deductions.splice(index, 1);
        },

        async save() {
            if (this.saving || !this.updateUrl) {
                return;
            }

            for (const row of this.allowances) {
                if (!row.allowance_type_id) {
                    alert('Pilih jenis tunjangan untuk setiap baris.');
                    return;
                }
            }

            for (const row of this.deductions) {
                if (!row.deduction_type_id) {
                    alert('Pilih jenis potongan untuk setiap baris.');
                    return;
                }
            }

            this.saving = true;

            try {
                const res = await fetch(this.updateUrl, {
                    method: 'PUT',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        base_salary: this.baseSalary,
                        effective_date: this.effectiveDate,
                        allowances: this.allowances.map(({ allowance_type_id, amount }) => ({
                            allowance_type_id,
                            amount,
                        })),
                        deductions: this.deductions.map(({ deduction_type_id, value }) => ({
                            deduction_type_id,
                            value,
                        })),
                    }),
                });

                const data = await res.json().catch(() => ({}));

                if (!res.ok) {
                    throw new Error(
                        data.message ||
                            (data.errors && Object.values(data.errors).flat()[0]) ||
                            'Gagal menyimpan',
                    );
                }

                if (Array.isArray(data.history)) {
                    this.history = data.history;
                }

                this.close();
                window.dispatchEvent(new CustomEvent('salary-saved', {
                    detail: { message: data.message || 'Data gaji berhasil disimpan.' },
                }));
            } catch (e) {
                alert(e.message || 'Gagal menyimpan data gaji.');
            } finally {
                this.saving = false;
            }
        },
    }));
});
