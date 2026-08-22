<?php

use App\Models\AttendanceDayReason;
use App\Services\AttendanceReportService;
use App\Support\AppTimezone;
use App\Support\Toast;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    /** @var 'this_month'|'custom' */
    public string $periodPreset = 'this_month';

    /** @var 'month'|'year' */
    public string $period = 'month';

    public int $year = 0;

    public int $month = 0;

    public bool $showReasonModal = false;

    public string $reasonDate = '';

    public string $reasonDateLabel = '';

    public string $timeClockIn = '—';

    public string $timeBreakStart = '—';

    public string $timeBreakEnd = '—';

    public string $timeClockOut = '—';

    public string $reasonClockIn = '';

    public string $reasonBreakStart = '';

    public string $reasonBreakEnd = '';

    public string $reasonClockOut = '';

    public string $reasonDay = '';

    public function mount(): void
    {
        $now = AppTimezone::nowDisplay();
        $this->year = (int) $now->year;
        $this->month = (int) $now->month;
    }

    public function setPeriodPreset(string $preset): void
    {
        if (! in_array($preset, ['this_month', 'custom'], true)) {
            return;
        }

        $this->periodPreset = $preset;

        if ($preset === 'this_month') {
            $now = AppTimezone::nowDisplay();
            $this->year = (int) $now->year;
            $this->month = (int) $now->month;
            $this->period = 'month';
        }
    }

    public function setCustomPeriodType(string $type): void
    {
        if (! in_array($type, ['month', 'year'], true)) {
            return;
        }

        $this->periodPreset = 'custom';
        $this->period = $type;
    }

    public function updatedYear(): void
    {
        $this->periodPreset = 'custom';
    }

    public function updatedMonth(): void
    {
        $this->periodPreset = 'custom';
    }

    public function updatedPeriod(): void
    {
        $this->periodPreset = 'custom';
    }

    public function openReason(string $date, string $dateLabel = ''): void
    {
        $user = auth()->user();
        $employee = $user?->employee;

        if (! $employee) {
            return;
        }

        try {
            Carbon::createFromFormat('Y-m-d', $date, AppTimezone::display());
        } catch (\Throwable) {
            return;
        }

        $reports = app(AttendanceReportService::class);
        $day = Carbon::createFromFormat('Y-m-d', $date, AppTimezone::display());
        $logs = $reports->forRange('day', (int) $day->year, (int) $day->month, (int) $day->day, $employee->id);
        [$rangeStart, $rangeEnd] = $reports->resolveRange('day', (int) $day->year, (int) $day->month, (int) $day->day);

        $row = $reports->pivotByEmployeeAndDate(
            $logs,
            null,
            collect([$employee]),
            $rangeStart,
            $rangeEnd,
        )->firstWhere('date', $date);

        $saved = AttendanceDayReason::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $date)
            ->first();

        $this->reasonDate = $date;
        $this->reasonDateLabel = $dateLabel !== ''
            ? $dateLabel
            : $day->locale('id')->translatedFormat('l, j F Y');
        $this->timeClockIn = $row['clock_in'] ?? '—';
        $this->timeBreakStart = $row['break_start'] ?? '—';
        $this->timeBreakEnd = $row['break_end'] ?? '—';
        $this->timeClockOut = $row['clock_out'] ?? '—';
        $this->reasonClockIn = $saved?->clock_in_reason ?? '';
        $this->reasonBreakStart = $saved?->break_start_reason ?? '';
        $this->reasonBreakEnd = $saved?->break_end_reason ?? '';
        $this->reasonClockOut = $saved?->clock_out_reason ?? '';
        $this->reasonDay = $saved?->day_reason ?? '';
        $this->showReasonModal = true;
    }

    public function closeReasonModal(): void
    {
        $this->showReasonModal = false;
        $this->resetReasonForm();
    }

    public function saveReasons(): void
    {
        $user = auth()->user();
        $employee = $user?->employee;

        if (! $employee || $this->reasonDate === '') {
            return;
        }

        $data = $this->validate([
            'reasonClockIn' => ['nullable', 'string', 'max:500'],
            'reasonBreakStart' => ['nullable', 'string', 'max:500'],
            'reasonBreakEnd' => ['nullable', 'string', 'max:500'],
            'reasonClockOut' => ['nullable', 'string', 'max:500'],
            'reasonDay' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'reasonClockIn' => 'alasan masuk',
            'reasonBreakStart' => 'alasan istirahat',
            'reasonBreakEnd' => 'alasan kembali',
            'reasonClockOut' => 'alasan pulang',
            'reasonDay' => 'alasan',
        ]);

        $payload = [
            'clock_in_reason' => filled($data['reasonClockIn'] ?? null) ? trim($data['reasonClockIn']) : null,
            'break_start_reason' => filled($data['reasonBreakStart'] ?? null) ? trim($data['reasonBreakStart']) : null,
            'break_end_reason' => filled($data['reasonBreakEnd'] ?? null) ? trim($data['reasonBreakEnd']) : null,
            'clock_out_reason' => filled($data['reasonClockOut'] ?? null) ? trim($data['reasonClockOut']) : null,
            'day_reason' => filled($data['reasonDay'] ?? null) ? trim($data['reasonDay']) : null,
        ];

        $hasAny = collect($payload)->contains(fn ($v) => filled($v));

        $existing = AttendanceDayReason::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $this->reasonDate)
            ->first();

        if (! $hasAny) {
            $existing?->delete();
        } else {
            AttendanceDayReason::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'work_date' => $this->reasonDate,
                ],
                $payload,
            );
        }

        $this->showReasonModal = false;
        $this->resetReasonForm();
        Toast::success('Alasan absensi disimpan.', $this);
    }

    private function resetReasonForm(): void
    {
        $this->reasonDate = '';
        $this->reasonDateLabel = '';
        $this->timeClockIn = '—';
        $this->timeBreakStart = '—';
        $this->timeBreakEnd = '—';
        $this->timeClockOut = '—';
        $this->reasonClockIn = '';
        $this->reasonBreakStart = '';
        $this->reasonBreakEnd = '';
        $this->reasonClockOut = '';
        $this->reasonDay = '';
    }

    /**
     * @return array{period: string, year: int, month: int}
     */
    private function resolveActivePeriod(): array
    {
        $now = AppTimezone::nowDisplay();

        if ($this->periodPreset === 'this_month') {
            return [
                'period' => 'month',
                'year' => (int) $now->year,
                'month' => (int) $now->month,
            ];
        }

        return [
            'period' => $this->period,
            'year' => $this->year,
            'month' => $this->month,
        ];
    }

    public function with(AttendanceReportService $reports): array
    {
        $user = auth()->user();
        $employee = $user->employee;
        $now = AppTimezone::nowDisplay();
        $yearOptions = range((int) $now->year, (int) $now->year - 5);

        if (! $employee) {
            return [
                'employee' => null,
                'periodLabel' => null,
                'periodPreset' => $this->periodPreset,
                'rows' => collect(),
                'reasonDates' => [],
                'yearOptions' => $yearOptions,
                'summary' => $this->emptySummary(),
            ];
        }

        ['period' => $period, 'year' => $year, 'month' => $month] = $this->resolveActivePeriod();

        $logs = $reports->forRange($period, $year, $month, null, $employee->id);
        [$rangeStart, $rangeEnd] = $reports->resolveRange($period, $year, $month, null);

        $rows = $reports->pivotByEmployeeAndDate(
            $logs,
            null,
            collect([$employee]),
            $rangeStart,
            $rangeEnd,
        )->sortByDesc('date')->values();

        $rangeStartDisplay = AppTimezone::toDisplay($rangeStart)->toDateString();
        $rangeEndDisplay = AppTimezone::toDisplay($rangeEnd)->toDateString();

        $reasonDates = AttendanceDayReason::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$rangeStartDisplay, $rangeEndDisplay])
            ->get()
            ->filter(fn (AttendanceDayReason $r) => $r->hasAnyReason())
            ->mapWithKeys(fn (AttendanceDayReason $r) => [$r->work_date->toDateString() => true])
            ->all();

        $skipStatuses = ['Libur', 'Libur Rutin', 'Tidak dijadwalkan', 'Jadwal belum diatur'];
        $workRows = $rows->filter(fn ($r) => ! in_array($r['status'] ?? '', $skipStatuses, true));
        $workCount = $workRows->count();
        $okCount = $workRows->where('compliance_ok', true)->count();

        return [
            'employee' => $employee,
            'periodLabel' => $reports->describePeriod($period, $year, $month, null),
            'periodPreset' => $this->periodPreset,
            'rows' => $rows,
            'reasonDates' => $reasonDates,
            'yearOptions' => $yearOptions,
            'summary' => [
                'total' => $rows->count(),
                'ok' => $rows->where('compliance_ok', true)->count(),
                'not_ok' => $rows->filter(fn ($r) => empty($r['compliance_ok']))->count(),
                'performa' => $workCount > 0 ? (int) round($okCount / $workCount * 100) : 100,
                'hari_kerja' => $workCount,
                'hadir_ok' => $okCount,
                'tidak_masuk' => $rows->whereIn('status', ['Tidak Masuk', 'Off'])->count(),
                'off' => $rows->where('status', 'Off')->count(),
                'cuti' => $rows->where('status', 'Cuti')->count(),
                'terlambat' => $rows->where('is_late', true)->count(),
                'istirahat_lebih' => $rows->where('is_over_break', true)->count(),
                'pulang_awal' => $rows->where('is_early_out', true)->count(),
                'jam_kerja_kurang' => $rows->where('is_short_work', true)->count(),
                'lembur' => $rows->filter(fn ($r) => (int) ($r['overtime_minutes'] ?? 0) > 0)->count(),
                'menit_terlambat' => (int) $rows->sum(fn ($r) => (int) ($r['late_minutes'] ?? 0)),
                'menit_istirahat_lebih' => (int) $rows->sum(fn ($r) => (int) ($r['over_break_minutes'] ?? 0)),
                'menit_pulang_awal' => (int) $rows->sum(fn ($r) => (int) ($r['early_out_minutes'] ?? 0)),
                'menit_jam_kerja_kurang' => (int) $rows->sum(fn ($r) => (int) ($r['short_work_minutes'] ?? 0)),
                'menit_lembur' => (int) $rows->sum(fn ($r) => (int) ($r['overtime_minutes'] ?? 0)),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'ok' => 0,
            'not_ok' => 0,
            'performa' => 0,
            'hari_kerja' => 0,
            'hadir_ok' => 0,
            'tidak_masuk' => 0,
            'off' => 0,
            'cuti' => 0,
            'terlambat' => 0,
            'istirahat_lebih' => 0,
            'pulang_awal' => 0,
            'jam_kerja_kurang' => 0,
            'lembur' => 0,
            'menit_terlambat' => 0,
            'menit_istirahat_lebih' => 0,
            'menit_pulang_awal' => 0,
            'menit_jam_kerja_kurang' => 0,
            'menit_lembur' => 0,
        ];
    }
}; ?>

