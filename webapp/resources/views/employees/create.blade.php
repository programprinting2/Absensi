<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tambah Karyawan</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="POST" action="{{ route('employees.store') }}" class="space-y-6">
                @csrf

                <!-- Data Dasar -->
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Data Dasar</h3>
                    <div class="space-y-4">
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

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="nik" value="NIK (KTP)" />
                                <x-text-input id="nik" name="nik" type="text" maxlength="16" class="mt-1 block w-full" value="{{ old('nik') }}" />
                                <x-input-error :messages="$errors->get('nik')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="phone" value="No. Telepon" />
                                <x-text-input id="phone" name="phone" type="text" maxlength="20" class="mt-1 block w-full" value="{{ old('phone') }}" />
                                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="address" value="Alamat" />
                            <textarea id="address" name="address" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('address') }}</textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
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
                                <x-text-input id="position" name="position" type="text" class="mt-1 block w-full" value="{{ old('position') }}" />
                                <x-input-error :messages="$errors->get('position')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="department" value="Departemen" />
                                <x-text-input id="department" name="department" type="text" class="mt-1 block w-full" value="{{ old('department') }}" />
                                <x-input-error :messages="$errors->get('department')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="join_date" value="Tanggal Bergabung" />
                            <x-text-input id="join_date" name="join_date" type="date" class="mt-1 block w-full" value="{{ old('join_date', now()->format('Y-m-d')) }}" />
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
                                <x-text-input id="npwp" name="npwp" type="text" maxlength="25" class="mt-1 block w-full" value="{{ old('npwp') }}" placeholder="00.000.000.0-000.000" />
                                <x-input-error :messages="$errors->get('npwp')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="ptkp_status" value="Status PTKP" />
                                <select id="ptkp_status" name="ptkp_status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    @foreach (['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'] as $status)
                                        <option value="{{ $status }}" {{ old('ptkp_status', 'TK/0') === $status ? 'selected' : '' }}>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="bpjs_kes" value="No. BPJS Kesehatan" />
                                <x-text-input id="bpjs_kes" name="bpjs_kes" type="text" maxlength="20" class="mt-1 block w-full" value="{{ old('bpjs_kes') }}" />
                                <x-input-error :messages="$errors->get('bpjs_kes')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="bpjs_tk" value="No. BPJS Ketenagakerjaan" />
                                <x-text-input id="bpjs_tk" name="bpjs_tk" type="text" maxlength="20" class="mt-1 block w-full" value="{{ old('bpjs_tk') }}" />
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
                            <x-text-input id="bank_name" name="bank_name" type="text" class="mt-1 block w-full" value="{{ old('bank_name') }}" placeholder="BCA, BRI, Mandiri, dll." />
                            <x-input-error :messages="$errors->get('bank_name')" class="mt-2" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="bank_account" value="Nomor Rekening" />
                                <x-text-input id="bank_account" name="bank_account" type="text" maxlength="30" class="mt-1 block w-full" value="{{ old('bank_account') }}" />
                                <x-input-error :messages="$errors->get('bank_account')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="bank_holder" value="Atas Nama" />
                                <x-text-input id="bank_holder" name="bank_holder" type="text" class="mt-1 block w-full" value="{{ old('bank_holder') }}" />
                                <x-input-error :messages="$errors->get('bank_holder')" class="mt-2" />
                            </div>
                        </div>
                    </div>
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
</x-app-layout>
