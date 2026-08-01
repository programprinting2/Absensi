<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Karyawan</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 text-green-800 text-sm px-4 py-3 rounded-md border border-green-200">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                <form id="employee-form" method="POST" action="{{ route('employees.update', $employee) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="employee_code" value="ID Karyawan (untuk keypad)" />
                        <x-text-input id="employee_code" type="number" class="mt-1 block w-full bg-gray-50 text-gray-500" value="{{ $employee->employee_code }}" readonly disabled />
                        <p class="mt-1 text-xs text-gray-400">Dibuat otomatis, tidak bisa diubah.</p>
                    </div>

                    <div>
                        <x-input-label for="full_name" value="Nama Lengkap" />
                        <x-text-input id="full_name" name="full_name" type="text" class="mt-1 block w-full" value="{{ old('full_name', $employee->full_name) }}" required />
                        <x-input-error :messages="$errors->get('full_name')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0">
                        <input id="is_active" type="checkbox" name="is_active" value="1"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                               {{ old('is_active', $employee->is_active) ? 'checked' : '' }}>
                        <x-input-label for="is_active" value="Aktif" />
                    </div>

                    <div class="flex justify-end items-center gap-4">
                        <a href="{{ route('employees.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                        <x-primary-button>Simpan</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-3">Sidik Jari Terdaftar</h3>

                <div class="space-y-2">
                    @forelse ($employee->fingerprintTemplates as $index => $template)
                        <div class="flex items-center justify-between bg-gray-50 rounded-md px-4 py-3">
                            <div class="text-sm text-gray-700">
                                <span class="font-semibold text-gray-900">Sidik Jari #{{ $index + 1 }}</span>
                                <span class="text-gray-400 block text-xs mt-0.5">
                                    {{ $template->device->name }} · slot #{{ $template->fingerprint_slot_id }}
                                    · terdaftar {{ $template->enrolled_at->format('d M Y H:i') }}
                                </span>
                            </div>

                            <form method="POST"
                                  action="{{ route('employees.fingerprint-templates.destroy', [$employee, $template]) }}"
                                  onsubmit="return confirm('Hapus sidik jari ini? Perintah hapus juga akan dikirim ke sensor.')">
                                @csrf
                                @method('DELETE')
                                <x-danger-button type="submit">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </x-danger-button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 py-2">Belum ada sidik jari terdaftar untuk karyawan ini.</p>
                    @endforelse
                </div>

                @php $devices = \App\Models\Device::where('is_active', true)->get(); @endphp

                <form method="POST" action="{{ route('employees.enroll-fingerprint', $employee) }}" class="mt-4 flex items-end gap-3">
                    @csrf
                    <div>
                        <x-input-label for="device_id" value="Device" />
                        <select id="device_id" name="device_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            @foreach ($devices as $device)
                                <option value="{{ $device->id }}">{{ $device->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-primary-button>Tambah Sidik Jari</x-primary-button>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-3">Pengaturan PIN</h3>

                <div class="space-y-4">
                    <p class="text-sm text-gray-500">
                        Status PIN:
                        @if ($employee->pin_hash)
                            <span class="font-medium text-green-700">sudah diatur</span>
                        @else
                            <span class="font-medium text-gray-500">belum diatur (absen dengan sidik jari saja)</span>
                        @endif
                    </p>

                    <x-pin-input form="employee-form" name="pin" label="PIN Baru (kosongkan jika tidak diubah)" />

                    <x-pin-input form="employee-form" name="pin_confirmation" label="Konfirmasi PIN Baru" />

                    <div class="flex justify-end">
                        <x-primary-button form="employee-form">Simpan PIN</x-primary-button>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6 border border-red-200">
                <h3 class="font-semibold text-red-700 mb-1">Zona Berbahaya</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Menghapus karyawan juga akan menghapus seluruh riwayat absensi dan sidik jari terdaftar miliknya. Tindakan ini tidak bisa dibatalkan.
                </p>

                <x-danger-button
                    x-data=""
                    x-on:click.prevent="$dispatch('open-modal', 'confirm-employee-deletion')"
                >Hapus Karyawan</x-danger-button>

                <x-modal name="confirm-employee-deletion" focusable>
                    <form method="POST" action="{{ route('employees.destroy', $employee) }}" class="p-6">
                        @csrf
                        @method('DELETE')

                        <h2 class="text-lg font-medium text-gray-900">
                            Hapus {{ $employee->full_name }}?
                        </h2>

                        <p class="mt-1 text-sm text-gray-600">
                            Karyawan ini beserta seluruh riwayat absensi dan sidik jari terdaftarnya akan dihapus permanen dari database. Tindakan ini tidak bisa dibatalkan.
                        </p>

                        <div class="mt-6 flex justify-end">
                            <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                Batal
                            </x-secondary-button>

                            <x-danger-button class="ms-3">
                                Hapus Karyawan
                            </x-danger-button>
                        </div>
                    </form>
                </x-modal>
            </div>
        </div>
    </div>
</x-app-layout>
