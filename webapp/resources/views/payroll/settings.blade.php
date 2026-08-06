<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('payroll.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali</a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengaturan Payroll</h2>
        </div>
    </x-slot>

    <div class="h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-4 overflow-y-auto">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('payroll.settings.update') }}" class="space-y-4 max-w-3xl">
                    @csrf
                    @method('PUT')

                    <h3 class="font-semibold text-gray-900">Periode Cutoff</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="cutoff_start_day" value="Tanggal Mulai" />
                            <x-text-input id="cutoff_start_day" name="cutoff_start_day" type="number" min="1" max="31" class="mt-1 block w-full" value="{{ old('cutoff_start_day', $settings->cutoff_start_day) }}" required />
                            <x-input-error :messages="$errors->get('cutoff_start_day')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="cutoff_end_day" value="Tanggal Akhir" />
                            <x-text-input id="cutoff_end_day" name="cutoff_end_day" type="number" min="1" max="31" class="mt-1 block w-full" value="{{ old('cutoff_end_day', $settings->cutoff_end_day) }}" required />
                            <x-input-error :messages="$errors->get('cutoff_end_day')" class="mt-2" />
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">Contoh: mulai 21 akhir 20 = tanggal 21 bulan lalu s/d 20 bulan ini.</p>

                    <h3 class="font-semibold text-gray-900 pt-4">Denda & Potongan Absensi</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="late_penalty_per_incident" value="Denda per Terlambat (Rp)" />
                            <x-currency-input id="late_penalty_per_incident" name="late_penalty_per_incident" class="mt-1 block w-full" :value="old('late_penalty_per_incident', $settings->late_penalty_per_incident)" />
                        </div>
                        <div>
                            <x-input-label for="absent_penalty_per_day" value="Potongan per Hari Tidak Masuk (Rp)" />
                            <x-currency-input id="absent_penalty_per_day" name="absent_penalty_per_day" class="mt-1 block w-full" :value="old('absent_penalty_per_day', $settings->absent_penalty_per_day)" />
                        </div>
                        <div>
                            <x-input-label for="early_out_penalty_per_incident" value="Denda per Pulang Cepat (Rp)" />
                            <x-currency-input id="early_out_penalty_per_incident" name="early_out_penalty_per_incident" class="mt-1 block w-full" :value="old('early_out_penalty_per_incident', $settings->early_out_penalty_per_incident)" />
                        </div>
                        <div>
                            <x-input-label for="over_break_penalty_per_incident" value="Denda per Over Break (Rp)" />
                            <x-currency-input id="over_break_penalty_per_incident" name="over_break_penalty_per_incident" class="mt-1 block w-full" :value="old('over_break_penalty_per_incident', $settings->over_break_penalty_per_incident ?? 0)" />
                        </div>
                        <div>
                            <x-input-label for="short_work_penalty_per_hour" value="Potongan per Jam Kerja Kurang (Rp)" />
                            <x-currency-input id="short_work_penalty_per_hour" name="short_work_penalty_per_hour" class="mt-1 block w-full" :value="old('short_work_penalty_per_hour', $settings->short_work_penalty_per_hour ?? 0)" />
                        </div>
                    </div>

                    <h3 class="font-semibold text-gray-900 pt-4">Lembur</h3>
                    <div class="max-w-sm">
                        <x-input-label for="overtime_rate_per_hour" value="Tarif Lembur per Jam (Rp)" />
                        <x-currency-input id="overtime_rate_per_hour" name="overtime_rate_per_hour" class="mt-1 block w-full" :value="old('overtime_rate_per_hour', $settings->overtime_rate_per_hour)" />
                    </div>

                    <h3 class="font-semibold text-gray-900 pt-4">Pajak PPh 21</h3>
                    <div class="flex items-center gap-3">
                        <input id="enable_pph21" name="enable_pph21" type="checkbox" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('enable_pph21', $settings->enable_pph21) ? 'checked' : '' }}>
                        <x-input-label for="enable_pph21" value="Aktifkan perhitungan PPh 21" />
                    </div>
                    <div class="max-w-sm">
                        <x-input-label for="pph21_method" value="Metode PPh 21" />
                        <select id="pph21_method" name="pph21_method" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="gross" {{ old('pph21_method', $settings->pph21_method) === 'gross' ? 'selected' : '' }}>Gross</option>
                            <option value="nett" {{ old('pph21_method', $settings->pph21_method) === 'nett' ? 'selected' : '' }}>Nett</option>
                            <option value="gross_up" {{ old('pph21_method', $settings->pph21_method) === 'gross_up' ? 'selected' : '' }}>Gross Up</option>
                        </select>
                    </div>

                    <div class="flex justify-end pt-4">
                        <x-primary-button>Simpan Pengaturan</x-primary-button>
                    </div>
                </form>
            </div>

            <!-- Quick links to component management -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Komponen Gaji</h3>
                <div class="flex gap-3">
                    <a href="{{ route('payroll.allowance-types.index') }}" class="inline-flex items-center px-4 py-3 border border-gray-200 rounded-lg hover:bg-gray-50 text-sm font-medium text-gray-700">
                        Jenis Tunjangan
                    </a>
                    <a href="{{ route('payroll.deduction-types.index') }}" class="inline-flex items-center px-4 py-3 border border-gray-200 rounded-lg hover:bg-gray-50 text-sm font-medium text-gray-700">
                        Jenis Potongan
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
