<?php

use App\Models\AttendanceDayReason;
use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Services\AttendanceDummyService;
use App\Services\AttendanceReportService;
use App\Support\AppTimezone;
use App\Support\Toast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url(history: false)]
    public string $period = 'month';

    #[Url(history: false)]
    public int $year;

    #[Url(history: false)]
    public int $month;

    #[Url(history: false)]
    public int $day;

    #[Url(history: false)]
    public ?string $employee_id = null;

    #[Url(history: false, as: 'tab')]
    public string $mainTab = 'detail';

    public ?string $editingCell = null;

    public string $editTimeValue = '';

    public function mount(): void
    {
        $this->year ??= now()->year;
        $this->month ??= now()->month;
        $this->day ??= now()->day;

        if (! in_array($this->mainTab, ['detail', 'rekap'], true)) {
            $this->mainTab = 'detail';
        }
    }

    public function setMainTab(string $tab): void
    {
        if (! in_array($tab, ['detail', 'rekap'], true)) {
            return;
        }

        $this->mainTab = $tab;
        $this->cancelEditCell();
    }

    public function resetFilters(): void
    {
        $this->reset(['period', 'year', 'month', 'day', 'employee_id']);
        $this->mount();
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

        // Normalisasi format 24 jam (0800 / 8:0 / 08:00 → 08:00)
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

    public function deleteRow(string $employeeId, string $date): void
    {
        [$start, $end] = AppTimezone::dayBoundsUtc($date);

        $deleted = AttendanceLog::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('event_time', [$start, $end])
            ->delete();

        $dayLabel = Carbon::createFromFormat('Y-m-d', $date, AppTimezone::display())
            ->locale('id')
            ->translatedFormat('j M Y');

        Toast::success(
            $deleted > 0
                ? "Absensi dihapus ({$deleted} log) untuk tanggal {$dayLabel}."
                : 'Tidak ada log absensi yang dihapus pada baris ini.',
            $this
        );
    }

    public function createDummy(AttendanceReportService $reports, AttendanceDummyService $dummy): void
    {
        if (app()->environment('production')) {
            return;
        }

        [$start, $end] = $reports->resolveRange($this->period, $this->year, $this->month, $this->day);
        $dummy->clearForRange($start, $end, $this->employee_id ?: null);
        $result = $dummy->createForRange($start, $end, $this->employee_id ?: null);

        Toast::success(
            "Dummy dibuat ulang: {$result['created_logs']} log · {$result['employees']} karyawan · {$result['days']} hari kerja"
            .($result['skipped_days'] ? " · {$result['skipped_days']} hari dilewati (sudah ada data asli)" : '')
            .'.',
            $this
        );
    }

    public function clearDummy(AttendanceReportService $reports, AttendanceDummyService $dummy): void
    {
        if (app()->environment('production')) {
            return;
        }

        [$start, $end] = $reports->resolveRange($this->period, $this->year, $this->month, $this->day);
        $deleted = $dummy->clearForRange($start, $end, $this->employee_id ?: null);

        Toast::success("Dummy dihapus: {$deleted} log pada filter periode ini.", $this);
    }

    public function with(AttendanceReportService $reports): array
    {
        $logs = $reports->forRange($this->period, $this->year, $this->month, $this->day, $this->employee_id);

        $employees = Employee::where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'created_at']);

        // Selalu tampilkan hari kerja tanpa absen sebagai "Tidak Masuk"
        // (Senin–Sabtu, dari tanggal karyawan terdaftar s/d hari ini / akhir periode).
        $employeesForAbsence = $this->employee_id
            ? $employees->where('id', $this->employee_id)->values()
            : $employees;

        [$rangeStart, $rangeEnd] = $reports->resolveRange($this->period, $this->year, $this->month, $this->day);

        $rows = $reports->pivotByEmployeeAndDate(
            $logs,
            WorkSchedule::active(),
            $employeesForAbsence,
            $rangeStart,
            $rangeEnd,
        );

        $reasonDateStart = $rangeStart->copy()->timezone(AppTimezone::display())->toDateString();
        $reasonDateEnd = $rangeEnd->copy()->timezone(AppTimezone::display())->toDateString();

        $reasonsByKey = $employeesForAbsence->isEmpty()
            ? collect()
            : AttendanceDayReason::query()
                ->whereIn('employee_id', $employeesForAbsence->pluck('id'))
                ->whereBetween('work_date', [$reasonDateStart, $reasonDateEnd])
                ->get()
                ->keyBy(fn (AttendanceDayReason $r) => $r->employee_id.'|'.$r->work_date->toDateString());

        $formatReason = function (?AttendanceDayReason $reason): ?array {
            if (! $reason || ! $reason->hasAnyReason()) {
                return null;
            }

            $lines = [];
            if (filled($reason->day_reason)) {
                $lines[] = $reason->day_reason;
            }
            foreach ([
                'Masuk' => $reason->clock_in_reason,
                'Istirahat' => $reason->break_start_reason,
                'Kembali' => $reason->break_end_reason,
                'Pulang' => $reason->clock_out_reason,
            ] as $label => $text) {
                if (filled($text)) {
                    $lines[] = "{$label}: {$text}";
                }
            }

            return $lines !== [] ? $lines : null;
        };

        $attachReasons = function ($groupRows) use ($reasonsByKey, $formatReason) {
            return $groupRows->map(function (array $row) use ($reasonsByKey, $formatReason) {
                $key = ($row['employee']->id ?? '').'|'.($row['date'] ?? '');
                $row['reason_lines'] = $formatReason($reasonsByKey->get($key));

                return $row;
            });
        };

        $buildStats = function ($group) {
            return [
                'total' => $group->count(),
                'ok' => $group->where('compliance_ok', true)->count(),
                'not_ok' => $group->filter(fn ($r) => empty($r['compliance_ok']))->count(),
                'tidak_masuk' => $group->where('status', 'Tidak Masuk')->count(),
                'terlambat' => $group->where('is_late', true)->count(),
                'istirahat_lebih' => $group->where('is_over_break', true)->count(),
                'pulang_awal' => $group->where('is_early_out', true)->count(),
                'jam_kerja_kurang' => $group->where('is_short_work', true)->count(),
                'lembur' => $group->filter(fn ($r) => (int) ($r['overtime_minutes'] ?? 0) > 0)->count(),
                'menit_terlambat' => (int) $group->sum(fn ($r) => (int) ($r['late_minutes'] ?? 0)),
                'menit_istirahat_lebih' => (int) $group->sum(fn ($r) => (int) ($r['over_break_minutes'] ?? 0)),
                'menit_pulang_awal' => (int) $group->sum(fn ($r) => (int) ($r['early_out_minutes'] ?? 0)),
                'menit_jam_kerja_kurang' => (int) $group->sum(fn ($r) => (int) ($r['short_work_minutes'] ?? 0)),
                'menit_lembur' => (int) $group->sum(fn ($r) => (int) ($r['overtime_minutes'] ?? 0)),
            ];
        };

        $summary = $buildStats($rows);

        $rekapByEmployee = $rows
            ->groupBy(fn ($r) => $r['employee']->id)
            ->map(function ($group) use ($buildStats) {
                return array_merge(
                    ['employee' => $group->first()['employee']],
                    $buildStats($group),
                );
            })
            ->sortBy(fn ($r) => mb_strtolower($r['employee']->full_name))
            ->values();

        $groupDetailByEmployee = blank($this->employee_id);

        $detailGroups = $groupDetailByEmployee
            ? $rows
                ->groupBy(fn ($r) => $r['employee']->id)
                ->map(function ($group) use ($attachReasons, $buildStats) {
                    $employee = $group->first()['employee'];

                    return [
                        'employee' => $employee,
                        'rows' => $attachReasons($group->sortByDesc('date')->values()),
                        'stats' => $buildStats($group),
                    ];
                })
                ->sortBy(fn ($g) => mb_strtolower($g['employee']->full_name))
                ->values()
            : collect([[
                'employee' => null,
                'rows' => $attachReasons($rows->sortByDesc('date')->values()),
                'stats' => null,
            ]]);

        return [
            'rows' => $rows,
            'summary' => $summary,
            'rekapByEmployee' => $rekapByEmployee,
            'detailGroups' => $detailGroups,
            'groupDetailByEmployee' => $groupDetailByEmployee,
            'employees' => $employees,
            'yearOptions' => range(now()->year, now()->year - 5),
            'periodLabel' => $reports->describePeriod($this->period, $this->year, $this->month, $this->day),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Laporan Absensi</h2>
    </x-slot>

    <div class="h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-4">
            <div class="bg-white shadow-sm rounded-lg p-6 shrink-0">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="w-36">
                        <x-input-label for="period" value="Periode" />
                        <select id="period" wire:model.change="period" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="month">Bulanan</option>
                            <option value="day">Harian</option>
                        </select>
                    </div>

                    <div class="w-56">
                        <x-input-label for="employee_id" value="Karyawan" />
                        <select id="employee_id" wire:model.change="employee_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">Semua Karyawan</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($period === 'day')
                        <div class="w-24">
                            <x-input-label for="day" value="Tanggal" />
                            <select id="day" wire:model.change="day" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                @for ($d = 1; $d <= 31; $d++)
                                    <option value="{{ $d }}">{{ $d }}</option>
                                @endfor
                            </select>
                        </div>
                    @endif

                    <div class="w-40">
                        <x-input-label for="month" value="Bulan" />
                        <select id="month" wire:model.change="month" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            @foreach (['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $index => $label)
                                <option value="{{ $index + 1 }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="w-28">
                        <x-input-label for="year" value="Tahun" />
                        <select id="year" wire:model.change="year" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            @foreach ($yearOptions as $y)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="button" wire:click="resetFilters"
                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Reset
                    </button>

                    @if (! app()->environment('production'))
                        <div class="ml-auto flex flex-wrap items-center gap-2">
                            <span class="text-[10px] uppercase tracking-widest text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">Dev</span>
                            <button type="button" wire:click="createDummy" wire:loading.attr="disabled"
                                    wire:confirm="Buat data absensi dummy acak untuk filter periode ini (karyawan aktif)?"
                                    class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50">
                                <span wire:loading.remove wire:target="createDummy">Create Dummy</span>
                                <span wire:loading wire:target="createDummy">Membuat…</span>
                            </button>
                            <button type="button" wire:click="clearDummy" wire:loading.attr="disabled"
                                    wire:confirm="Hapus semua absensi dummy pada filter periode ini? Data asli (bukan raw_notes=DEV_DUMMY) tidak ikut terhapus."
                                    class="inline-flex items-center px-4 py-2 bg-white border border-amber-300 rounded-md font-semibold text-xs text-amber-800 uppercase tracking-widest shadow-sm hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50">
                                <span wire:loading.remove wire:target="clearDummy">Clear Dummy</span>
                                <span wire:loading wire:target="clearDummy">Menghapus…</span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <h3 class="text-lg font-semibold text-gray-800 shrink-0">{{ $periodLabel }}</h3>

            <div class="flex gap-1 border-b border-gray-200 shrink-0">
                <button type="button" wire:click="setMainTab('detail')"
                        @class([
                            'px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition',
                            'border-gray-800 text-gray-900' => $mainTab === 'detail',
                            'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' => $mainTab !== 'detail',
                        ])>
                    Detail
                </button>
                <button type="button" wire:click="setMainTab('rekap')"
                        @class([
                            'px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition',
                            'border-gray-800 text-gray-900' => $mainTab === 'rekap',
                            'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' => $mainTab !== 'rekap',
                        ])>
                    Rekap
                </button>
            </div>

            @if ($mainTab === 'rekap')
                <x-attendance-stats-strip :stats="$summary" class="shrink-0" />

                <div class="bg-white shadow-sm rounded-lg overflow-hidden flex-1 flex flex-col min-h-0">
                    <div class="overflow-auto flex-1">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <th class="px-6 py-3">Karyawan</th>
                                    <th class="px-4 py-3 text-right">Hari</th>
                                    <th class="px-4 py-3 text-right">OK</th>
                                    <th class="px-4 py-3 text-right">Not OK</th>
                                    <th class="px-4 py-3 text-right">Tidak masuk</th>
                                    <th class="px-4 py-3 text-right">Terlambat</th>
                                    <th class="px-4 py-3 text-right">Istirahat+</th>
                                    <th class="px-4 py-3 text-right">Pulang awal</th>
                                    <th class="px-4 py-3 text-right">Jam kurang</th>
                                    <th class="px-4 py-3 text-right">Jam lembur</th>
                                    <th class="px-4 py-3 text-right">Total menit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($rekapByEmployee as $rekap)
                                    @php
                                        $totalMenit = $rekap['menit_terlambat']
                                            + $rekap['menit_istirahat_lebih']
                                            + $rekap['menit_pulang_awal']
                                            + $rekap['menit_jam_kerja_kurang'];
                                    @endphp
                                    <tr wire:key="rekap-{{ $rekap['employee']->id }}" class="hover:bg-gray-50">
                                        <td class="px-6 py-3 whitespace-nowrap font-medium text-gray-900">{{ $rekap['employee']->full_name }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-gray-700">{{ $rekap['total'] }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-green-700 font-medium">{{ $rekap['ok'] }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-red-700 font-medium">{{ $rekap['not_ok'] }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-gray-600">{{ $rekap['tidak_masuk'] }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-gray-700">
                                            {{ $rekap['terlambat'] }}
                                            <x-minutes-hm :minutes="$rekap['menit_terlambat']" class="text-xs text-gray-400" />
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-gray-700">
                                            {{ $rekap['istirahat_lebih'] }}
                                            <x-minutes-hm :minutes="$rekap['menit_istirahat_lebih']" class="text-xs text-gray-400" />
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-gray-700">
                                            {{ $rekap['pulang_awal'] }}
                                            <x-minutes-hm :minutes="$rekap['menit_pulang_awal']" class="text-xs text-gray-400" />
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-gray-700">
                                            {{ $rekap['jam_kerja_kurang'] }}
                                            <x-minutes-hm :minutes="$rekap['menit_jam_kerja_kurang']" class="text-xs text-gray-400" />
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-emerald-700">
                                            {{ $rekap['lembur'] }}
                                            <x-minutes-hm :minutes="$rekap['menit_lembur']" class="text-xs text-gray-400" />
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right font-medium text-gray-900">
                                            <x-minutes-hm :minutes="$totalMenit" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="px-6 py-6 text-center text-gray-500">
                                            Tidak ada data untuk direkap pada filter ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                @php
                    $hasAnyDetailRow = $detailGroups->contains(fn ($g) => $g['rows']->isNotEmpty());
                @endphp

                @if ($groupDetailByEmployee)
                    <div class="flex-1 min-h-0 overflow-auto space-y-2" x-data="{ open: {} }">
                        @unless ($hasAnyDetailRow)
                            <div class="bg-white shadow-sm rounded-lg px-6 py-8 text-center text-sm text-gray-500">
                                Tidak ada data absensi pada rentang ini.
                            </div>
                        @else
                            @foreach ($detailGroups as $group)
                                @continue(! $group['employee'] || $group['rows']->isEmpty())
                                @php $gid = $group['employee']->id; @endphp
                                <div wire:key="accordion-{{ $gid }}" class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-100">
                                    <button
                                        type="button"
                                        class="w-full text-left hover:bg-gray-50 transition"
                                        @click="open['{{ $gid }}'] = !open['{{ $gid }}']"
                                        :aria-expanded="!!open['{{ $gid }}']"
                                    >
                                        <div class="flex items-center gap-2 px-4 pt-3 pb-2 min-w-0">
                                            <svg class="h-4 w-4 text-gray-500 shrink-0 transition-transform" :class="open['{{ $gid }}'] ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                            </svg>
                                            <span class="text-sm font-semibold text-gray-900 truncate">{{ $group['employee']->full_name }}</span>
                                        </div>
                                        <div class="mx-3 mb-3">
                                            <x-attendance-stats-strip :stats="$group['stats']" inset />
                                        </div>
                                    </button>

                                    <div x-show="open['{{ $gid }}']" x-cloak class="border-t border-gray-100">
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                                <thead class="bg-gray-50">
                                                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        <th class="px-6 py-2.5">Tanggal</th>
                                                        <th class="px-6 py-2.5">Masuk</th>
                                                        <th class="px-6 py-2.5">Istirahat</th>
                                                        <th class="px-6 py-2.5">Kembali</th>
                                                        <th class="px-6 py-2.5">Pulang</th>
                                                        <th class="px-6 py-2.5">Status</th>
                                                        <th class="px-6 py-2.5">Alasan</th>
                                                        <th class="px-6 py-2.5 text-right">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 bg-white">
                                                    @php $previousDate = null; @endphp
                                                    @foreach ($group['rows'] as $row)
                                                        @php
                                                            $rowMonth = substr($row['date'], 0, 7);
                                                            $previousMonth = $previousDate ? substr($previousDate, 0, 7) : null;
                                                            $separatorClass = match (true) {
                                                                $previousDate === null => '',
                                                                $rowMonth !== $previousMonth => '!border-t-4 !border-t-gray-400',
                                                                $row['date'] !== $previousDate => '!border-t-2 !border-t-gray-300',
                                                                default => '',
                                                            };
                                                            $previousDate = $row['date'];
                                                        @endphp
                                                        @include('livewire.pages.reports.partials.attendance-detail-row', [
                                                            'row' => $row,
                                                            'showEmployeeColumn' => false,
                                                            'separatorClass' => $separatorClass,
                                                            'editingCell' => $editingCell,
                                                        ])
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endunless
                    </div>
                @else
                    <div class="bg-white shadow-sm rounded-lg overflow-hidden flex-1 flex flex-col min-h-0">
                        <div class="overflow-auto flex-1">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 sticky top-0 z-10">
                                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <th class="px-6 py-3">Tanggal</th>
                                        <th class="px-6 py-3">Karyawan</th>
                                        <th class="px-6 py-3">Masuk</th>
                                        <th class="px-6 py-3">Istirahat</th>
                                        <th class="px-6 py-3">Kembali</th>
                                        <th class="px-6 py-3">Pulang</th>
                                        <th class="px-6 py-3">Status</th>
                                        <th class="px-6 py-3">Alasan</th>
                                        <th class="px-6 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    @unless ($hasAnyDetailRow)
                                        <tr>
                                            <td colspan="9" class="px-6 py-6 text-center text-gray-500">
                                                Tidak ada data absensi pada rentang ini.
                                            </td>
                                        </tr>
                                    @else
                                        @php $previousDate = null; @endphp
                                        @foreach ($detailGroups->first()['rows'] ?? [] as $row)
                                            @php
                                                $rowMonth = substr($row['date'], 0, 7);
                                                $previousMonth = $previousDate ? substr($previousDate, 0, 7) : null;
                                                $separatorClass = match (true) {
                                                    $previousDate === null => '',
                                                    $rowMonth !== $previousMonth => '!border-t-4 !border-t-gray-400',
                                                    $row['date'] !== $previousDate => '!border-t-2 !border-t-gray-300',
                                                    default => '',
                                                };
                                                $previousDate = $row['date'];
                                            @endphp
                                            @include('livewire.pages.reports.partials.attendance-detail-row', [
                                                'row' => $row,
                                                'showEmployeeColumn' => true,
                                                'separatorClass' => $separatorClass,
                                                'editingCell' => $editingCell,
                                            ])
                                        @endforeach
                                    @endunless
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
