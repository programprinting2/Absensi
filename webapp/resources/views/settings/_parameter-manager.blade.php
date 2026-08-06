{{-- Master Parameter manager (kategori + detail) --}}
<div x-show="activeTab === 'parameter'" x-cloak class="flex-1 min-h-0 flex">
<div class="flex-1 min-h-0 flex"
     x-data="parameterManager()"
     x-init="init()">
    {{-- Kategori sidebar --}}
    <aside class="w-64 flex-shrink-0 border-r border-gray-200 bg-white flex flex-col">
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Kategori</h3>
                <p class="text-xs text-gray-400">Master parameter</p>
            </div>
            <button type="button"
                    @click="openCategoryForm()"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-[#f7340d] text-white hover:bg-[#d92b0a] transition"
                    title="Tambah kategori">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
            </button>
        </div>

        <div class="px-3 py-2 border-b border-gray-100">
            <input type="text"
                   x-model="categorySearch"
                   placeholder="Cari kategori..."
                   class="block w-full text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
        </div>

        <ul class="flex-1 overflow-y-auto py-1">
            <template x-if="loadingCategories">
                <li class="px-4 py-6 text-sm text-gray-400 text-center">Memuat...</li>
            </template>
            <template x-if="!loadingCategories && filteredCategories.length === 0">
                <li class="px-4 py-6 text-sm text-gray-400 text-center">Belum ada kategori</li>
            </template>
            <template x-for="cat in filteredCategories" :key="cat.id">
                <li>
                    <div @click="selectCategory(cat)"
                         :class="selectedCategoryId === cat.id
                            ? 'bg-orange-50 border-l-2 border-[#f7340d]'
                            : 'border-l-2 border-transparent hover:bg-gray-50'"
                         class="group px-3 py-2.5 cursor-pointer transition-colors flex items-start gap-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate"
                               :class="selectedCategoryId === cat.id ? 'text-[#f7340d]' : 'text-gray-800'"
                               x-text="cat.name"></p>
                            <p class="text-xs text-gray-400 truncate mt-0.5"
                               x-text="cat.description || (cat.details_count + ' detail')"></p>
                        </div>
                        <div class="opacity-0 group-hover:opacity-100 flex items-center gap-0.5 shrink-0"
                             :class="selectedCategoryId === cat.id && '!opacity-100'">
                            <button type="button"
                                    @click.stop="openCategoryForm(cat)"
                                    class="p-1 rounded text-gray-400 hover:text-indigo-600 hover:bg-indigo-50"
                                    title="Edit">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button type="button"
                                    @click.stop="deleteCategory(cat)"
                                    class="p-1 rounded text-gray-400 hover:text-red-600 hover:bg-red-50"
                                    title="Hapus">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </li>
            </template>
        </ul>
    </aside>

    {{-- Detail panel --}}
    <div class="flex-1 min-w-0 flex flex-col">
        <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between gap-3 flex-wrap">
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-gray-900 truncate"
                    x-text="selectedCategory ? selectedCategory.name : 'Detail Parameter'"></h3>
                <p class="text-xs text-gray-400 mt-0.5"
                   x-text="selectedCategory ? (selectedCategory.description || 'Daftar nilai parameter') : 'Pilih kategori di sebelah kiri'"></p>
            </div>
            <div class="flex items-center gap-2" x-show="selectedCategory">
                <input type="text"
                       x-model="detailSearch"
                       placeholder="Cari detail..."
                       class="text-sm border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 w-44" />
                <button type="button"
                        @click="openDetailForm()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold uppercase tracking-widest rounded-md bg-gray-800 text-white hover:bg-gray-700 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-auto p-5">
            <template x-if="!selectedCategory">
                <div class="rounded-lg border border-dashed border-gray-300 px-6 py-16 text-center">
                    <p class="text-sm text-gray-500">Pilih atau buat kategori parameter untuk mengelola detailnya.</p>
                </div>
            </template>

            <template x-if="selectedCategory">
                <div>
                    <template x-if="loadingDetails">
                        <p class="text-sm text-gray-400 text-center py-10">Memuat detail...</p>
                    </template>

                    <template x-if="!loadingDetails && filteredDetails.length === 0">
                        <div class="rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center">
                            <p class="text-sm text-gray-500">Belum ada detail pada kategori ini.</p>
                        </div>
                    </template>

                    <template x-if="!loadingDetails && filteredDetails.length > 0">
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nama</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nilai</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Keterangan</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider w-28">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <template x-for="detail in filteredDetails" :key="detail.id">
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2.5 font-medium text-gray-900" x-text="detail.name"></td>
                                            <td class="px-4 py-2.5 text-gray-600" x-text="detail.value || '—'"></td>
                                            <td class="px-4 py-2.5 text-gray-500" x-text="detail.description || '—'"></td>
                                            <td class="px-4 py-2.5">
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
                                                      :class="detail.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                                      x-text="detail.is_active ? 'Aktif' : 'Nonaktif'"></span>
                                            </td>
                                            <td class="px-4 py-2.5 text-right">
                                                <button type="button" @click="openDetailForm(detail)"
                                                        class="text-indigo-600 hover:text-indigo-800 text-xs font-medium mr-2">Edit</button>
                                                <button type="button" @click="deleteDetail(detail)"
                                                        class="text-red-600 hover:text-red-800 text-xs font-medium">Hapus</button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- Modal Kategori --}}
    <div x-show="showCategoryModal" x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-500/75"></div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md p-5" @click.stop>
            <h4 class="text-base font-semibold text-gray-900 mb-4"
                x-text="categoryForm.id ? 'Edit Kategori' : 'Tambah Kategori'"></h4>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nama Kategori</label>
                    <input type="text" x-model="categoryForm.name"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Contoh: DEPARTEMEN" />
                    <p class="mt-1 text-xs text-red-600" x-text="categoryErrors.name" x-show="categoryErrors.name"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Keterangan</label>
                    <textarea x-model="categoryForm.description" rows="2"
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" x-model="categoryForm.is_active"
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    Aktif
                </label>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" @click="showCategoryModal = false"
                        class="px-3 py-1.5 text-xs font-semibold uppercase tracking-widest rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Batal</button>
                <button type="button" @click="saveCategory" :disabled="saving"
                        class="px-3 py-1.5 text-xs font-semibold uppercase tracking-widest rounded-md bg-gray-800 text-white hover:bg-gray-700 disabled:opacity-50">
                    <span x-text="saving ? 'Menyimpan...' : 'Simpan'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Modal Detail --}}
    <div x-show="showDetailModal" x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-500/75"></div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md p-5" @click.stop>
            <h4 class="text-base font-semibold text-gray-900 mb-4"
                x-text="detailForm.id ? 'Edit Detail' : 'Tambah Detail'"></h4>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nama</label>
                    <input type="text" x-model="detailForm.name"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Contoh: HRD" />
                    <p class="mt-1 text-xs text-red-600" x-text="detailErrors.name" x-show="detailErrors.name"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nilai</label>
                    <input type="text" x-model="detailForm.value"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="Opsional, default = nama" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Keterangan</label>
                    <textarea x-model="detailForm.description" rows="2"
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" x-model="detailForm.is_active"
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    Aktif
                </label>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" @click="showDetailModal = false"
                        class="px-3 py-1.5 text-xs font-semibold uppercase tracking-widest rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Batal</button>
                <button type="button" @click="saveDetail" :disabled="saving"
                        class="px-3 py-1.5 text-xs font-semibold uppercase tracking-widest rounded-md bg-gray-800 text-white hover:bg-gray-700 disabled:opacity-50">
                    <span x-text="saving ? 'Menyimpan...' : 'Simpan'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
