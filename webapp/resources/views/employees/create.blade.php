<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tambah Karyawan</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('employees.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="employee_code" value="ID Karyawan (untuk keypad)" />
                        <x-text-input id="employee_code" type="number" class="mt-1 block w-full bg-gray-50 text-gray-500" value="{{ $nextEmployeeCode }}" readonly disabled />
                        <p class="mt-1 text-xs text-gray-400">Dibuat otomatis, tidak bisa diubah.</p>
                    </div>

                    <div>
                        <x-input-label for="full_name" value="Nama Lengkap" />
                        <x-text-input id="full_name" name="full_name" type="text" class="mt-1 block w-full" value="{{ old('full_name') }}" required />
                        <x-input-error :messages="$errors->get('full_name')" class="mt-2" />
                    </div>

                    <p class="text-sm text-gray-500">
                        Karyawan absen dengan sidik jari secara default. PIN bersifat opsional —
                        bisa ditambahkan nanti di halaman Edit, khusus untuk karyawan yang sidik jarinya
                        sulit terbaca sensor.
                    </p>

                    <div class="flex justify-end items-center gap-4">
                        <a href="{{ route('employees.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                        <x-primary-button>Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
