@props([
    'prefix' => 'schedule',
    'defaults' => [],
])

@php
    $d = array_merge([
        'name' => '',
        'clock_in_time' => '08:00',
        'clock_out_time' => '17:00',
        'break_duration_minutes' => 60,
        'work_duration_hours' => 8,
        'late_after_time' => '08:15',
    ], $defaults);

    $inputClass = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm';
    $monoClass = $inputClass.' font-mono tabular-nums';
@endphp

<div
    class="space-y-5"
    x-data="{
        clockIn: {{ \Illuminate\Support\Js::from($d['clock_in_time']) }},
        breakMinutes: {{ (int) $d['break_duration_minutes'] }},
        workHours: {{ (float) $d['work_duration_hours'] }},
        lateAfter: {{ \Illuminate\Support\Js::from($d['late_after_time']) }},
        get clockOut() {
            return window.calcWorkClockOut(this.clockIn, this.workHours, this.breakMinutes);
        },
        maskTime(field) {
            this[field] = window.maskTime24Input(this[field]);
        },
        blurTime(field) {
            this[field] = window.formatTime24(this[field]);
        }
    }"
>
    <div>
        <x-input-label :for="$prefix.'_name'" value="Nama Profil" />
        <x-text-input :id="$prefix.'_name'" name="name" type="text" class="mt-1 block w-full" :value="$d['name']" required placeholder="Contoh: Jadwal Normal, Jadwal Puasa" />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div class="space-y-3">
        <div>
            <p class="text-sm font-medium text-gray-900">Jadwal harian</p>
            <p class="text-xs text-gray-500 mt-0.5">Isi jam masuk, lama kerja, dan istirahat — jam pulang dihitung otomatis.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <x-input-label :for="$prefix.'_clock_in_time'" value="Jam Masuk" />
                <input :id="'{{ $prefix }}_clock_in_time'" name="clock_in_time" type="text" inputmode="numeric" autocomplete="off" maxlength="5"
                       placeholder="08:00" pattern="^([01][0-9]|2[0-3]):[0-5][0-9]$" title="Format 24 jam, contoh: 08:00"
                       x-model="clockIn" @input="maskTime('clockIn')" @blur="blurTime('clockIn')" required
                       class="{{ $monoClass }}" />
                <x-input-error :messages="$errors->get('clock_in_time')" class="mt-2" />
            </div>
            <div>
                <x-input-label :for="$prefix.'_work_duration_hours'" value="Lama Kerja (jam)" />
                <input :id="'{{ $prefix }}_work_duration_hours'" name="work_duration_hours" type="number" min="1" max="24" step="0.5"
                       x-model.number="workHours" required
                       class="{{ $inputClass }}" />
                <x-input-error :messages="$errors->get('work_duration_hours')" class="mt-2" />
            </div>
            <div>
                <x-input-label :for="$prefix.'_break_duration_minutes'" value="Istirahat (menit)" />
                <input :id="'{{ $prefix }}_break_duration_minutes'" name="break_duration_minutes" type="number" min="0" max="480"
                       x-model.number="breakMinutes" required
                       class="{{ $inputClass }}" />
                <x-input-error :messages="$errors->get('break_duration_minutes')" class="mt-2" />
            </div>
        </div>

        <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Jam Pulang</p>
                <p class="text-xs text-gray-500 mt-0.5 truncate" x-text="(clockIn || '—') + ' + ' + (workHours || 0) + ' jam + ' + (breakMinutes || 0) + ' m'"></p>
            </div>
            <input :id="'{{ $prefix }}_clock_out_time'" name="clock_out_time" type="text" readonly tabindex="-1"
                   :value="clockOut"
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
            <x-input-label :for="$prefix.'_late_after_time'" value="Terlambat Ketika Jam" />
            <input :id="'{{ $prefix }}_late_after_time'" name="late_after_time" type="text" inputmode="numeric" autocomplete="off" maxlength="5"
                   placeholder="08:15" pattern="^([01][0-9]|2[0-3]):[0-5][0-9]$" title="Format 24 jam, contoh: 08:15"
                   x-model="lateAfter" @input="maskTime('lateAfter')" @blur="blurTime('lateAfter')" required
                   class="{{ $monoClass }}" />
            <x-input-error :messages="$errors->get('late_after_time')" class="mt-2" />
        </div>
    </div>
</div>
