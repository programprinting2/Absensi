<?php

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Services\AttendanceReportService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function with(AttendanceReportService $reports): array
    {
        $employees = Employee::where('is_active', true)->orderBy('employee_code')->get();
        $schedule = WorkSchedule::active();

        $todayLogs = AttendanceLog::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('event_time', [now()->startOfDay(), now()->endOfDay()])
            ->get();

        $rows = $reports->todayStatusForEmployees($employees, $todayLogs, $schedule);

        return [
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

<div wire:poll.5s>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div
                x-data="{ now: new Date({{ now()->timestamp * 1000 }}) }"
                x-init="setInterval(() => now = new Date(now.getTime() + 1000), 1000)"
                class="bg-white shadow-sm rounded-lg p-5 flex flex-wrap items-center justify-between gap-4"
            >
                <div class="flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider">Tanggal</p>
                        <p class="text-lg font-semibold text-gray-800"
                           x-text="now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })"></p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="text-xs text-gray-500 uppercase tracking-wider">Waktu Server</p>
                        <p class="text-2xl font-mono font-semibold text-gray-800"
                           x-text="now.toLocaleTimeString('id-ID', { hour12: false })"></p>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm font-medium text-gray-500">Masuk</p>
                    <p class="mt-1 text-2xl font-semibold text-green-700">{{ $summary['masuk'] }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm font-medium text-gray-500">Tidak Masuk (Off)</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-500">{{ $summary['off'] }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm font-medium text-gray-500">Sedang Istirahat</p>
                    <p class="mt-1 text-2xl font-semibold text-yellow-700">{{ $summary['istirahat'] }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm font-medium text-gray-500">Terlambat</p>
                    <p class="mt-1 text-2xl font-semibold text-red-700">{{ $summary['terlambat'] }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm font-medium text-gray-500">Over Break</p>
                    <p class="mt-1 text-2xl font-semibold text-red-700">{{ $summary['over_break'] }}</p>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Absensi Hari Ini</h3>

                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <th class="px-6 py-3">Karyawan</th>
                                    <th class="px-6 py-3">Masuk</th>
                                    <th class="px-6 py-3">Istirahat</th>
                                    <th class="px-6 py-3">Kembali</th>
                                    <th class="px-6 py-3">Durasi</th>
                                    <th class="px-6 py-3">Pulang</th>
                                    <th class="px-6 py-3 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($rows as $row)
                                    <tr wire:key="dashboard-{{ $row['employee']->id }}" class="hover:bg-gray-50">
                                        <td class="px-6 py-3 whitespace-nowrap font-medium text-gray-900">{{ $row['employee']->full_name }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <x-status-tooltip :value="$row['clock_in'] ?? '-'" :flagged="$row['is_late']" tooltip="Terlambat" />
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-gray-700">{{ $row['break_start'] ?? '-' }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap text-gray-700">{{ $row['break_end'] ?? '-' }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <x-status-tooltip :value="$row['break_duration'] ?? '-'" :flagged="$row['is_over_break']" tooltip="Over Break" />
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <x-status-tooltip :value="$row['clock_out'] ?? '-'" :flagged="$row['is_early_out']" tooltip="Pulang Lebih Awal" />
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-right">
                                            @php
                                                $statusColor = match ($row['status']) {
                                                    'Bekerja' => 'bg-green-100 text-green-800',
                                                    'Istirahat' => 'bg-yellow-100 text-yellow-800',
                                                    'Pulang' => 'bg-blue-100 text-blue-800',
                                                    default => 'bg-gray-100 text-gray-600',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                                {{ $row['status'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-6 text-center text-gray-500">
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
</div>