@php
    $fmtDur = function (int $minutes): string {
        $total = abs($minutes);
        if ($total <= 0) {
            return '';
        }
        $hours = intdiv($total, 60);
        $remain = $total % 60;
        if ($hours >= 24) {
            $days = intdiv($hours, 24);
            $hours = $hours % 24;

            return "{$days}h {$hours}j {$remain}m";
        }

        return "{$hours}j {$remain}m";
    };

    $performa = (int) ($summary['performa'] ?? 0);
    $performaColor = match (true) {
        $performa >= 90 => 'text-emerald-600',
        $performa >= 75 => 'text-amber-600',
        default => 'text-red-600',
    };
    $performaRing = match (true) {
        $performa >= 90 => 'stroke-emerald-500',
        $performa >= 75 => 'stroke-amber-500',
        default => 'stroke-red-500',
    };
    $performaTrack = match (true) {
        $performa >= 90 => 'stroke-emerald-100',
        $performa >= 75 => 'stroke-amber-100',
        default => 'stroke-red-100',
    };
    $monthLabels = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
@endphp

<div class="flex flex-col min-h-0 flex-1">
    <div class="bg-white border-b border-gray-200 shrink-0">
        <div class="py-3 px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard Kehadiran</h2>
                @if ($employee)
                    <p class="mt-0.5 text-sm text-gray-500">
                        {{ $employee->full_name }}
                        @if ($employee->employee_code)
                            <span class="text-gray-400">· ID {{ $employee->employee_code }}</span>
                        @endif
                        @if ($periodLabel)
                            <span class="text-gray-400">· {{ $periodLabel }}</span>
                        @endif
                    </p>
                @endif
            </div>

            @if ($employee)
                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                    <div class="flex rounded-md border border-gray-300 p-0.5 bg-gray-50 shrink-0 h-8 items-center">
                        <button
                            type="button"
                            wire:click="setPeriodPreset('this_month')"
                            @class([
                                'inline-flex items-center justify-center h-7 shrink-0 rounded px-2.5 text-xs font-semibold whitespace-nowrap transition',
                                $periodPreset === 'this_month'
                                    ? 'bg-[#f7340d] text-white'
                                    : 'text-gray-500 hover:text-gray-700',
                            ])
                        >
                            Bulan ini
                        </button>
                        <button
                            type="button"
                            wire:click="setPeriodPreset('custom')"
                            @class([
                                'inline-flex items-center justify-center h-7 shrink-0 rounded px-2.5 text-xs font-semibold whitespace-nowrap transition',
                                $periodPreset === 'custom'
                                    ? 'bg-[#f7340d] text-white'
                                    : 'text-gray-500 hover:text-gray-700',
                            ])
                        >
                            Pilih Periode
                        </button>
                    </div>

                    @if ($periodPreset === 'custom')
                        <div class="flex rounded-md border border-gray-300 p-0.5 bg-gray-50 shrink-0 h-8 items-center">
                            <button
                                type="button"
                                wire:click="setCustomPeriodType('month')"
                                @class([
                                    'inline-flex items-center justify-center h-7 shrink-0 rounded px-2.5 text-xs font-semibold whitespace-nowrap transition',
                                    $period === 'month'
                                        ? 'bg-[#f7340d] text-white'
                                        : 'text-gray-500 hover:text-gray-700',
                                ])
                            >
                                Bulanan
                            </button>
                            <button
                                type="button"
                                wire:click="setCustomPeriodType('year')"
                                @class([
                                    'inline-flex items-center justify-center h-7 shrink-0 rounded px-2.5 text-xs font-semibold whitespace-nowrap transition',
                                    $period === 'year'
                                        ? 'bg-[#f7340d] text-white'
                                        : 'text-gray-500 hover:text-gray-700',
                                ])
                            >
                                Tahunan
                            </button>
                        </div>

                        @if ($period === 'month')
                            <select wire:model.live="month" class="h-8 rounded-md border-gray-300 bg-white text-xs focus:border-[#f7340d] focus:ring-[#f7340d]">
                                @foreach ($monthLabels as $index => $label)
                                    <option value="{{ $index + 1 }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif

                        <select wire:model.live="year" class="h-8 rounded-md border-gray-300 bg-white text-xs focus:border-[#f7340d] focus:ring-[#f7340d]">
                            @foreach ($yearOptions as $y)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div @if (! $showReasonModal) wire:poll.60s.visible @endif>
        <div class="px-4 sm:px-6 lg:px-8 py-5 pb-12 space-y-6">
            @unless ($employee)
                <div class="bg-white shadow-sm rounded-2xl px-6 py-12 text-center text-sm text-gray-500 border border-gray-200">
                    Akun ini belum terhubung ke data karyawan. Hubungi admin.
                </div>
            @else
                {{-- Statistik --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    {{-- Performa --}}
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center gap-4">
                            <div class="relative h-16 w-16 shrink-0">
                                <svg class="h-16 w-16 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                                    <circle cx="18" cy="18" r="15.5" fill="none" class="{{ $performaTrack }}" stroke-width="3"></circle>
                                    <circle
                                        cx="18" cy="18" r="15.5" fill="none"
                                        class="{{ $performaRing }}"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                        pathLength="100"
                                        stroke-dasharray="{{ max(0, min(100, $performa)) }}, 100"
                                    ></circle>
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-sm font-bold tabular-nums {{ $performaColor }}">{{ $performa }}%</span>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Performa</p>
                                <p class="mt-1 text-sm font-semibold text-gray-900">{{ $summary['hadir_ok'] }} / {{ $summary['hari_kerja'] }} hari OK</p>
                                <p class="text-xs text-gray-500 mt-0.5">Hari kerja sesuai jadwal</p>
                            </div>
                        </div>
                    </div>

                    {{-- Off --}}
                    <div class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-red-700">Off / Alpha</p>
                                <p class="mt-1 text-3xl font-bold tabular-nums text-red-800">{{ $summary['off'] }}</p>
                                <p class="text-xs text-red-700 mt-1">Tidak masuk tanpa keterangan</p>
                            </div>
                            <div class="rounded-xl bg-red-200 p-2.5 text-red-800">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Cuti --}}
                    <div class="rounded-2xl border border-sky-200 bg-sky-50 p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-sky-700">Cuti</p>
                                <p class="mt-1 text-3xl font-bold tabular-nums text-sky-800">{{ $summary['cuti'] }}</p>
                                <p class="text-xs text-sky-700 mt-1">Hari cuti disetujui</p>
                            </div>
                            <div class="rounded-xl bg-sky-200 p-2.5 text-sky-800">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M4.5 8.25h15m-13.5 0V18a2.25 2.25 0 002.25 2.25h10.5A2.25 2.25 0 0021 18V8.25m-18 0V6.108c0-1.135.845-2.098 1.976-2.192.883-.078 1.79-.108 2.976-.108.83 0 1.54.02 2.126.06.586.04 1.086.1 1.5.18V6.75m-13.5 1.5h15" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Terlambat --}}
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-amber-800">Terlambat</p>
                                <p class="mt-1 text-3xl font-bold tabular-nums text-amber-900">
                                    {{ $summary['terlambat'] }}
                                    @if ($summary['menit_terlambat'] > 0)
                                        <span class="text-sm font-medium text-amber-800">· {{ $fmtDur($summary['menit_terlambat']) }}</span>
                                    @endif
                                </p>
                                <p class="text-xs text-amber-800 mt-1">Keterlambatan masuk kerja</p>
                            </div>
                            <div class="rounded-xl bg-amber-200 p-2.5 text-amber-900">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Statistik tambahan --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    @foreach ([
                        ['label' => 'OK', 'value' => $summary['ok'], 'sub' => 'Hari patuh aturan', 'class' => 'text-emerald-700', 'bg' => 'bg-emerald-50 border-emerald-200'],
                        ['label' => 'Not OK', 'value' => $summary['not_ok'], 'sub' => 'Ada pelanggaran', 'class' => 'text-red-700', 'bg' => 'bg-red-50 border-red-200'],
                        ['label' => 'Istirahat+', 'value' => $summary['istirahat_lebih'], 'sub' => $summary['menit_istirahat_lebih'] > 0 ? $fmtDur($summary['menit_istirahat_lebih']) : '—', 'class' => 'text-amber-800', 'bg' => 'bg-amber-50 border-amber-200'],
                        ['label' => 'Pulang awal', 'value' => $summary['pulang_awal'], 'sub' => $summary['menit_pulang_awal'] > 0 ? $fmtDur($summary['menit_pulang_awal']) : '—', 'class' => 'text-red-700', 'bg' => 'bg-red-50 border-red-200'],
                        ['label' => 'Lembur', 'value' => $summary['lembur'], 'sub' => $summary['menit_lembur'] > 0 ? $fmtDur($summary['menit_lembur']) : '—', 'class' => 'text-emerald-700', 'bg' => 'bg-emerald-50 border-emerald-200'],
                    ] as $chip)
                        <div class="rounded-xl border {{ $chip['bg'] }} px-4 py-3.5 shadow-sm">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-600">{{ $chip['label'] }}</p>
                            <p class="mt-1 text-xl font-bold tabular-nums {{ $chip['class'] }}">{{ $chip['value'] }}</p>
                            <p class="text-[11px] text-gray-600 mt-0.5 truncate">{{ $chip['sub'] }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Tabel detail --}}
                <div class="bg-white shadow-sm rounded-2xl overflow-hidden border border-gray-200">
                    <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-sm font-semibold text-gray-900">Detail Kehadiran</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $periodLabel }} · {{ $rows->count() }} hari tercatat</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-100">
                                <tr class="text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">
                                    <th class="px-5 py-3">Tanggal</th>
                                    <th class="px-5 py-3">Masuk</th>
                                    <th class="px-5 py-3 hidden sm:table-cell">Istirahat</th>
                                    <th class="px-5 py-3 hidden sm:table-cell">Kembali</th>
                                    <th class="px-5 py-3">Pulang</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3 text-right">Alasan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($rows as $row)
                                    @php
                                        $hasReason = ! empty($reasonDates[$row['date'] ?? ''] ?? null);
                                        $isAbsent = in_array($row['status'] ?? '', ['Tidak Masuk', 'Off'], true);
                                        $isLeave = ($row['status'] ?? '') === 'Cuti';
                                    @endphp
                                    <tr
                                        wire:key="my-{{ $row['date'] }}"
                                        @class([
                                            'hover:bg-gray-50 transition-colors',
                                            'bg-red-50' => $isAbsent,
                                            'bg-sky-50' => $isLeave,
                                        ])
                                    >
                                        <td class="px-5 py-3.5 whitespace-nowrap">
                                            <p class="font-medium text-gray-800">{{ $row['date_label'] }}</p>
                                        </td>
                                        <td class="px-5 py-3.5 whitespace-nowrap tabular-nums {{ !empty($row['is_late']) ? 'text-red-600 font-semibold' : 'text-gray-700' }}">
                                            {{ $row['clock_in'] ?? '—' }}
                                        </td>
                                        <td class="px-5 py-3.5 whitespace-nowrap text-gray-700 tabular-nums hidden sm:table-cell">{{ $row['break_start'] ?? '—' }}</td>
                                        <td class="px-5 py-3.5 whitespace-nowrap text-gray-700 tabular-nums hidden sm:table-cell">{{ $row['break_end'] ?? '—' }}</td>
                                        <td class="px-5 py-3.5 whitespace-nowrap tabular-nums {{ !empty($row['is_early_out']) || !empty($row['is_short_work']) ? 'text-red-600 font-semibold' : 'text-gray-700' }}">
                                            {{ $row['clock_out'] ?? '—' }}
                                        </td>
                                        <td class="px-5 py-3.5 align-top">
                                            <x-attendance-status :parts="$row['status_parts'] ?? []" :fallback-status="$row['status']" />
                                        </td>
                                        <td class="px-5 py-3.5 whitespace-nowrap text-right">
                                            <button
                                                type="button"
                                                wire:click="openReason('{{ $row['date'] }}', {{ \Illuminate\Support\Js::from($row['date_label'] ?? '') }})"
                                                class="inline-flex items-center justify-center p-2 rounded-lg transition {{ $hasReason ? 'text-[#f7340d] bg-orange-100 hover:bg-orange-200' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-100' }}"
                                                title="{{ $hasReason ? 'Lihat / edit alasan' : 'Isi alasan' }}"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-[1.125rem] w-[1.125rem]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-5 py-14 text-center">
                                            <p class="text-sm font-medium text-gray-700">Belum ada data absensi</p>
                                            <p class="text-xs text-gray-500 mt-1">Data akan muncul setelah periode dipilih memiliki catatan kehadiran.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endunless
        </div>
    </div>

    @if ($showReasonModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50 p-4"
            wire:keydown.escape.window="closeReasonModal"
        >
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden" @click.stop>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Alasan Absensi</h3>
                        <p class="text-sm text-gray-500 mt-0.5">{{ $reasonDateLabel }}</p>
                    </div>
                    <button type="button" wire:click="closeReasonModal" class="text-gray-400 hover:text-gray-600" title="Tutup">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    @foreach ([
                        ['label' => 'Masuk', 'time' => $timeClockIn, 'model' => 'reasonClockIn', 'error' => 'reasonClockIn'],
                        ['label' => 'Istirahat', 'time' => $timeBreakStart, 'model' => 'reasonBreakStart', 'error' => 'reasonBreakStart'],
                        ['label' => 'Kembali', 'time' => $timeBreakEnd, 'model' => 'reasonBreakEnd', 'error' => 'reasonBreakEnd'],
                        ['label' => 'Pulang', 'time' => $timeClockOut, 'model' => 'reasonClockOut', 'error' => 'reasonClockOut'],
                    ] as $field)
                        <div class="grid grid-cols-1 sm:grid-cols-[7rem_1fr] gap-2 sm:gap-3 sm:items-start">
                            <div class="pt-2">
                                <p class="text-sm font-medium text-gray-800">{{ $field['label'] }}</p>
                                <p class="text-xs text-gray-500 tabular-nums">{{ $field['time'] }}</p>
                            </div>
                            <div>
                                <input
                                    type="text"
                                    maxlength="500"
                                    wire:model="{{ $field['model'] }}"
                                    placeholder="Alasan (opsional)"
                                    class="block w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-[#f7340d] focus:border-[#f7340d]"
                                />
                                @error($field['error'])
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endforeach

                    <div class="pt-3 mt-1 border-t border-gray-100">
                        <label for="reasonDay" class="block text-sm font-medium text-gray-800 mb-1.5">Alasan umum</label>
                        <textarea
                            id="reasonDay"
                            rows="3"
                            maxlength="1000"
                            wire:model="reasonDay"
                            placeholder="Alasan umum untuk hari ini (opsional)"
                            class="block w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-[#f7340d] focus:border-[#f7340d]"
                        ></textarea>
                        @error('reasonDay')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-200 bg-gray-50">
                    <x-secondary-button type="button" wire:click="closeReasonModal">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="saveReasons" wire:loading.attr="disabled">
                        Simpan
                    </x-primary-button>
                </div>
            </div>
        </div>
    @endif
</div>
