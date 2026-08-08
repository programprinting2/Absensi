<?php

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Services\AttendanceReportService;
use App\Support\AppTimezone;
use App\Support\IndonesianHolidays;
use App\Support\Toast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?string $editingCell = null;

    public string $editTimeValue = '';

    /** Tanggal absensi yang ditampilkan (Y-m-d, timezone display). */
    public string $selectedDate = '';

    public function mount(): void
    {
        $this->selectedDate = AppTimezone::nowDisplay()->toDateString();
    }

    public function setSelectedDate(string $date): void
    {
        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $date, AppTimezone::display());
            $this->selectedDate = $parsed->toDateString();
            $this->cancelEditCell();
            $this->syncDateLabel();
        } catch (\Throwable) {
            // abaikan format tidak valid
        }
    }

    public function shiftSelectedDate(int $days): void
    {
        $base = $this->selectedDate !== ''
            ? Carbon::createFromFormat('Y-m-d', $this->selectedDate, AppTimezone::display())
            : AppTimezone::nowDisplay();

        $this->selectedDate = $base->copy()->addDays($days)->toDateString();
        $this->cancelEditCell();
        $this->syncDateLabel();
    }

    public function previousDay(): void
    {
        $this->shiftSelectedDate(-1);
    }

    public function nextDay(): void
    {
        $this->shiftSelectedDate(1);
    }

    public function goToToday(): void
    {
        $this->selectedDate = AppTimezone::nowDisplay()->toDateString();
        $this->cancelEditCell();
        $this->syncDateLabel();
    }

    private function syncDateLabel(): void
    {
        if ($this->selectedDate === '') {
            return;
        }

        try {
            $label = Carbon::createFromFormat('Y-m-d', $this->selectedDate, AppTimezone::display())
                ->locale('id')
                ->translatedFormat('l, j F Y');
        } catch (\Throwable) {
            return;
        }

        $this->js(
            '(() => {'
            .'const el = document.getElementById("js-att-selected-date");'
            .'if (el) el.textContent = '.json_encode($label, JSON_UNESCAPED_UNICODE).';'
            .'})()'
        );
    }

    public function startEditCell(string $employeeId, string $date, string $field, ?string $current = null): void
    {
        if (! in_array($field, ['clock_in', 'break_start', 'break_end', 'clock_out'], true)) {
            return;
        }

        $this->editingCell = "{$employeeId}|{$date}|{$field}";
        $this->editTimeValue = $current ? substr($current, 0, 5) : '';
    }

    public function cancelEditCell(): void
    {
        $this->editingCell = null;
        $this->editTimeValue = '';
    }

    public function saveCellTime(): void
    {
        if (! $this->editingCell) {
            return;
        }

        [$employeeId, $date, $field] = explode('|', $this->editingCell, 3);

        if (! in_array($field, ['clock_in', 'break_start', 'break_end', 'clock_out'], true)) {
            $this->cancelEditCell();

            return;
        }

        $day = Carbon::createFromFormat('Y-m-d', $date, AppTimezone::display());
        [$dayStart, $dayEnd] = AppTimezone::dayBoundsUtc($date);

        $value = trim($this->editTimeValue);

        $existing = AttendanceLog::query()
            ->where('employee_id', $employeeId)
            ->where('attendance_type', $field)
            ->whereBetween('event_time', [$dayStart, $dayEnd])
            ->first();

        if ($value === '') {
            $existing?->delete();
            $this->cancelEditCell();
            Toast::success('Jam absensi dikosongkan.', $this);

            return;
        }

        if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $value, $m)) {
            $value = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        } elseif (preg_match('/^(\d{3,4})$/', preg_replace('/\D/', '', $value), $m)) {
            $digits = str_pad($m[1], 4, '0', STR_PAD_LEFT);
            $value = substr($digits, 0, 2).':'.substr($digits, 2, 2);
        }

        if (! preg_match('/^\d{2}:\d{2}$/', $value)) {
            $msg = 'Format jam 24 jam, contoh: 08:00 atau 17:00.';
            $this->addError('editTimeValue', $msg);
            Toast::error($msg, $this);

            return;
        }

        [$hour, $minute] = array_map('intval', explode(':', $value));
        if ($hour > 23 || $minute > 59) {
            $msg = 'Jam tidak valid (00:00–23:59).';
            $this->addError('editTimeValue', $msg);
            Toast::error($msg, $this);

            return;
        }

        $this->editTimeValue = $value;
        $eventTime = AppTimezone::wallToUtc($day->year, $day->month, $day->day, $hour, $minute);

        if ($existing) {
            $existing->update(['event_time' => $eventTime]);
        } else {
            $deviceId = AttendanceLog::query()
                ->where('employee_id', $employeeId)
                ->whereBetween('event_time', [$dayStart, $dayEnd])
                ->value('device_id')
                ?? Device::where('is_active', true)->value('id')
                ?? Device::query()->value('id');

            if (! $deviceId) {
                Toast::error('Tidak ada perangkat. Tidak bisa menambah jam.', $this);
                $this->cancelEditCell();

                return;
            }

            AttendanceLog::create([
                'device_id' => $deviceId,
                'employee_id' => $employeeId,
                'attendance_type' => $field,
                'method' => 'fingerprint',
                'event_time' => $eventTime,
                'client_uuid' => (string) Str::uuid(),
                'raw_notes' => null,
            ]);
        }

        $this->cancelEditCell();
        Toast::success('Jam absensi diperbarui.', $this);
    }

    public function resetToday(string $employeeId): void
    {
        $date = $this->selectedDate ?: AppTimezone::nowDisplay()->toDateString();
        [$start, $end] = AppTimezone::dayBoundsUtc($date);

        $deleted = AttendanceLog::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('event_time', [$start, $end])
            ->delete();

        $this->cancelEditCell();

        Toast::success(
            $deleted > 0
                ? "Absensi tanggal ini direset ({$deleted} log)."
                : 'Tidak ada absensi untuk direset.',
            $this
        );
    }

    public function with(AttendanceReportService $reports): array
    {
        $selected = $this->selectedDate
            ? Carbon::createFromFormat('Y-m-d', $this->selectedDate, AppTimezone::display())
            : AppTimezone::nowDisplay();
        $selectedDate = $selected->toDateString();
        [$startUtc, $endUtc] = AppTimezone::dayBoundsUtc($selectedDate);
        $todayDate = AppTimezone::nowDisplay()->toDateString();

        $employees = Employee::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code']);

        $employeeIds = $employees->pluck('id');

        $dayLogs = $employeeIds->isEmpty()
            ? collect()
            : AttendanceLog::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereBetween('event_time', [$startUtc, $endUtc])
                ->get(['id', 'employee_id', 'attendance_type', 'event_time']);

        $schedule = WorkSchedule::active();
        $rows = $reports->todayStatusForEmployees($employees, $dayLogs, $schedule)
            ->map(function (array $row) use ($selectedDate) {
                $row['date'] = $selectedDate;

                return $row;
            });

        $year = (int) $selected->year;

        return [
            'todayLabel' => $selected->locale('id')->translatedFormat('l, j F Y'),
            'todayDate' => $todayDate,
            'selectedDate' => $selectedDate,
            'isToday' => $selectedDate === $todayDate,
            'holidays' => IndonesianHolidays::forYears([$year - 1, $year, $year + 1]),
            'rows' => $rows,
            'summary' => [
                'masuk' => $rows->whereIn('status', ['Bekerja', 'Istirahat', 'Pulang'])->count(),
                'off' => $rows->where('status', 'Off')->count(),
                'istirahat' => $rows->where('status', 'Istirahat')->count(),
                'terlambat' => $rows->where('is_late', true)->count(),
                'over_break' => $rows->where('is_over_break', true)->count(),
            ],
        ];
    }
}; ?>

