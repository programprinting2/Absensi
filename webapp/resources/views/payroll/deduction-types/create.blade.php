<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tambah Jenis Potongan</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('payroll.deduction-types.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="name" value="Nama Potongan" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="calculation_method" value="Metode Perhitungan" />
                        <select id="calculation_method" name="calculation_method" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            <option value="fixed" {{ old('calculation_method') === 'fixed' ? 'selected' : '' }}>Nominal Tetap (Rp)</option>
                            <option value="percentage" {{ old('calculation_method') === 'percentage' ? 'selected' : '' }}>Persentase dari Gaji Pokok (%)</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="default_value" value="Nilai Default" />
                        <x-text-input id="default_value" name="default_value" type="text" inputmode="decimal" class="mt-1 block w-full text-right tabular-nums" value="{{ old('default_value', 0) }}" required />
                        <x-input-error :messages="$errors->get('default_value')" class="mt-2" />
                    </div>
                    <div class="flex justify-end items-center gap-4">
                        <a href="{{ route('payroll.deduction-types.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                        <x-primary-button>Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
