<?php

use App\Models\AttendanceDayReason;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Support\AppTimezone;
use App\Services\AttendanceReportService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function with(AttendanceReportService $reports): array
    {
        $now = AppTimezone::nowDisplay();
        $year = (int) $now->year;
        $month = (int) $now->month;
        $periodLabel = $now->copy()->locale('id')->translatedFormat('F Y');

        $employees = Employee::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code', 'created_at']);

        // Hanya bulan aktif — tidak query rekap bulan sebelumnya.
        $logs = $reports->forRange('month', $year, $month, null, null);
        [$rangeStart, $rangeEnd] = $reports->resolveRange('month', $year, $month, null);

        $rows = $reports->pivotByEmployeeAndDate(
            $logs,
            WorkSchedule::active(),
            $employees,
            $rangeStart,
            $rangeEnd,
        );

        $monthStart = Carbon::create($year, $month, 1, 0, 0, 0, AppTimezone::display())->toDateString();
        $monthEnd = Carbon::create($year, $month, 1, 0, 0, 0, AppTimezone::display())->endOfMonth()->toDateString();

        $reasonsByKey = AttendanceDayReason::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->get()
            ->keyBy(fn (AttendanceDayReason $r) => $r->employee_id.'|'.$r->work_date->toDateString());

        $fmtHm = function (int $minutes): string {
            $total = abs($minutes);
            $hours = intdiv($total, 60);
            $remain = $total % 60;

            return "{$hours} : {$remain}";
        };

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
                'menit_terlambat' => (int) $group->sum(fn ($r) => (int) ($r['late_minutes'] ?? 0)),
                'menit_istirahat_lebih' => (int) $group->sum(fn ($r) => (int) ($r['over_break_minutes'] ?? 0)),
                'menit_pulang_awal' => (int) $group->sum(fn ($r) => (int) ($r['early_out_minutes'] ?? 0)),
                'menit_jam_kerja_kurang' => (int) $group->sum(fn ($r) => (int) ($r['short_work_minutes'] ?? 0)),
            ];
        };

        $summary = $buildStats($rows);

        $detailGroups = $rows
            ->groupBy(fn ($r) => $r['employee']->id)
            ->map(function ($group) use ($buildStats, $reasonsByKey, $formatReason) {
                $employee = $group->first()['employee'];

                $detailRows = $group->sortByDesc('date')->values()->map(function (array $row) use ($employee, $reasonsByKey, $formatReason) {
                    $key = $employee->id.'|'.($row['date'] ?? '');
                    $row['reason_lines'] = $formatReason($reasonsByKey->get($key));

                    return $row;
                });

                return [
                    'employee' => $employee,
                    'rows' => $detailRows,
                    'stats' => $buildStats($group),
                ];
            })
            ->sortBy(fn ($g) => mb_strtolower($g['employee']->full_name))
            ->values();

        return [
            'periodLabel' => $periodLabel,
            'summary' => $summary,
            'detailGroups' => $detailGroups,
            'fmtHm' => $fmtHm,
            'todayLabel' => $now->copy()->locale('id')->translatedFormat('l, j F Y'),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Dashboard</h2>
            <p class="mt-0.5 text-sm text-gray-500">Laporan absensi · {{ $periodLabel }}</p>
        </div>
    </x-slot>

    <div class="h-[calc(100vh-8rem)] flex flex-col" wire:poll.60s.visible>
        <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-4 overflow-y-auto">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 shrink-0">
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

            <div class="flex flex-wrap items-center gap-1.5 shrink-0">
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

            <div class="space-y-2" x-data="{ open: {} }">
                @forelse ($detailGroups as $group)
                    @php $gid = $group['employee']->id; $s = $group['stats']; @endphp
                    <div wire:key="my-dash-{{ $gid }}" class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-100">
                        <button
                            type="button"
                            class="w-full px-4 py-3 text-left hover:bg-gray-50 transition"
                            @click="open['{{ $gid }}'] = !open['{{ $gid }}']"
                            :aria-expanded="!!open['{{ $gid }}']"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <svg class="h-4 w-4 text-gray-500 shrink-0 transition-transform" :class="open['{{ $gid }}'] ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                </svg>
                                <span class="text-sm font-semibold text-gray-900 truncate">{{ $group['employee']->full_name }}</span>
                                <span class="text-xs text-gray-400 shrink-0">{{ $s['total'] }} hari</span>
                            </div>
                            <div class="mt-1.5 ml-7 flex flex-wrap items-center gap-1.5">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">OK {{ $s['ok'] }}</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $s['not_ok'] > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">Not OK {{ $s['not_ok'] }}</span>
                                @if ($s['tidak_masuk'] > 0)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Absen {{ $s['tidak_masuk'] }}</span>
                                @endif
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $s['terlambat'] > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">
                                    Telat {{ $s['terlambat'] }}@if ($s['menit_terlambat'] > 0)<span class="opacity-70"> ({{ ($fmtHm)($s['menit_terlambat']) }})</span>@endif
                                </span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $s['istirahat_lebih'] > 0 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}">
                                    Istirahat+ {{ $s['istirahat_lebih'] }}@if ($s['menit_istirahat_lebih'] > 0)<span class="opacity-70"> ({{ ($fmtHm)($s['menit_istirahat_lebih']) }})</span>@endif
                                </span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $s['pulang_awal'] > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">
                                    Pulang awal {{ $s['pulang_awal'] }}@if ($s['menit_pulang_awal'] > 0)<span class="opacity-70"> ({{ ($fmtHm)($s['menit_pulang_awal']) }})</span>@endif
                                </span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $s['jam_kerja_kurang'] > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">
                                    Jam kurang {{ $s['jam_kerja_kurang'] }}@if ($s['menit_jam_kerja_kurang'] > 0)<span class="opacity-70"> ({{ ($fmtHm)($s['menit_jam_kerja_kurang']) }})</span>@endif
                                </span>
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
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        @foreach ($group['rows'] as $row)
                                            <tr wire:key="my-dash-row-{{ $gid }}-{{ $row['date'] }}" class="hover:bg-gray-50 {{ ($row['status'] ?? '') === 'Tidak Masuk' ? 'bg-red-50/40' : '' }}">
                                                <td class="px-6 py-3 whitespace-nowrap text-gray-700">{{ $row['date_label'] }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap tabular-nums {{ !empty($row['is_late']) ? 'text-red-600 font-medium' : 'text-gray-700' }}">{{ $row['clock_in'] ?? '—' }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap text-gray-700 tabular-nums">{{ $row['break_start'] ?? '—' }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap text-gray-700 tabular-nums">{{ $row['break_end'] ?? '—' }}</td>
                                                <td class="px-6 py-3 whitespace-nowrap tabular-nums {{ !empty($row['is_early_out']) || !empty($row['is_short_work']) ? 'text-red-600 font-medium' : 'text-gray-700' }}">{{ $row['clock_out'] ?? '—' }}</td>
                                                <td class="px-6 py-3 align-top">
                                                    <x-attendance-status :parts="$row['status_parts'] ?? []" :fallback-status="$row['status']" />
                                                </td>
                                                <td class="px-6 py-3 align-top max-w-xs">
                                                    @if (! empty($row['reason_lines']))
                                                        <div class="space-y-0.5 text-xs text-gray-700">
                                                            @foreach ($row['reason_lines'] as $i => $line)
                                                                <p @class(['font-medium text-gray-800' => $i === 0, 'text-gray-500' => $i > 0])>{{ $line }}</p>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <span class="text-gray-400">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-white shadow-sm rounded-lg px-6 py-10 text-center text-sm text-gray-500">
                        Belum ada data absensi untuk {{ $periodLabel }}.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
