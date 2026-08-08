<div x-show="activeTab === 'pph21'" x-cloak class="flex-1 overflow-y-auto p-6">
    <div class="mb-5">
        <h3 class="text-base font-semibold text-gray-900">Pajak PPh 21</h3>
        <p class="text-sm text-gray-500 mt-0.5">Aktifkan dan atur metode perhitungan PPh 21 pada penggajian.</p>
    </div>

    <form method="POST" action="{{ route('settings.pph21.update') }}" class="space-y-5 max-w-lg">
        @csrf
        @method('PUT')

        <div class="flex items-center gap-3">
            <input id="enable_pph21" name="enable_pph21" type="checkbox" value="1"
                   class="rounded border-gray-300 text-[#f7340d] shadow-sm focus:ring-[#f7340d]"
                   {{ old('enable_pph21', $payrollSettings->enable_pph21) ? 'checked' : '' }}>
            <x-input-label for="enable_pph21" value="Aktifkan perhitungan PPh 21" />
        </div>

        <div>
            <x-input-label for="pph21_method" value="Metode PPh 21" />
            <select id="pph21_method" name="pph21_method"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-[#f7340d] focus:border-[#f7340d] text-sm">
                <option value="gross" {{ old('pph21_method', $payrollSettings->pph21_method) === 'gross' ? 'selected' : '' }}>Gross</option>
                <option value="nett" {{ old('pph21_method', $payrollSettings->pph21_method) === 'nett' ? 'selected' : '' }}>Nett</option>
                <option value="gross_up" {{ old('pph21_method', $payrollSettings->pph21_method) === 'gross_up' ? 'selected' : '' }}>Gross Up</option>
            </select>
            <x-input-error :messages="$errors->get('pph21_method')" class="mt-2" />
        </div>

        <div class="flex justify-end pt-2 border-t border-gray-100">
            <x-primary-button class="mt-4">Simpan PPh 21</x-primary-button>
        </div>
    </form>
</div>
