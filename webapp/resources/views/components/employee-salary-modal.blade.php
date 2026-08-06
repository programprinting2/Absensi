{{-- Salary modal: z-[60] di atas modal karyawan (z-50); dropdown autocomplete z-[100] --}}
<div
    x-data="employeeSalaryModal({
        csrf: @js(csrf_token()),
        allowanceApi: @js(url('/payroll/allowance-types')),
        deductionApi: @js(url('/payroll/deduction-types')),
    })"
>
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-[60] overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div class="fixed inset-0 bg-gray-900/50 transition-opacity"></div>

            <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
                <div
                    @click.stop
                    class="relative w-full max-w-2xl max-h-[90vh] bg-white rounded-xl shadow-2xl flex flex-col overflow-hidden"
                >
                    <div class="flex items-center justify-between px-6 py-3.5 border-b border-gray-200 bg-gray-50 shrink-0">
                        <div>
                            <p class="text-xs text-gray-500">Pengaturan Gaji</p>
                            <h2 class="text-lg font-semibold text-gray-900" x-text="employeeName || '…'"></h2>
                        </div>
                        <button type="button" @click="close()" class="text-gray-400 hover:text-gray-600 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-5 space-y-5">
                        <div x-show="loading" class="py-12 text-center text-sm text-gray-500">Memuat data gaji…</div>

                        <div x-show="!loading" x-cloak class="space-y-5">
                            <div class="rounded-lg border border-gray-200 p-4">
                                <h3 class="font-semibold text-gray-900 mb-1">Gaji Pokok Saat Ini</h3>
                                <p class="text-xs text-gray-500 mb-3">Ubah nominal + tanggal berlaku untuk mencatat kenaikan/penurunan ke riwayat.</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Gaji Pokok (Rp)</label>
                                        <input type="text" inputmode="numeric" autocomplete="off"
                                               :value="formatCurrency(baseSalary)"
                                               @input="baseSalary = applyCurrencyInput($event)"
                                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm text-right tabular-nums" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Berlaku Sejak</label>
                                        <input type="date" x-model="effectiveDate"
                                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                                    </div>
                                </div>
                                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-md bg-gray-50 border border-gray-100 px-3 py-2.5">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-medium text-gray-600">Gaji Per Hari</p>
                                            <p class="text-[11px] text-gray-400">Gaji pokok ÷ 26 hari kerja</p>
                                        </div>
                                        <p class="text-sm font-semibold text-gray-900 tabular-nums text-right shrink-0" x-text="formatRp(dailySalary)"></p>
                                    </div>
                                    <div class="flex items-baseline justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-medium text-gray-600">Gaji Per Jam</p>
                                            <p class="text-[11px] text-gray-400">Gaji per hari ÷ 8 jam</p>
                                        </div>
                                        <p class="text-sm font-semibold text-gray-900 tabular-nums text-right shrink-0" x-text="formatRp(hourlySalary)"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4">
                                <h3 class="font-semibold text-gray-900 mb-1">Riwayat Gaji</h3>
                                <p class="text-xs text-gray-500 mb-4">Jejak gaji awal dan setiap perubahan (kenaikan/penurunan).</p>

                                <template x-if="history.length === 0">
                                    <p class="text-sm text-gray-500">Belum ada riwayat. Simpan gaji pokok pertama untuk mulai mencatat.</p>
                                </template>

                                <ol x-show="history.length > 0" class="relative ms-3 border-s border-gray-200 space-y-4">
                                    <template x-for="item in history" :key="item.id">
                                        <li class="ms-4">
                                            <span
                                                class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full border border-white"
                                                :class="item.is_active ? 'bg-green-500' : (item.change > 0 ? 'bg-blue-500' : (item.change < 0 ? 'bg-amber-500' : 'bg-gray-400'))"
                                            ></span>
                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                <div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <p class="text-sm font-semibold text-gray-900" x-text="item.label"></p>
                                                        <span x-show="item.is_active" class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-medium text-green-700">Aktif</span>
                                                    </div>
                                                    <p class="text-xs text-gray-500 mt-0.5" x-text="item.effective_date_label"></p>
                                                    <p class="text-xs text-gray-400 mt-0.5" x-show="item.note" x-text="item.note"></p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-sm font-semibold text-gray-900 tabular-nums" x-text="item.base_salary_label"></p>
                                                    <p class="text-xs font-medium mt-0.5"
                                                       x-show="item.change_label"
                                                       :class="item.change > 0 ? 'text-green-600' : (item.change < 0 ? 'text-red-600' : 'text-gray-500')"
                                                       x-text="item.change_label"></p>
                                                </div>
                                            </div>
                                        </li>
                                    </template>
                                </ol>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="font-semibold text-gray-900">Tunjangan</h3>
                                    <button type="button" @click="addAllowance()" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Tambah</button>
                                </div>

                                <template x-if="allowances.length === 0">
                                    <p class="text-sm text-gray-500">Belum ada tunjangan. Klik "+ Tambah" untuk menambahkan.</p>
                                </template>

                                <template x-for="(item, index) in allowances" :key="item._key">
                                    <div class="flex gap-3 mb-3 items-end">
                                        <div class="flex-1 min-w-0">
                                            <label class="block text-sm font-medium text-gray-700" x-show="index === 0">Jenis</label>
                                            <x-type-autocomplete
                                                class="mt-1"
                                                store-key="salaryAllowanceTypes"
                                                :api-base="url('/payroll/allowance-types')"
                                                placeholder="Pilih atau tambah tunjangan"
                                                :create-defaults="['is_fixed' => true]"
                                                x-model="item.allowance_type_id"
                                            />
                                        </div>
                                        <div class="w-36 shrink-0">
                                            <label class="block text-sm font-medium text-gray-700" x-show="index === 0">Jumlah (Rp)</label>
                                            <input type="text" inputmode="numeric" autocomplete="off"
                                                   :value="formatCurrency(item.amount)"
                                                   @input="item.amount = applyCurrencyInput($event)"
                                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm text-right tabular-nums" />
                                        </div>
                                        <button type="button" @click="removeAllowance(index)" class="text-red-500 hover:text-red-700 pb-2 shrink-0" title="Hapus baris">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="font-semibold text-gray-900">Potongan</h3>
                                    <button type="button" @click="addDeduction()" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Tambah</button>
                                </div>

                                <template x-if="deductions.length === 0">
                                    <p class="text-sm text-gray-500">Belum ada potongan. Klik "+ Tambah" untuk menambahkan.</p>
                                </template>

                                <template x-for="(item, index) in deductions" :key="item._key">
                                    <div class="flex gap-3 mb-3 items-end">
                                        <div class="flex-1 min-w-0">
                                            <label class="block text-sm font-medium text-gray-700" x-show="index === 0">Jenis</label>
                                            <x-type-autocomplete
                                                class="mt-1"
                                                store-key="salaryDeductionTypes"
                                                :api-base="url('/payroll/deduction-types')"
                                                placeholder="Pilih atau tambah potongan"
                                                :create-defaults="['calculation_method' => 'fixed', 'default_value' => 0]"
                                                x-model="item.deduction_type_id"
                                            />
                                        </div>
                                        <div class="w-36 shrink-0">
                                            <label class="block text-sm font-medium text-gray-700" x-show="index === 0">Nilai</label>
                                            <input type="text" inputmode="decimal" autocomplete="off"
                                                   :value="formatCurrency(item.value)"
                                                   @input="item.value = applyCurrencyInput($event)"
                                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm text-right tabular-nums" />
                                        </div>
                                        <button type="button" @click="removeDeduction(index)" class="text-red-500 hover:text-red-700 pb-2 shrink-0" title="Hapus baris">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 px-6 py-3.5 border-t border-gray-200 bg-gray-50 shrink-0">
                        <button type="button" @click="close()"
                                class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition">
                            Batal
                        </button>
                        <button type="button" @click="save()" :disabled="saving || loading"
                                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition disabled:opacity-50">
                            <span x-text="saving ? 'Menyimpan…' : 'Simpan'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
