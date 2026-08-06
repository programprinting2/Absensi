<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tambah Jenis Tunjangan</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('payroll.allowance-types.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="name" value="Nama Tunjangan" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div class="flex items-center gap-3">
                        <input id="is_fixed" name="is_fixed" type="checkbox" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('is_fixed', true) ? 'checked' : '' }}>
                        <x-input-label for="is_fixed" value="Tunjangan Tetap" />
                    </div>
                    <div class="flex justify-end items-center gap-4">
                        <a href="{{ route('payroll.allowance-types.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                        <x-primary-button>Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
