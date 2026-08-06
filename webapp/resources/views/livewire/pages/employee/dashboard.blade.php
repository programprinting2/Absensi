<?php

use App\Models\WorkSchedule;
use App\Support\AppTimezone;
use App\Services\AttendanceReportService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function with(AttendanceReportService $reports): array
    {
        $user = auth()->user();
        $employee = $user->employee;

        if (! $employee) {
            return [
                'employee' => null,
                'periodLabel' => null,
                'rows' => collect(),
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

    <div class="py-6" wire:poll.60s.visible>
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
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
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($rows as $row)
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
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">
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
</div>
