<?php

use App\Models\AttendanceDayReason;
use App\Models\WorkSchedule;
use App\Services\AttendanceReportService;
use App\Support\AppTimezone;
use App\Support\Toast;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
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
            WorkSchedule::active(),
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

    public function with(AttendanceReportService $reports): array
    {
        $user = auth()->user();
        $employee = $user->employee;

        if (! $employee) {
            return [
                'employee' => null,
                'periodLabel' => null,
                'rows' => collect(),
                'reasonDates' => [],
                'summary' => [
                    'total' => 0,
                    'ok' => 0,
                    'not_ok' => 0,
                    'tidak_masuk' => 0,
                    'terlambat' => 0,
                    'istirahat_lebih' => 0,
                    'pulang_awal' => 0,
                    'jam_kerja_kurang' => 0,
                    'menit_terlambat' => 0,
                    'menit_istirahat_lebih' => 0,
                    'menit_pulang_awal' => 0,
                    'menit_jam_kerja_kurang' => 0,
                ],
                'todayRow' => null,
            ];
        }

        $now = AppTimezone::nowDisplay();
        $year = (int) $now->year;
        $month = (int) $now->month;

        $logs = $reports->forRange('month', $year, $month, null, $employee->id);
        [$rangeStart, $rangeEnd] = $reports->resolveRange('month', $year, $month, null);

        $rows = $reports->pivotByEmployeeAndDate(
            $logs,
            WorkSchedule::active(),
            collect([$employee]),
            $rangeStart,
            $rangeEnd,
        )->sortByDesc('date')->values();

        $today = $now->toDateString();
        $todayRow = $rows->firstWhere('date', $today);

        $reasonDates = AttendanceDayReason::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [
                Carbon::create($year, $month, 1, 0, 0, 0, AppTimezone::display())->toDateString(),
                Carbon::create($year, $month, 1, 0, 0, 0, AppTimezone::display())->endOfMonth()->toDateString(),
            ])
            ->get()
            ->filter(fn (AttendanceDayReason $r) => $r->hasAnyReason())
            ->mapWithKeys(fn (AttendanceDayReason $r) => [$r->work_date->toDateString() => true])
            ->all();

        $fmtHm = function (int $minutes): string {
            $total = abs($minutes);
            $hours = intdiv($total, 60);
            $remain = $total % 60;

            return "{$hours} : {$remain}";
        };

        return [
            'employee' => $employee,
            'periodLabel' => $now->locale('id')->translatedFormat('F Y'),
            'rows' => $rows,
            'todayRow' => $todayRow,
            'reasonDates' => $reasonDates,
            'fmtHm' => $fmtHm,
            'summary' => [
                'total' => $rows->count(),
                'ok' => $rows->where('compliance_ok', true)->count(),
                'not_ok' => $rows->filter(fn ($r) => empty($r['compliance_ok']))->count(),
                'tidak_masuk' => $rows->where('status', 'Tidak Masuk')->count(),
                'terlambat' => $rows->where('is_late', true)->count(),
                'istirahat_lebih' => $rows->where('is_over_break', true)->count(),
                'pulang_awal' => $rows->where('is_early_out', true)->count(),
                'jam_kerja_kurang' => $rows->where('is_short_work', true)->count(),
                'menit_terlambat' => (int) $rows->sum(fn ($r) => (int) ($r['late_minutes'] ?? 0)),
                'menit_istirahat_lebih' => (int) $rows->sum(fn ($r) => (int) ($r['over_break_minutes'] ?? 0)),
                'menit_pulang_awal' => (int) $rows->sum(fn ($r) => (int) ($r['early_out_minutes'] ?? 0)),
                'menit_jam_kerja_kurang' => (int) $rows->sum(fn ($r) => (int) ($r['short_work_minutes'] ?? 0)),
            ],
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Dashboard</h2>
            @if ($employee)
                <p class="mt-0.5 text-sm text-gray-500">
                    {{ $employee->full_name }}
                    @if ($employee->employee_code)
                        <span class="text-gray-400">· ID {{ $employee->employee_code }}</span>
                    @endif
                    <span class="text-gray-400">· {{ $periodLabel }}</span>
                </p>
            @endif
        </div>
    </x-slot>

    <div class="h-[calc(100vh-8rem)] flex flex-col" @if (! $showReasonModal) wire:poll.60s.visible @endif>
        <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-4 overflow-y-auto">
            @unless ($employee)
                <div class="bg-white shadow-sm rounded-lg px-6 py-10 text-center text-sm text-gray-500">
                    Akun ini belum terhubung ke data karyawan. Hubungi admin.
                </div>
            @else
                {{-- Status hari ini --}}
                <div class="bg-white shadow-sm rounded-lg p-5 border border-gray-100">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Hari ini</p>
                            <p class="mt-0.5 text-sm font-semibold text-gray-900">
                                {{ \App\Support\AppTimezone::nowDisplay()->locale('id')->translatedFormat('l, j F Y') }}
                            </p>
                        </div>
                        @if ($todayRow)
                            <x-attendance-status :parts="$todayRow['status_parts'] ?? []" :fallback-status="$todayRow['status']" />
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">—</span>
                        @endif
                    </div>
                    @if ($todayRow && ($todayRow['status'] ?? '') !== 'Tidak Masuk')
                        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                            <div>
                                <p class="text-xs text-gray-500">Masuk</p>
                                <p @class(['font-medium tabular-nums', 'text-red-600' => !empty($todayRow['is_late']), 'text-gray-900' => empty($todayRow['is_late'])])>
                                    {{ $todayRow['clock_in'] ?? '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Istirahat</p>
                                <p class="font-medium text-gray-900 tabular-nums">{{ $todayRow['break_start'] ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Kembali</p>
                                <p class="font-medium text-gray-900 tabular-nums">{{ $todayRow['break_end'] ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Pulang</p>
                                <p @class(['font-medium tabular-nums', 'text-red-600' => !empty($todayRow['is_early_out']) || !empty($todayRow['is_short_work']), 'text-gray-900' => empty($todayRow['is_early_out']) && empty($todayRow['is_short_work'])])>
                                    {{ $todayRow['clock_out'] ?? '—' }}
                                </p>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Ringkasan bulan --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-4 gap-3">
                    <div class="bg-white shadow-sm rounded-lg p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Hari kerja</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-800">{{ $summary['total'] }}</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">OK</p>
                        <p class="mt-1 text-2xl font-semibold text-green-700">{{ $summary['ok'] }}</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Not OK</p>
                        <p class="mt-1 text-2xl font-semibold text-red-700">{{ $summary['not_ok'] }}</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Tidak masuk</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-600">{{ $summary['tidak_masuk'] }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $summary['terlambat'] > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">
                        Telat {{ $summary['terlambat'] }}@if ($summary['menit_terlambat'] > 0)<span class="opacity-70"> ({{ ($fmtHm)($summary['menit_terlambat']) }})</span>@endif
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $summary['istirahat_lebih'] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}">
                        Istirahat+ {{ $summary['istirahat_lebih'] }}@if ($summary['menit_istirahat_lebih'] > 0)<span class="opacity-70"> ({{ ($fmtHm)($summary['menit_istirahat_lebih']) }})</span>@endif
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $summary['pulang_awal'] > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">
                        Pulang awal {{ $summary['pulang_awal'] }}@if ($summary['menit_pulang_awal'] > 0)<span class="opacity-70"> ({{ ($fmtHm)($summary['menit_pulang_awal']) }})</span>@endif
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $summary['jam_kerja_kurang'] > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">
                        Jam kurang {{ $summary['jam_kerja_kurang'] }}@if ($summary['menit_jam_kerja_kurang'] > 0)<span class="opacity-70"> ({{ ($fmtHm)($summary['menit_jam_kerja_kurang']) }})</span>@endif
                    </span>
                </div>

                {{-- Detail harian --}}
                <div class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-100">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-800">Detail absensi · {{ $periodLabel }}</h3>
                    </div>
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
                                    <th class="px-6 py-2.5 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($rows as $row)
                                    @php $hasReason = ! empty($reasonDates[$row['date'] ?? ''] ?? null); @endphp
                                    <tr wire:key="my-{{ $row['date'] }}" class="hover:bg-gray-50 {{ ($row['status'] ?? '') === 'Tidak Masuk' ? 'bg-red-50/40' : '' }}">
                                        <td class="px-6 py-3 whitespace-nowrap text-gray-700">{{ $row['date_label'] }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap tabular-nums {{ !empty($row['is_late']) ? 'text-red-600 font-medium' : 'text-gray-700' }}">
                                            {{ $row['clock_in'] ?? '—' }}
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-gray-700 tabular-nums">{{ $row['break_start'] ?? '—' }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap text-gray-700 tabular-nums">{{ $row['break_end'] ?? '—' }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap tabular-nums {{ !empty($row['is_early_out']) || !empty($row['is_short_work']) ? 'text-red-600 font-medium' : 'text-gray-700' }}">
                                            {{ $row['clock_out'] ?? '—' }}
                                        </td>
                                        <td class="px-6 py-3 align-top">
                                            <x-attendance-status :parts="$row['status_parts'] ?? []" :fallback-status="$row['status']" />
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-right">
                                            <button
                                                type="button"
                                                wire:click="openReason('{{ $row['date'] }}', {{ \Illuminate\Support\Js::from($row['date_label'] ?? '') }})"
                                                class="inline-flex items-center justify-center p-1.5 rounded-md transition {{ $hasReason ? 'text-[#f7340d] bg-orange-50 hover:bg-orange-100' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-100' }}"
                                                title="{{ $hasReason ? 'Lihat / edit alasan' : 'Isi alasan' }}"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">
                                            Belum ada data absensi untuk bulan ini.
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
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600/50 p-4"
            wire:keydown.escape.window="closeReasonModal"
        >
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg overflow-hidden" @click.stop>
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Alasan</h3>
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
                                    class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                @error($field['error'])
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endforeach

                    <div class="pt-3 mt-1 border-t border-gray-100">
                        <label for="reasonDay" class="block text-sm font-medium text-gray-800 mb-1.5">Alasan</label>
                        <textarea
                            id="reasonDay"
                            rows="3"
                            maxlength="1000"
                            wire:model="reasonDay"
                            placeholder="Alasan umum untuk hari ini (opsional)"
                            class="block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                        @error('reasonDay')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-100 bg-gray-50">
                    <x-secondary-button type="button" wire:click="closeReasonModal">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="saveReasons" wire:loading.attr="disabled">
                        Simpan
                    </x-primary-button>
                </div>
            </div>
        </div>
    @endif
</div>