</div>

@once
@push('scripts')
<script>
function parameterManager() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const base = @json(url('/settings/parameters'));

    return {
        categories: [],
        details: [],
        selectedCategoryId: null,
        categorySearch: '',
        detailSearch: '',
        loadingCategories: false,
        loadingDetails: false,
        saving: false,
        showCategoryModal: false,
        showDetailModal: false,
        categoryForm: { id: null, name: '', description: '', is_active: true },
        detailForm: { id: null, name: '', value: '', description: '', is_active: true },
        categoryErrors: {},
        detailErrors: {},

        get selectedCategory() {
            return this.categories.find(c => c.id === this.selectedCategoryId) || null;
        },

        get filteredCategories() {
            const q = this.categorySearch.trim().toLowerCase();
            if (!q) return this.categories;
            return this.categories.filter(c =>
                c.name.toLowerCase().includes(q) ||
                (c.description || '').toLowerCase().includes(q)
            );
        },

        get filteredDetails() {
            const q = this.detailSearch.trim().toLowerCase();
            if (!q) return this.details;
            return this.details.filter(d =>
                d.name.toLowerCase().includes(q) ||
                (d.value || '').toLowerCase().includes(q) ||
                (d.description || '').toLowerCase().includes(q)
            );
        },

        async init() {
            await this.loadCategories();
        },

        async request(url, options = {}) {
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                ...options,
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                const err = new Error(data.message || 'Request failed');
                err.status = res.status;
                err.errors = data.errors || {};
                throw err;
            }

            return data;
        },

        async loadCategories() {
            this.loadingCategories = true;
            try {
                this.categories = await this.request(base);
                if (this.selectedCategoryId && !this.categories.find(c => c.id === this.selectedCategoryId)) {
                    this.selectedCategoryId = null;
                    this.details = [];
                }
                if (!this.selectedCategoryId && this.categories.length) {
                    await this.selectCategory(this.categories[0]);
                }
            } catch (e) {
                console.error(e);
                window.showToast('Gagal memuat kategori parameter.', 'error');
            } finally {
                this.loadingCategories = false;
            }
        },

        async selectCategory(cat) {
            this.selectedCategoryId = cat.id;
            this.detailSearch = '';
            this.loadingDetails = true;
            try {
                this.details = await this.request(`${base}/${cat.id}/details`);
            } catch (e) {
                console.error(e);
                this.details = [];
                window.showToast('Gagal memuat detail parameter.', 'error');
            } finally {
                this.loadingDetails = false;
            }
        },

        openCategoryForm(cat = null) {
            this.categoryErrors = {};
            this.categoryForm = cat
                ? { id: cat.id, name: cat.name, description: cat.description || '', is_active: !!cat.is_active }
                : { id: null, name: '', description: '', is_active: true };
            this.showCategoryModal = true;
        },

        async saveCategory() {
            this.saving = true;
            this.categoryErrors = {};
            try {
                const payload = {
                    name: this.categoryForm.name.trim(),
                    description: this.categoryForm.description || null,
                    is_active: this.categoryForm.is_active,
                };

                if (this.categoryForm.id) {
                    await this.request(`${base}/${this.categoryForm.id}`, {
                        method: 'PUT',
                        body: JSON.stringify(payload),
                    });
                } else {
                    await this.request(base, {
                        method: 'POST',
                        body: JSON.stringify(payload),
                    });
                }

                this.showCategoryModal = false;
                await this.loadCategories();
            } catch (e) {
                if (e.errors) {
                    this.categoryErrors = Object.fromEntries(
                        Object.entries(e.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
                    );
                } else {
                    window.showToast(e.message || 'Gagal menyimpan kategori.', 'error');
                }
            } finally {
                this.saving = false;
            }
        },

        async deleteCategory(cat) {
            if (!confirm(`Hapus kategori "${cat.name}" beserta semua detailnya?`)) return;
            try {
                await this.request(`${base}/${cat.id}`, { method: 'DELETE' });
                if (this.selectedCategoryId === cat.id) {
                    this.selectedCategoryId = null;
                    this.details = [];
                }
                await this.loadCategories();
            } catch (e) {
                window.showToast(e.message || 'Gagal menghapus kategori.', 'error');
            }
        },

        openDetailForm(detail = null) {
            this.detailErrors = {};
            this.detailForm = detail
                ? {
                    id: detail.id,
                    name: detail.name,
                    value: detail.value || '',
                    description: detail.description || '',
                    is_active: !!detail.is_active,
                }
                : { id: null, name: '', value: '', description: '', is_active: true };
            this.showDetailModal = true;
        },

        async saveDetail() {
            if (!this.selectedCategoryId) return;
            this.saving = true;
            this.detailErrors = {};
            try {
                const payload = {
                    name: this.detailForm.name.trim(),
                    value: this.detailForm.value.trim() || null,
                    description: this.detailForm.description || null,
                    is_active: this.detailForm.is_active,
                };

                if (this.detailForm.id) {
                    await this.request(`${base}/${this.selectedCategoryId}/details/${this.detailForm.id}`, {
                        method: 'PUT',
                        body: JSON.stringify(payload),
                    });
                } else {
                    await this.request(`${base}/${this.selectedCategoryId}/details`, {
                        method: 'POST',
                        body: JSON.stringify(payload),
                    });
                }

                this.showDetailModal = false;
                await this.selectCategory(this.selectedCategory);
                await this.loadCategories();
            } catch (e) {
                if (e.errors) {
                    this.detailErrors = Object.fromEntries(
                        Object.entries(e.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
                    );
                } else {
                    window.showToast(e.message || 'Gagal menyimpan detail.', 'error');
                }
            } finally {
                this.saving = false;
            }
        },

        async deleteDetail(detail) {
            if (!confirm(`Hapus detail "${detail.name}"?`)) return;
            try {
                await this.request(`${base}/${this.selectedCategoryId}/details/${detail.id}`, {
                    method: 'DELETE',
                });
                await this.selectCategory(this.selectedCategory);
                await this.loadCategories();
            } catch (e) {
                window.showToast(e.message || 'Gagal menghapus detail.', 'error');
            }
        },
    };
}
</script>
@endpush
@endonce
