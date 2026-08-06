@php
    $fmtTime = fn (?string $t) => $t ? substr($t, 0, 5) : '—';
    $editingScheduleId = session('editing_schedule_id') ?: old('_editing_schedule_id');
    $editingSchedule = $editingScheduleId
        ? $schedules->firstWhere('id', $editingScheduleId)
        : null;
    $scheduleFieldErrors = $errors->hasAny([
        'name', 'clock_in_time', 'clock_out_time', 'break_duration_minutes',
        'work_duration_hours', 'late_after_time',
    ]);
    $showCreateScheduleModal = $scheduleFieldErrors && ! $editingScheduleId;
    $showEditScheduleModal = filled($editingScheduleId) && ($scheduleFieldErrors || session()->has('editing_schedule_id'));
@endphp

<div class="mb-5">
    <h3 class="text-base font-semibold text-gray-900">Profil Jam Kerja</h3>
    <p class="text-sm text-gray-500 mt-0.5">
        Buat beberapa profil (mis. Normal, Puasa). Hanya <strong>satu profil aktif</strong> yang dipakai sebagai acuan telat &amp; jam kerja di absensi/laporan.
    </p>
</div>

<div
    class="border border-gray-200 rounded-lg overflow-hidden"
    x-data="{
        editing: {
            id: {{ \Illuminate\Support\Js::from($editingSchedule?->id) }},
            name: {{ \Illuminate\Support\Js::from(old('name', $editingSchedule?->name ?? '')) }},
            clock_in_time: {{ \Illuminate\Support\Js::from(old('clock_in_time', $fmtTime($editingSchedule?->clock_in_time))) }},
            break_duration_minutes: {{ \Illuminate\Support\Js::from(old('break_duration_minutes', $editingSchedule?->break_duration_minutes ?? 60)) }},
            work_duration_hours: {{ \Illuminate\Support\Js::from(old('work_duration_hours', ($editingSchedule?->work_duration_minutes ?? 480) / 60)) }},
            late_after_time: {{ \Illuminate\Support\Js::from(old('late_after_time', $fmtTime($editingSchedule?->late_after_time ?: $editingSchedule?->clock_in_time))) }}
        },
        get editingClockOut() {
            return window.calcWorkClockOut(
                this.editing.clock_in_time,
                this.editing.work_duration_hours,
                this.editing.break_duration_minutes
            );
        },
        openEdit(row) {
            this.editing = {
                id: row.id,
                name: row.name,
                clock_in_time: row.clock_in_time,
                break_duration_minutes: row.break_duration_minutes,
                work_duration_hours: row.work_duration_hours,
                late_after_time: row.late_after_time,
            };
            $dispatch('open-modal', 'edit-work-schedule');
        }
    }"
