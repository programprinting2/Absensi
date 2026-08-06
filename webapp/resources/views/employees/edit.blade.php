<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Karyawan</h2>
            <a href="{{ route('employees.salary', $employee) }}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                Pengaturan Gaji
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form id="employee-form" method="POST" action="{{ route('employees.update', $employee) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Data Dasar -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Data Dasar</h3>
                    <div class="space-y-4">
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

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="nik" value="NIK (KTP)" />
                                <x-text-input id="nik" name="nik" type="text" maxlength="16" class="mt-1 block w-full" value="{{ old('nik', $employee->nik) }}" />
                                <x-input-error :messages="$errors->get('nik')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="phone" value="No. Telepon" />
                                <x-text-input id="phone" name="phone" type="text" maxlength="20" class="mt-1 block w-full" value="{{ old('phone', $employee->phone) }}" />
                                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="address" value="Alamat" />
                            <textarea id="address" name="address" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('address', $employee->address) }}</textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0">
                            <input id="is_active" type="checkbox" name="is_active" value="1"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                   {{ old('is_active', $employee->is_active) ? 'checked' : '' }}>
                            <x-input-label for="is_active" value="Aktif" />
                        </div>
                    </div>
                </div>

                <!-- Data Kepegawaian -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Data Kepegawaian</h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="position" value="Jabatan" />
                                <x-text-input id="position" name="position" type="text" class="mt-1 block w-full" value="{{ old('position', $employee->position) }}" />
                                <x-input-error :messages="$errors->get('position')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="department" value="Departemen" />
                                <x-text-input id="department" name="department" type="text" class="mt-1 block w-full" value="{{ old('department', $employee->department) }}" />
                                <x-input-error :messages="$errors->get('department')" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="join_date" value="Tanggal Bergabung" />
                            <x-text-input id="join_date" name="join_date" type="date" class="mt-1 block w-full" value="{{ old('join_date', $employee->join_date?->format('Y-m-d')) }}" />
                            <x-input-error :messages="$errors->get('join_date')" class="mt-2" />
                        </div>
                    </div>
                </div>

                <!-- Data Perpajakan & BPJS -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Perpajakan & BPJS</h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="npwp" value="NPWP" />
                                <x-text-input id="npwp" name="npwp" type="text" maxlength="25" class="mt-1 block w-full" value="{{ old('npwp', $employee->npwp) }}" placeholder="00.000.000.0-000.000" />
                                <x-input-error :messages="$errors->get('npwp')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="ptkp_status" value="Status PTKP" />
                                <select id="ptkp_status" name="ptkp_status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    @foreach (['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'] as $status)
                                        <option value="{{ $status }}" {{ old('ptkp_status', $employee->ptkp_status ?? 'TK/0') === $status ? 'selected' : '' }}>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="bpjs_kes" value="No. BPJS Kesehatan" />
                                <x-text-input id="bpjs_kes" name="bpjs_kes" type="text" maxlength="20" class="mt-1 block w-full" value="{{ old('bpjs_kes', $employee->bpjs_kes) }}" />
                                <x-input-error :messages="$errors->get('bpjs_kes')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="bpjs_tk" value="No. BPJS Ketenagakerjaan" />
                                <x-text-input id="bpjs_tk" name="bpjs_tk" type="text" maxlength="20" class="mt-1 block w-full" value="{{ old('bpjs_tk', $employee->bpjs_tk) }}" />
                                <x-input-error :messages="$errors->get('bpjs_tk')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Bank -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Rekening Bank</h3>
                    <div class="space-y-4">
                        <div>
                            <x-input-label for="bank_name" value="Nama Bank" />
                            <x-text-input id="bank_name" name="bank_name" type="text" class="mt-1 block w-full" value="{{ old('bank_name', $employee->bank_name) }}" placeholder="BCA, BRI, Mandiri, dll." />
                            <x-input-error :messages="$errors->get('bank_name')" class="mt-2" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="bank_account" value="Nomor Rekening" />
                                <x-text-input id="bank_account" name="bank_account" type="text" maxlength="30" class="mt-1 block w-full" value="{{ old('bank_account', $employee->bank_account) }}" />
                                <x-input-error :messages="$errors->get('bank_account')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="bank_holder" value="Atas Nama" />
                                <x-text-input id="bank_holder" name="bank_holder" type="text" class="mt-1 block w-full" value="{{ old('bank_holder', $employee->bank_holder) }}" />
                                <x-input-error :messages="$errors->get('bank_holder')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end items-center gap-4">
                    <a href="{{ route('employees.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Batal</a>
                    <x-primary-button>Simpan</x-primary-button>
                </div>
            </form>

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
                <h3 class="font-semibold text-gray-800 mb-1">Akses Portal Absensi</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Buat akun login agar karyawan bisa melihat absensi bulan berjalan di dashboard khusus.
                </p>

                @php $portalUser = $employee->portalUser; @endphp

                <form method="POST" action="{{ route('employees.portal.update', $employee) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="flex items-center gap-2">
                        <input type="hidden" name="portal_enabled" value="0">
                        <input id="portal_enabled" type="checkbox" name="portal_enabled" value="1"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                               {{ old('portal_enabled', $portalUser ? '1' : '0') == '1' ? 'checked' : '' }}>
                        <x-input-label for="portal_enabled" value="Aktifkan akses portal" />
                    </div>

                    <div>
                        <x-input-label for="portal_email" value="Email login" />
                        <x-text-input id="portal_email" name="portal_email" type="email" class="mt-1 block w-full"
                                      value="{{ old('portal_email', $portalUser?->email) }}" autocomplete="off" />
                        <x-input-error :messages="$errors->get('portal_email')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="portal_password" value="Password {{ $portalUser ? '(kosongkan jika tidak diubah)' : '' }}" />
                            <x-text-input id="portal_password" name="portal_password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                            <x-input-error :messages="$errors->get('portal_password')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="portal_password_confirmation" value="Konfirmasi password" />
                            <x-text-input id="portal_password_confirmation" name="portal_password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                        </div>
                    </div>

                    @if ($portalUser)
                        <p class="text-xs text-green-700">Portal aktif · terakhir update {{ $portalUser->updated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
                    @endif

                    <div class="flex justify-end">
                        <x-primary-button>Simpan Akses Portal</x-primary-button>
                    </div>
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
