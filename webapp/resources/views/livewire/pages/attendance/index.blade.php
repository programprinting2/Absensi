<?php

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Services\AttendanceReportService;
use App\Support\AppTimezone;
use App\Support\Toast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?string $editingCell = null;

    public string $editTimeValue = '';

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
        $date = AppTimezone::nowDisplay()->toDateString();
        [$start, $end] = AppTimezone::dayBoundsUtc($date);

        $deleted = AttendanceLog::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('event_time', [$start, $end])
            ->delete();

        $this->cancelEditCell();

        Toast::success(
            $deleted > 0
                ? "Absensi hari ini direset ({$deleted} log)."
                : 'Tidak ada absensi untuk direset.',
            $this
        );
    }

    public function with(AttendanceReportService $reports): array
    {
        $today = AppTimezone::nowDisplay();
        $todayDate = $today->toDateString();
        [$startUtc, $endUtc] = AppTimezone::dayBoundsUtc($today);

        $employees = Employee::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code']);

        $employeeIds = $employees->pluck('id');

        $todayLogs = $employeeIds->isEmpty()
            ? collect()
            : AttendanceLog::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereBetween('event_time', [$startUtc, $endUtc])
                ->get(['id', 'employee_id', 'attendance_type', 'event_time']);

        $schedule = WorkSchedule::active();
        $rows = $reports->todayStatusForEmployees($employees, $todayLogs, $schedule)
            ->map(function (array $row) use ($todayDate) {
                $row['date'] = $todayDate;

                return $row;
            });

        return [
            'todayLabel' => $today->locale('id')->translatedFormat('l, j F Y'),
            'todayDate' => $todayDate,
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

<div @if (! $editingCell) wire:poll.30s.visible @endif>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-1">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Absensi') }}
            </h2>
            <p class="text-sm text-gray-500">{{ $todayLabel }}</p>
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
                    <h3 class="text-sm font-semibold text-gray-800">Absensi Hari Ini</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Klik jam untuk edit · Reset membersihkan punch hari ini.</p>
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
                                                wire:confirm="Reset absensi {{ $row['employee']->full_name }} untuk hari ini?"
                                                class="inline-flex items-center justify-center p-1.5 rounded-md text-amber-600 hover:bg-amber-50 hover:text-amber-700 transition"
                                                title="Reset absensi hari ini"
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
</div>
