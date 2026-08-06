<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('employees.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Karyawan</a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengaturan Gaji — {{ $employee->full_name }}</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('employees.salary.update', $employee) }}" class="space-y-6" x-data="salaryForm()">
                @csrf
                @method('PUT')

                <!-- Base Salary -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Gaji Pokok</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="base_salary" value="Gaji Pokok (Rp)" />
                            <x-currency-input id="base_salary" name="base_salary" class="mt-1 block w-full" :value="old('base_salary', $employee->activeSalary?->base_salary ?? 0)" />
                            <x-input-error :messages="$errors->get('base_salary')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="effective_date" value="Berlaku Sejak" />
                            <x-text-input id="effective_date" name="effective_date" type="date" class="mt-1 block w-full" value="{{ old('effective_date', $employee->activeSalary?->effective_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required />
                            <x-input-error :messages="$errors->get('effective_date')" class="mt-2" />
                        </div>
                    </div>
                </div>

                <!-- Allowances -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900">Tunjangan</h3>
                        <button type="button" @click="addAllowance()" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Tambah</button>
                    </div>

                    <template x-if="allowances.length === 0">
                        <p class="text-sm text-gray-500">Belum ada tunjangan. Klik "+ Tambah" untuk menambahkan.</p>
                    </template>

                    <template x-for="(item, index) in allowances" :key="index">
                        <div class="flex gap-3 mb-3 items-end">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700" x-show="index === 0">Jenis</label>
                                <select :name="`allowances[${index}][allowance_type_id]`" x-model="item.allowance_type_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" required>
                                    <option value="">Pilih...</option>
                                    @foreach ($allowanceTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="w-40">
                                <label class="block text-sm font-medium text-gray-700" x-show="index === 0">Jumlah (Rp)</label>
                                <input :name="`allowances[${index}][amount]`" type="hidden" :value="item.amount">
                                <input type="text" inputmode="numeric" autocomplete="off" required
                                       :value="window.formatCurrencyId(item.amount)"
                                       @input="item.amount = window.parseCurrencyId($event.target.value); $event.target.value = $event.target.value.replace(/\D/g, '') === '' ? '' : window.formatCurrencyId(item.amount)"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm text-right tabular-nums">
                            </div>
                            <button type="button" @click="allowances.splice(index, 1)" class="text-red-500 hover:text-red-700 pb-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Deductions -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-900">Potongan</h3>
                        <button type="button" @click="addDeduction()" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Tambah</button>
                    </div>

                    <template x-if="deductions.length === 0">
                        <p class="text-sm text-gray-500">Belum ada potongan. Klik "+ Tambah" untuk menambahkan.</p>
                    </template>

                    <template x-for="(item, index) in deductions" :key="index">
                        <div class="flex gap-3 mb-3 items-end">
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700" x-show="index === 0">Jenis</label>
                                <select :name="`deductions[${index}][deduction_type_id]`" x-model="item.deduction_type_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" required>
                                    <option value="">Pilih...</option>
                                    @foreach ($deductionTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }} ({{ $type->calculation_method === 'fixed' ? 'Rp' : '%' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="w-40">
                                <label class="block text-sm font-medium text-gray-700" x-show="index === 0">Nilai</label>
                                <input :name="`deductions[${index}][value]`" type="hidden" :value="item.value">
                                <input type="text" inputmode="numeric" autocomplete="off" required
                                       :value="window.formatCurrencyId(item.value)"
                                       @input="item.value = window.parseCurrencyId($event.target.value); $event.target.value = $event.target.value.replace(/\D/g, '') === '' ? '' : window.formatCurrencyId(item.value)"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm text-right tabular-nums">
                            </div>
                            <button type="button" @click="deductions.splice(index, 1)" class="text-red-500 hover:text-red-700 pb-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </template>
                </div>

                <div class="flex justify-end items-center gap-4">
                    <a href="{{ route('employees.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                    <x-primary-button>Simpan</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function salaryForm() {
            return {
                allowances: @json($employee->employeeAllowances->map(fn($a) => ['allowance_type_id' => $a->allowance_type_id, 'amount' => $a->amount])),
                deductions: @json($employee->employeeDeductions->map(fn($d) => ['deduction_type_id' => $d->deduction_type_id, 'value' => $d->value])),
                addAllowance() { this.allowances.push({ allowance_type_id: '', amount: 0 }) },
                addDeduction() { this.deductions.push({ deduction_type_id: '', value: 0 }) },
            }
        }
    </script>
</x-app-layout>
