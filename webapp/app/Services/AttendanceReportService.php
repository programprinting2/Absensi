<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\WorkSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceReportService
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveRange(string $period, int $year, ?int $month = null, ?int $day = null): array
    {
        return match ($period) {
            'year' => [
                Carbon::create($year, 1, 1)->startOfYear(),
                Carbon::create($year, 1, 1)->endOfYear(),
            ],
            'month' => [
                Carbon::create($year, $month ?? 1, 1)->startOfMonth(),
                Carbon::create($year, $month ?? 1, 1)->endOfMonth(),
            ],
            'day' => [
                Carbon::create($year, $month ?? 1, $day ?? 1)->startOfDay(),
                Carbon::create($year, $month ?? 1, $day ?? 1)->endOfDay(),
            ],
            default => throw new \InvalidArgumentException("Unknown period: {$period}"),
        };
    }

    /**
     * @return Collection<int, AttendanceLog>
     */
    public function forRange(string $period, int $year, ?int $month = null, ?int $day = null, ?string $employeeId = null): Collection
    {
        [$start, $end] = $this->resolveRange($period, $year, $month, $day);

        return AttendanceLog::with(['employee', 'device'])
            ->whereBetween('event_time', [$start, $end])
            ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
            ->orderBy('event_time')
            ->get();
    }

    /**
     * Label periode yang sedang diterapkan, mis. "Periode Juli 2026".
     */
    public function describePeriod(string $period, int $year, ?int $month = null, ?int $day = null): string
    {
        return match ($period) {
            'year' => "Periode {$year}",
            'month' => 'Periode '.Carbon::create($year, $month ?? 1, 1)->locale('id')->translatedFormat('F Y'),
            'day' => 'Periode '.Carbon::create($year, $month ?? 1, $day ?? 1)->locale('id')->translatedFormat('l, j F Y'),
            default => 'Periode',
        };
    }

    /**
     * Kelompokkan log per karyawan per tanggal, menjadi baris pivot
     * (clock_in, break_start, break_end, clock_out), plus flag status
     * (terlambat, istirahat lebih, pulang cepat) jika $schedule diberikan.
     *
     * Jika $employees, $rangeStart, dan $rangeEnd diberikan, hari kerja
     * (Senin-Sabtu, sampai hari ini) tanpa log sama sekali untuk karyawan
     * tersebut ikut ditambahkan sebagai baris "Tidak Masuk" — supaya
     * ketidakhadiran juga kelihatan di laporan, bukan cuma menghilang.
     *
     * @param  Collection<int, \App\Models\Employee>|null  $employees
     */
    public function pivotByEmployeeAndDate(
        Collection $logs,
        ?WorkSchedule $schedule = null,
        ?Collection $employees = null,
        ?Carbon $rangeStart = null,
        ?Carbon $rangeEnd = null,
    ): Collection {
        $rows = $logs
            ->groupBy(fn (AttendanceLog $log) => $log->employee_id.'|'.$this->toLocal($log->event_time)->toDateString())
            ->map(function (Collection $group) use ($schedule) {
                $first = $group->first();
                $firstLocal = $this->toLocal($first->event_time);

                $row = $this->buildAttendanceRow($group, $schedule);
                $row['employee'] = $first->employee;
                $row['date'] = $firstLocal->toDateString();
                $row['date_label'] = $firstLocal->locale('id')->translatedFormat('l, j M y');

                return $row;
            });

        if ($employees && $rangeStart && $rangeEnd) {
            $rows = $rows->union($this->absenceRows($employees, $rows->keys(), $rangeStart, $rangeEnd));
        }

        return $rows->sortByDesc('date')->values();
    }

    /**
     * Baris "Tidak Masuk" untuk hari kerja tanpa log sama sekali, dibatasi
     * dari tanggal karyawan itu dibuat sampai hari ini (tidak menandai hari
     * sebelum karyawannya terdaftar, atau hari yang belum terjadi).
     *
     * @param  Collection<int, \App\Models\Employee>  $employees
     * @param  Collection<int, string>  $existingKeys
     */
    private function absenceRows(Collection $employees, Collection $existingKeys, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        $today = Carbon::today();
        $end = $rangeEnd->lessThan($today) ? $rangeEnd->copy() : $today;
        $existingKeys = $existingKeys->flip();

        $rows = collect();

        foreach ($employees as $employee) {
            $employeeStart = $rangeStart->copy();
            if ($employee->created_at && $employee->created_at->greaterThan($employeeStart)) {
                $employeeStart = $employee->created_at->copy()->startOfDay();
            }

            if ($employeeStart->greaterThan($end)) {
                continue;
            }

            for ($date = $employeeStart->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
                if ($date->isSunday()) {
                    continue;
                }

                $key = $employee->id.'|'.$date->toDateString();

                if ($existingKeys->has($key)) {
                    continue;
                }

                $rows->put($key, [
                    'employee' => $employee,
                    'date' => $date->toDateString(),
                    'date_label' => $date->copy()->locale('id')->translatedFormat('l, j M y'),
                    'clock_in' => null,
                    'break_start' => null,
                    'break_end' => null,
                    'clock_out' => null,
                    'break_duration' => null,
                    'is_late' => false,
                    'is_over_break' => false,
                    'is_early_out' => false,
                    'status' => 'Tidak Masuk',
                ]);
            }
        }

        return $rows;
    }

    /**
     * Status absensi hari ini untuk setiap karyawan aktif (termasuk yang
     * belum absen sama sekali / "Off"), dipakai di dashboard.
     *
     * @param  Collection<int, \App\Models\Employee>  $employees
     * @param  Collection<int, AttendanceLog>  $todayLogs
     */
    public function todayStatusForEmployees(Collection $employees, Collection $todayLogs, ?WorkSchedule $schedule = null): Collection
    {
        $logsByEmployee = $todayLogs->groupBy('employee_id');

        return $employees->map(function ($employee) use ($logsByEmployee, $schedule) {
            $logs = $logsByEmployee->get($employee->id, collect())->sortBy('event_time');

            $row = $this->buildAttendanceRow($logs, $schedule);
            $row['employee'] = $employee;

            return $row;
        })->values();
    }

    /**
     * Bangun baris ringkasan (jam per jenis absensi + flag status) dari
     * sekumpulan log milik satu karyawan pada satu hari.
     *
     * @param  Collection<int, AttendanceLog>  $logs
     */
    private function buildAttendanceRow(Collection $logs, ?WorkSchedule $schedule): array
    {
        $row = [
            'clock_in' => null,
            'break_start' => null,
            'break_end' => null,
            'clock_out' => null,
            'break_duration' => null,
            'is_late' => false,
            'is_over_break' => false,
            'is_early_out' => false,
            'status' => 'Off',
        ];

        $breakStartAt = null;
        $breakEndAt = null;
        $latestType = null;

        foreach ($logs as $log) {
            $row[$log->attendance_type] = $this->toLocal($log->event_time)->format('H:i');
            $latestType = $log->attendance_type;

            if ($log->attendance_type === 'break_start') {
                $breakStartAt = $log->event_time;
            } elseif ($log->attendance_type === 'break_end') {
                $breakEndAt = $log->event_time;
            }
        }

        if ($breakStartAt && $breakEndAt && $breakEndAt->greaterThan($breakStartAt)) {
            $breakMinutes = (int) $breakStartAt->diffInMinutes($breakEndAt);
            $row['break_duration'] = $this->formatDuration($breakMinutes);

            if ($schedule && $breakMinutes > $schedule->break_duration_minutes) {
                $row['is_over_break'] = true;
            }
        }

        if ($schedule) {
            if ($row['clock_in'] && $this->timeToMinutes($row['clock_in']) > $this->timeToMinutes($schedule->clock_in_time)) {
                $row['is_late'] = true;
            }

            if ($row['clock_out'] && $this->timeToMinutes($row['clock_out']) < $this->timeToMinutes($schedule->clock_out_time)) {
                $row['is_early_out'] = true;
            }
        }

        $row['status'] = match ($latestType) {
            null => 'Off',
            'clock_in', 'break_end' => 'Bekerja',
            'break_start' => 'Istirahat',
            'clock_out' => 'Pulang',
            default => 'Off',
        };

        return $row;
    }

    /**
     * event_time disimpan sebagai UTC asli (timestamptz) di database, tapi
     * jam kerja & tampilan ke user selalu WIB — konversi di titik ini
     * sebelum ditampilkan/dikelompokkan per tanggal.
     */
    private function toLocal(Carbon $time): Carbon
    {
        return $time->copy()->setTimezone('Asia/Jakarta');
    }

    private function timeToMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }

    private function formatDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours === 0) {
            return "{$remainingMinutes} menit";
        }

        if ($remainingMinutes === 0) {
            return "{$hours} jam";
        }

        return "{$hours} jam {$remainingMinutes} menit";
    }
}
