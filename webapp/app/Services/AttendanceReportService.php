<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\WorkSchedule;
use App\Support\AppTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceReportService
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveRange(string $period, int $year, ?int $month = null, ?int $day = null): array
    {
        $tz = AppTimezone::display();

        return match ($period) {
            'year' => [
                Carbon::create($year, 1, 1, 0, 0, 0, $tz)->startOfYear()->utc(),
                Carbon::create($year, 1, 1, 0, 0, 0, $tz)->endOfYear()->utc(),
            ],
            'month' => [
                Carbon::create($year, $month ?? 1, 1, 0, 0, 0, $tz)->startOfMonth()->utc(),
                Carbon::create($year, $month ?? 1, 1, 0, 0, 0, $tz)->endOfMonth()->utc(),
            ],
            'day' => [
                Carbon::create($year, $month ?? 1, $day ?? 1, 0, 0, 0, $tz)->startOfDay()->utc(),
                Carbon::create($year, $month ?? 1, $day ?? 1, 0, 0, 0, $tz)->endOfDay()->utc(),
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

        return AttendanceLog::query()
            ->select(['id', 'employee_id', 'attendance_type', 'event_time'])
            ->with(['employee:id,full_name'])
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
        $tz = AppTimezone::display();

        return match ($period) {
            'year' => "Periode {$year}",
            'month' => 'Periode '.Carbon::create($year, $month ?? 1, 1, 0, 0, 0, $tz)->locale('id')->translatedFormat('F Y'),
            'day' => 'Periode '.Carbon::create($year, $month ?? 1, $day ?? 1, 0, 0, 0, $tz)->locale('id')->translatedFormat('l, j F Y'),
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

                $row = $this->buildAttendanceRow($group, $schedule, $firstLocal->toDateString());
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
        $today = AppTimezone::nowDisplay()->startOfDay();
        $endDisplay = AppTimezone::toDisplay($rangeEnd);
        $end = $endDisplay->lessThan($today) ? $endDisplay->copy()->startOfDay() : $today;
        $startDisplay = AppTimezone::toDisplay($rangeStart)->startOfDay();
        $existingKeys = $existingKeys->flip();

        $rows = collect();

        foreach ($employees as $employee) {
            $employeeStart = $startDisplay->copy();
            if ($employee->created_at) {
                $createdLocal = AppTimezone::toDisplay($employee->created_at)->startOfDay();
                if ($createdLocal->greaterThan($employeeStart)) {
                    $employeeStart = $createdLocal;
                }
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
                    'is_short_work' => false,
                    'status' => 'Tidak Masuk',
                    'compliance_ok' => false,
                    'compliance_issues' => ['Tidak masuk'],
                    'status_parts' => [],
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

            $row = $this->buildAttendanceRow($logs, $schedule, AppTimezone::nowDisplay()->toDateString());
            $row['employee'] = $employee;

            return $row;
        })->values();
    }

    /**
     * Bangun baris ringkasan (jam per jenis absensi + flag status) dari
     * sekumpulan log milik satu karyawan pada satu hari.
     *
     * @param  Collection<int, AttendanceLog>  $logs
     * @param  string|null  $date  Tanggal lokal Y-m-d (untuk aturan compliance hari ini)
     */
    private function buildAttendanceRow(Collection $logs, ?WorkSchedule $schedule, ?string $date = null): array
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
            'is_short_work' => false,
            'status' => 'Off',
            'compliance_ok' => false,
            'compliance_issues' => [],
            'status_parts' => [],
        ];

        $breakStartAt = null;
        $breakEndAt = null;
        $clockInAt = null;
        $clockOutAt = null;
        $latestType = null;
        $breakMinutes = null;

        foreach ($logs as $log) {
            $local = $this->toLocal($log->event_time);
            $row[$log->attendance_type] = $local->format('H:i');
            $latestType = $log->attendance_type;

            if ($log->attendance_type === 'clock_in') {
                $clockInAt = $local;
            } elseif ($log->attendance_type === 'break_start') {
                $breakStartAt = $local;
            } elseif ($log->attendance_type === 'break_end') {
                $breakEndAt = $local;
            } elseif ($log->attendance_type === 'clock_out') {
                $clockOutAt = $local;
            }
        }

        $netMinutes = null;
        $required = (int) ($schedule?->work_duration_minutes ?? 0);

        if ($breakStartAt && $breakEndAt && $breakEndAt->greaterThan($breakStartAt)) {
            $breakMinutes = (int) round(abs($breakStartAt->diffInMinutes($breakEndAt)));
            $row['break_duration'] = $this->formatDuration($breakMinutes);

            if ($schedule && $breakMinutes > (int) $schedule->break_duration_minutes) {
                $row['is_over_break'] = true;
            }
        }

        if ($schedule) {
            $lateThreshold = $schedule->late_after_time ?: $schedule->clock_in_time;
            if ($row['clock_in'] && $this->timeToMinutes($row['clock_in']) > $this->timeToMinutes($lateThreshold)) {
                $row['is_late'] = true;
                $row['late_minutes'] = $this->timeToMinutes($row['clock_in']) - $this->timeToMinutes($lateThreshold);
            }

            if ($row['clock_out'] && $this->timeToMinutes($row['clock_out']) < $this->timeToMinutes($schedule->clock_out_time)) {
                $row['is_early_out'] = true;
                $row['early_out_minutes'] = $this->timeToMinutes($schedule->clock_out_time) - $this->timeToMinutes($row['clock_out']);
            }

            if ($clockInAt && $clockOutAt && $clockOutAt->greaterThan($clockInAt)) {
                $grossMinutes = (int) round(abs($clockInAt->diffInMinutes($clockOutAt)));
                $allowedBreak = (int) ($schedule->break_duration_minutes ?? 0);
                $actualBreak = (int) ($breakMinutes ?? 0);

                // Istirahat dalam jatah dikurangi dari span (bukan jam kerja).
                // Over break tidak ikut mengurangi jam kerja di sini — dilapor terpisah.
                $breakForNet = min($actualBreak, $allowedBreak);
                $netMinutes = $grossMinutes - $breakForNet;
                $row['net_work_minutes'] = $netMinutes;

                if ($required > 0 && $netMinutes < $required) {
                    $row['is_short_work'] = true;
                    $row['short_work_minutes'] = $required - $netMinutes;
                }

                if ($required > 0 && $netMinutes > $required) {
                    $row['overtime_minutes'] = $netMinutes - $required;
                }
            }

            if ($row['is_over_break'] && $breakMinutes !== null) {
                $row['over_break_minutes'] = $breakMinutes - (int) $schedule->break_duration_minutes;
            }
        }

        $row['status'] = match ($latestType) {
            null => 'Off',
            'clock_in', 'break_end' => 'Bekerja',
            'break_start' => 'Istirahat',
            'clock_out' => 'Pulang',
            default => 'Off',
        };

        $row['status_parts'] = $this->buildStatusParts($row, $netMinutes, $schedule, $date);
        $row = array_merge($row, $this->evaluateCompliance($row, $date, $schedule));

        return $row;
    }

    /**
     * Status kolom (metrik, hijau):
     * Terlambat = 0 : 15
     * Over break = 0 : 5
     * Pulang awal = 1 : 5
     * Lembur = 1 : 0
     *
     * @return list<array{label: string, ok: bool, display: ?string, negative: bool, metric: bool}>
     */
    private function buildStatusParts(array $row, ?int $netMinutes, ?WorkSchedule $schedule, ?string $date): array
    {
        if ($row['status'] === 'Off' && ! $row['clock_in']) {
            return [];
        }

        $parts = [];

        $late = (int) ($row['late_minutes'] ?? 0);
        if ($late > 0) {
            $parts[] = [
                'label' => 'Terlambat',
                'ok' => false,
                'display' => $this->formatHmPair($late),
                'negative' => false,
                'metric' => true,
            ];
        }

        $over = (int) ($row['over_break_minutes'] ?? 0);
        if ($over > 0) {
            $parts[] = [
                'label' => 'Over break',
                'ok' => false,
                'display' => $this->formatHmPair($over),
                'negative' => false,
                'metric' => true,
            ];
        }

        $early = (int) ($row['early_out_minutes'] ?? 0);
        $short = (int) ($row['short_work_minutes'] ?? 0);

        if ($early > 0) {
            $parts[] = [
                'label' => 'Pulang awal',
                'ok' => false,
                'display' => $this->formatHmPair($early),
                'negative' => false,
                'metric' => true,
            ];
        }

        // Jam kurang = sisa kekurangan jam kerja yang belum tercakup pulang awal.
        // Istirahat normal tidak menambah baris ini; over break dilapor terpisah.
        $jamKurang = $early > 0 ? max(0, $short - $early) : $short;
        if ($jamKurang > 1) {
            $parts[] = [
                'label' => 'Jam kurang',
                'ok' => false,
                'display' => $this->formatHmPair($jamKurang),
                'negative' => false,
                'metric' => true,
            ];
        }

        $overtime = (int) ($row['overtime_minutes'] ?? 0);
        if ($overtime <= 0 && $netMinutes !== null) {
            $required = (int) ($schedule?->work_duration_minutes ?? 0);
            if ($required > 0) {
                $overtime = max(0, $netMinutes - $required);
            }
        }

        if ($overtime > 0) {
            $parts[] = [
                'label' => 'Lembur',
                'ok' => true,
                'display' => $this->formatHmPair($overtime),
                'negative' => false,
                'metric' => true,
            ];
        }

        if ($this->requiresClockOut($date, $schedule) && $row['clock_in'] && ! $row['clock_out']) {
            $parts[] = [
                'label' => 'Kerja',
                'ok' => false,
                'display' => null,
                'negative' => false,
                'metric' => false,
            ];
        }

        return $parts;
    }

    /**
     * OK jika ada absensi lengkap tanpa pelanggaran (terlambat / over break / pulang awal / jam kurang).
     *
     * @return array{compliance_ok: bool, compliance_issues: list<string>}
     */
    private function evaluateCompliance(array $row, ?string $date = null, ?WorkSchedule $schedule = null): array
    {
        // Baris Off tanpa log sama sekali.
        if (($row['status'] ?? '') === 'Off' && ! ($row['clock_in'] ?? null) && ! ($row['clock_out'] ?? null)) {
            return [
                'compliance_ok' => false,
                'compliance_issues' => [],
            ];
        }

        $issues = [];

        $late = (int) ($row['late_minutes'] ?? 0);
        if (! empty($row['is_late']) || $late > 0) {
            $issues[] = 'Terlambat = '.$this->formatHmPair($late > 0 ? $late : 0);
        }

        $over = (int) ($row['over_break_minutes'] ?? 0);
        if (! empty($row['is_over_break']) || $over > 0) {
            $issues[] = 'Over break = '.$this->formatHmPair($over > 0 ? $over : 0);
        }

        $early = (int) ($row['early_out_minutes'] ?? 0);
        if (! empty($row['is_early_out']) || $early > 0) {
            $issues[] = 'Pulang awal = '.$this->formatHmPair($early > 0 ? $early : 0);
        }

        $short = (int) ($row['short_work_minutes'] ?? 0);
        $jamKurang = $early > 0 ? max(0, $short - $early) : $short;
        if ($jamKurang > 1) {
            $issues[] = 'Jam kurang = '.$this->formatHmPair($jamKurang);
        }

        if ($this->requiresClockOut($date, $schedule) && ($row['clock_in'] ?? null) && ! ($row['clock_out'] ?? null)) {
            $issues[] = 'Kerja = Not OK';
        }

        $hasAttendance = (bool) (($row['clock_in'] ?? null) || ($row['clock_out'] ?? null));

        return [
            'compliance_ok' => $hasAttendance && $issues === [],
            'compliance_issues' => $issues,
        ];
    }

    /**
     * Pulang wajib dicek hanya setelah jam kerja hari itu selesai,
     * atau untuk tanggal yang sudah lewat.
     */
    private function requiresClockOut(?string $date, ?WorkSchedule $schedule): bool
    {
        if (! $date) {
            return true;
        }

        $today = AppTimezone::nowDisplay()->toDateString();

        if ($date < $today) {
            return true;
        }

        if ($date > $today) {
            return false;
        }

        $clockOut = substr((string) ($schedule?->clock_out_time ?: '17:00'), 0, 5);
        $now = AppTimezone::nowDisplay();
        $nowMinutes = ((int) $now->format('G')) * 60 + (int) $now->format('i');

        return $nowMinutes >= $this->timeToMinutes($clockOut);
    }

    /**
     * event_time disimpan UTC sejati; tampilan dikonversi ke timezone bisnis
     * (Pengaturan → Identitas → Timezone Tampilan).
     */
    private function toLocal(Carbon $time): Carbon
    {
        return AppTimezone::toDisplay($time);
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

    /**
     * Contoh: 189 → "3 : 9"
     */
    private function formatHmPair(int $minutes): string
    {
        $total = abs($minutes);
        $hours = intdiv($total, 60);
        $remain = $total % 60;

        return "{$hours} : {$remain}";
    }

    /**
     * Contoh: 189 → "189 m (3 : 9)"
     */
    private function formatMinutesWithHours(int $minutes): string
    {
        $total = abs($minutes);

        return "{$total} m (".$this->formatHmPair($total).')';
    }
}