>
    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h4 class="text-sm font-semibold text-gray-800">Daftar profil</h4>
            <p class="text-xs text-gray-500 mt-0.5">Aktifkan profil yang sesuai periode kerja saat ini.</p>
        </div>
        <x-primary-button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'create-work-schedule')">
            Buat Profil
        </x-primary-button>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm divide-y divide-gray-100">
            <thead class="bg-white text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-2.5">Nama</th>
                    <th class="px-4 py-2.5">Masuk</th>
                    <th class="px-4 py-2.5">Pulang</th>
                    <th class="px-4 py-2.5">Istirahat</th>
                    <th class="px-4 py-2.5">Jam kerja</th>
                    <th class="px-4 py-2.5">Telat setelah</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th class="px-4 py-2.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($schedules as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">{{ $row->name }}</td>
                        <td class="px-4 py-3 tabular-nums text-gray-700">{{ $fmtTime($row->clock_in_time) }}</td>
                        <td class="px-4 py-3 tabular-nums text-gray-700">{{ $fmtTime($row->clock_out_time) }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $row->break_duration_minutes }} m</td>
                        <td class="px-4 py-3 text-gray-700">{{ ($row->work_duration_minutes ?? 480) / 60 }} jam</td>
                        <td class="px-4 py-3 tabular-nums text-gray-700">{{ $fmtTime($row->late_after_time ?: $row->clock_in_time) }}</td>
                        <td class="px-4 py-3">
                            @if ($row->is_active)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktif</span>
                            @else
                                <form method="POST" action="{{ route('work-schedule.activate', $row) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 transition" title="Aktifkan profil ini">
                                        Aktifkan
                                    </button>
                                </form>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center p-1.5 rounded-md text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700 transition"
                                    title="Update"
                                    @click="openEdit({
                                        id: {{ \Illuminate\Support\Js::from($row->id) }},
                                        name: {{ \Illuminate\Support\Js::from($row->name) }},
                                        clock_in_time: {{ \Illuminate\Support\Js::from($fmtTime($row->clock_in_time)) }},
                                        break_duration_minutes: {{ (int) $row->break_duration_minutes }},
                                        work_duration_hours: {{ ($row->work_duration_minutes ?? 480) / 60 }},
                                        late_after_time: {{ \Illuminate\Support\Js::from($fmtTime($row->late_after_time ?: $row->clock_in_time)) }}
                                    })"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                </button>

                                @unless ($row->is_active)
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center p-1.5 rounded-md text-red-600 hover:bg-red-50 hover:text-red-700 transition"
                                        title="Hapus"
                                        x-on:click.prevent="$dispatch('open-modal', 'delete-work-schedule-{{ $row->id }}')"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">Belum ada profil jam kerja.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-4 py-3 border-t border-gray-100 bg-gray-50">
        <p class="text-xs text-gray-500">
            Jenis absensi (Masuk / Istirahat / Kembali / Pulang) dipilih di keypad device. Profil aktif dipakai sebagai acuan telat, istirahat lebih, dan jam kerja di laporan.
        </p>
    </div>

    {{-- Dialog: Buat Profil --}}
    <x-modal name="create-work-schedule" :show="$showCreateScheduleModal" focusable maxWidth="lg">
        <form method="POST" action="{{ route('work-schedule.store') }}" class="p-6 space-y-4">
            @csrf
            <h2 class="text-lg font-medium text-gray-900">Buat Profil Jam Kerja</h2>
            <p class="text-sm text-gray-500">Contoh: Jadwal Normal, Jadwal Puasa (Ramadan).</p>

            @include('settings._work-schedule-fields', [
                'prefix' => 'create',
                'defaults' => [
                    'name' => old('name', ''),
                    'clock_in_time' => old('clock_in_time', '08:00'),
                    'clock_out_time' => old('clock_out_time', '17:00'),
                    'break_duration_minutes' => old('break_duration_minutes', 60),
                    'work_duration_hours' => old('work_duration_hours', 8),
                    'late_after_time' => old('late_after_time', '08:15'),
                ],
            ])

            <div class="flex justify-end gap-2 pt-2">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">Batal</x-secondary-button>
                <x-primary-button type="submit">Buat Profil</x-primary-button>
            </div>
        </form>
    </x-modal>

    {{-- Dialog: Update Profil --}}
    <x-modal name="edit-work-schedule" :show="$showEditScheduleModal" focusable maxWidth="lg">
        <form method="POST" class="p-6 space-y-4" x-bind:action="'{{ url('/work-schedule') }}/' + editing.id">
            @csrf
            @method('PUT')
            <input type="hidden" name="_editing_schedule_id" x-bind:value="editing.id">

            <h2 class="text-lg font-medium text-gray-900">Update Profil Jam Kerja</h2>

            <div class="space-y-5">
                <div>
                    <x-input-label for="edit_schedule_name" value="Nama Profil" />
                    <input id="edit_schedule_name" name="name" type="text" x-model="editing.name" required
                           class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div class="space-y-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900">Jadwal harian</p>
                        <p class="text-xs text-gray-500 mt-0.5">Isi jam masuk, lama kerja, dan istirahat — jam pulang dihitung otomatis.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <x-input-label for="edit_clock_in_time" value="Jam Masuk" />
                            <input id="edit_clock_in_time" name="clock_in_time" type="text" inputmode="numeric" autocomplete="off" maxlength="5"
                                   placeholder="08:00" pattern="^([01][0-9]|2[0-3]):[0-5][0-9]$" title="Format 24 jam, contoh: 08:00"
                                   x-model="editing.clock_in_time" required
                                   @input="editing.clock_in_time = window.maskTime24Input(editing.clock_in_time)"
                                   @blur="editing.clock_in_time = window.formatTime24(editing.clock_in_time)"
                                   class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm font-mono tabular-nums" />
                            <x-input-error :messages="$errors->get('clock_in_time')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="edit_work_duration_hours" value="Lama Kerja (jam)" />
                            <input id="edit_work_duration_hours" name="work_duration_hours" type="number" min="1" max="24" step="0.5"
                                   x-model.number="editing.work_duration_hours" required
                                   class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                            <x-input-error :messages="$errors->get('work_duration_hours')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="edit_break_duration_minutes" value="Istirahat (menit)" />
                            <input id="edit_break_duration_minutes" name="break_duration_minutes" type="number" min="0" max="480"
                                   x-model.number="editing.break_duration_minutes" required
                                   class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                            <x-input-error :messages="$errors->get('break_duration_minutes')" class="mt-2" />
                        </div>
                    </div>

                    <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Jam Pulang</p>
                            <p class="text-xs text-gray-500 mt-0.5 truncate"
                               x-text="(editing.clock_in_time || '—') + ' + ' + (editing.work_duration_hours || 0) + ' jam + ' + (editing.break_duration_minutes || 0) + ' m'"></p>
                        </div>
                        <input id="edit_clock_out_time" name="clock_out_time" type="text" readonly tabindex="-1"
                               x-bind:value="editingClockOut"
                               class="w-24 shrink-0 border-0 bg-transparent p-0 text-right text-xl font-semibold font-mono tabular-nums text-gray-900 focus:ring-0 cursor-default" />
                        <x-input-error :messages="$errors->get('clock_out_time')" class="w-full mt-1" />
                    </div>
                </div>

                <div class="space-y-3 pt-1 border-t border-gray-100">
                    <div>
                        <p class="text-sm font-medium text-gray-900">Aturan keterlambatan</p>
                        <p class="text-xs text-gray-500 mt-0.5">Masuk setelah jam ini dihitung terlambat (format 24 jam).</p>
                    </div>
                    <div class="max-w-xs">
                        <x-input-label for="edit_late_after_time" value="Terlambat Ketika Jam" />
                        <input id="edit_late_after_time" name="late_after_time" type="text" inputmode="numeric" autocomplete="off" maxlength="5"
                               placeholder="08:15" pattern="^([01][0-9]|2[0-3]):[0-5][0-9]$" title="Format 24 jam, contoh: 08:15"
                               x-model="editing.late_after_time" required
                               @input="editing.late_after_time = window.maskTime24Input(editing.late_after_time)"
                               @blur="editing.late_after_time = window.formatTime24(editing.late_after_time)"
                               class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm font-mono tabular-nums" />
                        <x-input-error :messages="$errors->get('late_after_time')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">Batal</x-secondary-button>
                <x-primary-button type="submit">Simpan Perubahan</x-primary-button>
            </div>
        </form>
    </x-modal>

    @foreach ($schedules as $row)
        @continue($row->is_active)
        <x-modal name="delete-work-schedule-{{ $row->id }}" focusable>
            <form method="POST" action="{{ route('work-schedule.destroy', $row) }}" class="p-6">
                @csrf
                @method('DELETE')
                <h2 class="text-lg font-medium text-gray-900">Hapus profil {{ $row->name }}?</h2>
                <p class="mt-1 text-sm text-gray-600">Profil ini akan dihapus permanen.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">Batal</x-secondary-button>
                    <x-danger-button>Hapus</x-danger-button>
                </div>
            </form>
        </x-modal>
    @endforeach
</div>
