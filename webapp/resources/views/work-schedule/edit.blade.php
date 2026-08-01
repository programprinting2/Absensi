<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengaturan Jam Kerja</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="bg-green-50 text-green-800 text-sm px-4 py-3 rounded-md border border-green-200 mb-4">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
                <form method="POST" action="{{ route('work-schedule.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" value="Nama Jadwal" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name', $schedule->name) }}" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="clock_in_time" value="Jam Masuk" />
                            <x-text-input id="clock_in_time" name="clock_in_time" type="time" class="mt-1 block w-full" value="{{ old('clock_in_time', substr($schedule->clock_in_time ?? '', 0, 5)) }}" required />
                            <x-input-error :messages="$errors->get('clock_in_time')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="clock_out_time" value="Jam Pulang" />
                            <x-text-input id="clock_out_time" name="clock_out_time" type="time" class="mt-1 block w-full" value="{{ old('clock_out_time', substr($schedule->clock_out_time ?? '', 0, 5)) }}" required />
                            <x-input-error :messages="$errors->get('clock_out_time')" class="mt-2" />
                        </div>
                        <div class="col-span-2">
                            <x-input-label for="break_duration_minutes" value="Durasi Istirahat (menit)" />
                            <x-text-input id="break_duration_minutes" name="break_duration_minutes" type="number" min="0" max="480" class="mt-1 block w-full" value="{{ old('break_duration_minutes', $schedule->break_duration_minutes) }}" required />
                            <x-input-error :messages="$errors->get('break_duration_minutes')" class="mt-2" />
                        </div>
                    </div>

                    <p class="text-sm text-gray-500">
                        Jenis absensi (Masuk / Istirahat / Kembali / Pulang) dipilih langsung oleh karyawan
                        di keypad device (tombol A/B/C/D), bukan otomatis berdasarkan jam. Jadwal ini dipakai
                        sebagai acuan telat/pulang cepat di laporan.
                    </p>

                    <div class="flex justify-end">
                        <x-primary-button>Simpan Jadwal</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