<div
    @if (! $editingCell) wire:poll.30s.visible @endif
    x-data="attendanceDatePicker({
        today: {{ \Illuminate\Support\Js::from($todayDate) }},
        selectedDate: {{ \Illuminate\Support\Js::from($selectedDate) }},
        holidays: {{ \Illuminate\Support\Js::from($holidays) }},
    })"
    @open-attendance-calendar.window="openDialog()"
    @attendance-previous-day.window="$wire.previousDay()"
    @attendance-next-day.window="$wire.nextDay()"
    @attendance-go-today.window="$wire.goToToday()"
>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Absensi') }}
            </h2>
            <div class="flex items-center gap-2 flex-wrap">
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-attendance-calendar'))"
                    class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-[#f7340d] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#f7340d] rounded-md px-1 -mx-1"
                    title="Pilih tanggal"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 opacity-70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                    </svg>
                    <span id="js-att-selected-date">{{ $todayLabel }}</span>
                </button>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        title="Hari sebelumnya"
                        onclick="window.dispatchEvent(new CustomEvent('attendance-previous-day'))"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button
                        type="button"
                        title="Kembali ke hari ini"
                        onclick="window.dispatchEvent(new CustomEvent('attendance-go-today'))"
                        class="px-2.5 py-1.5 text-xs font-medium rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50"
                    >
                        Hari ini
                    </button>
                    <button
                        type="button"
                        title="Hari berikutnya"
                        onclick="window.dispatchEvent(new CustomEvent('attendance-next-day'))"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-4 overflow-y-auto">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 shrink-0">
                <div class="bg-white shadow-sm rounded-lg px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Masuk</p>
                    <p class="mt-1 text-xl font-semibold text-green-700">{{ $summary['masuk'] }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Tidak Masuk</p>
                    <p class="mt-1 text-xl font-semibold text-gray-500">{{ $summary['off'] }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Istirahat</p>
                    <p class="mt-1 text-xl font-semibold text-yellow-700">{{ $summary['istirahat'] }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Terlambat</p>
                    <p class="mt-1 text-xl font-semibold text-red-700">{{ $summary['terlambat'] }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Over Break</p>
                    <p class="mt-1 text-xl font-semibold text-red-700">{{ $summary['over_break'] }}</p>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden flex-1 flex flex-col min-h-0">
                <div class="px-4 py-3 border-b border-gray-100 shrink-0">
                    <h3 class="text-sm font-semibold text-gray-800">
                        Absensi {{ $isToday ? 'Hari Ini' : $todayLabel }}
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">Klik jam untuk edit · Reset membersihkan punch tanggal ini · Klik tanggal di header untuk pilih hari lain.</p>
                </div>

                <div class="overflow-auto flex-1 min-h-0">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-3">Karyawan</th>
                                <th class="px-4 py-3">Masuk</th>
                                <th class="px-4 py-3">Istirahat</th>
                                <th class="px-4 py-3">Kembali</th>
                                <th class="px-4 py-3">Pulang</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($rows as $row)
                                @php
                                    $date = $row['date'] ?? $todayDate;
                                    $hasLogs = filled($row['clock_in'] ?? null)
                                        || filled($row['break_start'] ?? null)
                                        || filled($row['break_end'] ?? null)
                                        || filled($row['clock_out'] ?? null);
                                @endphp
                                <tr wire:key="attendance-{{ $row['employee']->id }}" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="font-medium text-gray-900">{{ $row['employee']->full_name }}</div>
                                        @if ($row['employee']->employee_code)
                                            <div class="text-xs text-gray-400">{{ $row['employee']->employee_code }}</div>
                                        @endif
                                    </td>

                                    @foreach ([
                                        ['clock_in', $row['clock_in'] ?? null, ! empty($row['is_late'])],
                                        ['break_start', $row['break_start'] ?? null, false],
                                        ['break_end', $row['break_end'] ?? null, false],
                                        ['clock_out', $row['clock_out'] ?? null, ! empty($row['is_early_out']) || ! empty($row['is_short_work'])],
                                    ] as [$field, $value, $flagged])
                                        @php $cellKey = $row['employee']->id.'|'.$date.'|'.$field; @endphp
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if ($editingCell === $cellKey)
                                                <input
                                                    type="text"
                                                    inputmode="numeric"
                                                    autocomplete="off"
                                                    maxlength="5"
                                                    placeholder="08:00"
                                                    title="Format 24 jam, contoh: 08:00 atau 17:00"
                                                    wire:model.live="editTimeValue"
                                                    wire:keydown.enter.prevent="saveCellTime"
                                                    wire:keydown.escape.prevent="cancelEditCell"
                                                    wire:blur="saveCellTime"
                                                    x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                                                    @input="
                                                        let d = $el.value.replace(/\D/g, '').slice(0, 4);
                                                        $el.value = d.length >= 3 ? d.slice(0, 2) + ':' + d.slice(2) : d;
                                                        $wire.set('editTimeValue', $el.value);
                                                    "
                                                    class="w-[7.5rem] rounded-md border-gray-300 text-sm font-mono tabular-nums shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                />
                                            @else
                                                <button
                                                    type="button"
                                                    wire:click="startEditCell('{{ $row['employee']->id }}', '{{ $date }}', '{{ $field }}', '{{ $value }}')"
                                                    class="inline-flex items-center rounded px-1.5 py-0.5 -mx-1.5 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                    title="Klik untuk edit jam"
                                                >
                                                    <span @class(['font-medium' => $flagged, 'text-red-600' => $flagged, 'text-gray-700' => ! $flagged])>
                                                        {{ $value ?? '-' }}
                                                    </span>
                                                </button>
                                            @endif
                                        </td>
                                    @endforeach

                                    <td class="px-4 py-3 align-top">
                                        <x-attendance-status :parts="$row['status_parts'] ?? []" :fallback-status="$row['status']" />
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right">
                                        @if ($hasLogs)
                                            <button
                                                type="button"
                                                wire:click="resetToday('{{ $row['employee']->id }}')"
                                                wire:confirm="Reset absensi {{ $row['employee']->full_name }} untuk tanggal ini?"
                                                class="inline-flex items-center justify-center p-1.5 rounded-md text-amber-600 hover:bg-amber-50 hover:text-amber-700 transition"
                                                title="Reset absensi tanggal ini"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
                                                </svg>
                                            </button>
                                        @else
                                            <span class="inline-flex p-1.5 text-gray-300" title="Tidak ada absensi untuk direset">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
                                                </svg>
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                                        Belum ada karyawan aktif.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Dialog kalender pilih tanggal --}}
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600/50 p-4"
        @keydown.escape.window="open && (open = false)"
        @click.self="open = false"
    >
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden" @click.stop>
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div class="flex items-center justify-between gap-3 w-full">
                    <h4 class="font-semibold text-gray-900">Kalender</h4>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="prevMonth()"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <button type="button" @click="goToday()"
                                class="px-2.5 py-1.5 text-xs font-medium rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50">
                            Hari ini
                        </button>
                        <button type="button" @click="nextMonth()"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <button type="button" @click="open = false" class="ml-1 text-gray-400 hover:text-gray-600" title="Tutup">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-5">
                <p class="text-sm font-medium text-gray-800 mb-3" x-text="monthLabel"></p>

                <div class="grid grid-cols-7 gap-1 text-center text-xs font-medium text-gray-500 mb-1">
                    <div>Sen</div><div>Sel</div><div>Rab</div><div>Kam</div><div>Jum</div><div>Sab</div>
                    <div class="text-red-600">Min</div>
                </div>

                <div class="grid grid-cols-7 gap-1">
                    <template x-for="cell in cells" :key="cell.key">
                        <button
                            type="button"
                            class="relative min-h-[2.5rem] rounded-md border p-1 text-sm text-left"
                            :class="cellClasses(cell)"
                            :disabled="!cell.day"
                            :title="cell.holidayName || ''"
                            @click="pickDay(cell)"
                        >
                            <span class="font-medium tabular-nums" x-text="cell.day || ''"></span>
                            <template x-if="cell.holidayName">
                                <span class="absolute bottom-0.5 left-0.5 right-0.5 block h-1 rounded-full"
                                      :class="cell.isJointLeave ? 'bg-amber-400' : 'bg-red-500'"></span>
                            </template>
                        </button>
                    </template>
                </div>

                <div class="mt-3 flex flex-wrap gap-3 text-xs text-gray-600">
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> Libur nasional / Minggu</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span> Cuti bersama</span>
                </div>
            </div>
        </div>
    </div>
</div>
