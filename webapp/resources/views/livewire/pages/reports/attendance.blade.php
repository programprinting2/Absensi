<?php

use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Services\AttendanceReportService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url]
    public string $period = 'month';

    #[Url]
    public int $year;

    #[Url]
    public int $month;

    #[Url]
    public int $day;

    #[Url]
    public ?string $employee_id = null;

    public function mount(): void
    {
        $this->year ??= now()->year;
        $this->month ??= now()->month;
        $this->day ??= now()->day;
    }

    public function resetFilters(): void
    {
        $this->reset(['period', 'year', 'month', 'day', 'employee_id']);
        $this->mount();
    }

    public function with(AttendanceReportService $reports): array
    {
        $logs = $reports->forRange($this->period, $this->year, $this->month, $this->day, $this->employee_id);

        $employees = Employee::where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'created_at']);

        $employeesForAbsence = $this->employee_id
            ? $employees->where('id', $this->employee_id)
            : $employees;

        [$rangeStart, $rangeEnd] = $reports->resolveRange($this->period, $this->year, $this->month, $this->day);

        $rows = $reports->pivotByEmployeeAndDate(
            $logs,
            WorkSchedule::active(),
            $employeesForAbsence,
            $rangeStart,
            $rangeEnd,
        );

        return [
            'rows' => $rows,
            'employees' => $employees,
            'yearOptions' => range(now()->year, now()->year - 5),
            'periodLabel' => $reports->describePeriod($this->period, $this->year, $this->month, $this->day),
        ];
    }
}; ?>

<div wire:poll.5s>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Laporan Absensi</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="w-36">
                        <x-input-label for="period" value="Periode" />
                        <select id="period" wire:model.live="period" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="month">Bulanan</option>
                            <option value="day">Harian</option>
                        </select>
                    </div>

                    <div class="w-56">
                        <x-input-label for="employee_id" value="Karyawan" />
                        <select id="employee_id" wire:model.live="employee_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">Semua Karyawan</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($period === 'day')
                        <div class="w-24">
                            <x-input-label for="day" value="Tanggal" />
                            <select id="day" wire:model.live="day" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                @for ($d = 1; $d <= 31; $d++)
                                    <option value="{{ $d }}">{{ $d }}</option>
                                @endfor
                            </select>
                        </div>
                    @endif

                    <div class="w-40">
                        <x-input-label for="month" value="Bulan" />
                        <select id="month" wire:model.live="month" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            @foreach (['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] as $index => $label)
                                <option value="{{ $index + 1 }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="w-28">
                        <x-input-label for="year" value="Tahun" />
                        <select id="year" wire:model.live="year" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            @foreach ($yearOptions as $y)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="button" wire:click="resetFilters"
                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Reset
                    </button>
                </div>
            </div>

            <h3 class="text-lg font-semibold text-gray-800">{{ $periodLabel }}</h3>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-3">Tanggal</th>
                                <th class="px-6 py-3">Karyawan</th>
                                <th class="px-6 py-3">Masuk</th>
                                <th class="px-6 py-3">Istirahat</th>
                                <th class="px-6 py-3">Kembali</th>
                                <th class="px-6 py-3">Durasi</th>
                                <th class="px-6 py-3">Pulang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @php $previousDate = null; @endphp
                            @forelse ($rows as $row)
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
                                <tr wire:key="row-{{ $row['employee']->id }}-{{ $row['date'] }}" class="hover:bg-gray-50 {{ $separatorClass }} {{ $row['status'] === 'Tidak Masuk' ? 'bg-red-50/40' : '' }}">
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-700">{{ $row['date_label'] }}</td>
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
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-6 text-center text-gray-500">
                                        Tidak ada data absensi pada rentang ini.
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
