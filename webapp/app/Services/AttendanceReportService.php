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
        // $schedule tetap diterima untuk kompatibilitas pemanggil lama;
        // perhitungan memakai ShiftResolver per karyawan per tanggal.
        unset($schedule);
        $rows = $logs
            ->groupBy(fn (AttendanceLog $log) => $log->employee_id.'|'.$this->toLocal($log->event_time)->toDateString())
            ->map(function (Collection $group) {
                $first = $group->first();
                $firstLocal = $this->toLocal($first->event_time);
                $date = $firstLocal->toDateString();
                $schedule = app(ShiftResolver::class)->forEmployeeOnDate($first->employee_id, $date);

                $row = $this->buildAttendanceRow($group, $schedule, $date);
                $row['employee'] = $first->employee;
                $row['date'] = $date;
                $row['date_label'] = $firstLocal->locale('id')->translatedFormat('l, j M y');
                $row['shift_id'] = $schedule?->id;
                $row['shift_name'] = $schedule?->name;
                $row['shift_crosses_midnight'] = (bool) ($schedule?->crosses_midnight);

                return $row;
            });

        if ($employees && $rangeStart && $rangeEnd) {
            $leaveMap = app(LeaveService::class)->approvedLeavesByEmployeeDate(
                $employees->pluck('id'),
                $rangeStart,
                $rangeEnd,
            );
            $rows = $rows->union($this->absenceRows($employees, $rows->keys(), $rangeStart, $rangeEnd, $leaveMap));
        }

        return $rows->sortByDesc('date')->values();
    }

    /**
     * Baris "Tidak Masuk" / "Cuti" untuk hari kerja tanpa log sama sekali, dibatasi
     * dari tanggal karyawan itu dibuat sampai hari ini (tidak menandai hari
     * sebelum karyawannya terdaftar, atau hari yang belum terjadi).
     *
     * @param  Collection<int, \App\Models\Employee>  $employees
     * @param  Collection<int, string>  $existingKeys
     * @param  array<string, array<string, \App\Models\EmployeeLeave>>  $leaveMap
     */
    private function absenceRows(
        Collection $employees,
        Collection $existingKeys,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        array $leaveMap = [],
    ): Collection {
        $today = AppTimezone::nowDisplay()->startOfDay();
        $endDisplay = AppTimezone::toDisplay($rangeEnd);
        $end = $endDisplay->lessThan($today) ? $endDisplay->copy()->startOfDay() : $today;
        $startDisplay = AppTimezone::toDisplay($rangeStart)->startOfDay();
        $existingKeys = $existingKeys->flip();
        $resolver = app(ShiftResolver::class);

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
                $key = $employee->id.'|'.$date->toDateString();

                if ($existingKeys->has($key)) {
                    continue;
                }

                $day = $date->toDateString();
                $resolved = $resolver->resolveDay($employee->id, $day);

                // Hari libur / tidak dijadwalkan / libur karyawan / libur request → tidak jadi "Tidak Masuk"
                if (! $resolved->isWorkDay()) {
                    if (in_array($resolved->kind, [
                        \App\Support\ResolvedShiftDay::KIND_LIBUR_REQUEST,
                        \App\Support\ResolvedShiftDay::KIND_LIBUR_KARYAWAN,
                        \App\Support\ResolvedShiftDay::KIND_LIBUR_HARI,
                        \App\Support\ResolvedShiftDay::KIND_LIBUR_EVENT,
                    ], true)) {
                        $status = match ($resolved->kind) {
                            \App\Support\ResolvedShiftDay::KIND_LIBUR_REQUEST => 'Cuti',
                            \App\Support\ResolvedShiftDay::KIND_LIBUR_KARYAWAN => 'Libur Rutin',
                            default => 'Libur',
                        };
                        $leave = $leaveMap[$employee->id][$day] ?? null;
                        $rows->put($key, [
                            'employee' => $employee,
                            'date' => $day,
                            'date_label' => $date->copy()->locale('id')->translatedFormat('l, j M y'),
                            'shift_id' => null,
                            'shift_name' => null,
                            'shift_crosses_midnight' => false,
                            'clock_in' => null,
                            'break_start' => null,
                            'break_end' => null,
                            'clock_out' => null,
                            'break_duration' => null,
                            'is_late' => false,
                            'is_over_break' => false,
                            'is_early_out' => false,
                            'is_short_work' => false,
                            'is_leave' => $resolved->kind === \App\Support\ResolvedShiftDay::KIND_LIBUR_REQUEST,
                            'leave_type' => $leave?->leave_type,
                            'leave_type_label' => $leave?->typeLabel(),
                            'status' => $status,
                            'compliance_ok' => true,
                            'compliance_issues' => [],
                            'status_parts' => [],
                        ]);
                    }

                    continue;
                }

                $schedule = $resolved->schedule;

                // Wajib masuk tapi tidak ada log → Off (alpha)
                $rows->put($key, [
                    'employee' => $employee,
                    'date' => $day,
                    'date_label' => $date->copy()->locale('id')->translatedFormat('l, j M y'),
                    'shift_id' => $schedule?->id,
                    'shift_name' => $schedule?->name,
                    'shift_crosses_midnight' => (bool) ($schedule?->crosses_midnight),
                    'clock_in' => null,
                    'break_start' => null,
                    'break_end' => null,
                    'clock_out' => null,
                    'break_duration' => null,
                    'is_late' => false,
                    'is_over_break' => false,
                    'is_early_out' => false,
                    'is_short_work' => false,
                    'is_leave' => false,
                    'status' => 'Off',
                    'compliance_ok' => false,
                    'compliance_issues' => ['Off / tidak masuk'],
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
        unset($schedule);
        $logsByEmployee = $todayLogs->groupBy('employee_id');
        $today = AppTimezone::nowDisplay()->toDateString();
        $resolver = app(ShiftResolver::class);
        $leaveMap = app(LeaveService::class)->approvedLeavesByEmployeeDate(
            $employees->pluck('id'),
            $today,
            $today,
        );

        return $employees->map(function ($employee) use ($logsByEmployee, $leaveMap, $today, $resolver) {
            $logs = $logsByEmployee->get($employee->id, collect())->sortBy('event_time');
            $resolved = $resolver->resolveDay($employee->id, $today);
            $empSchedule = $resolved->isWorkDay() ? $resolved->schedule : null;

            $row = $this->buildAttendanceRow($logs, $empSchedule, $today);
            $row['employee'] = $employee;
            $row['shift_id'] = $empSchedule?->id;
            $row['shift_name'] = $empSchedule?->name;

            if (! $row['clock_in'] && ! $row['clock_out']) {
                if ($resolved->kind === \App\Support\ResolvedShiftDay::KIND_LIBUR_REQUEST
                    || ($leaveMap[$employee->id][$today] ?? null)) {
                    $leave = $leaveMap[$employee->id][$today] ?? null;
                    $row['status'] = 'Cuti';
                    $row['is_leave'] = true;
                    $row['leave_type'] = $leave?->leave_type;
                    $row['leave_type_label'] = $leave?->typeLabel();
                    $row['compliance_ok'] = true;
                    $row['compliance_issues'] = [];
                    $row['status_parts'] = [];
                } elseif (in_array($resolved->kind, [
                    \App\Support\ResolvedShiftDay::KIND_LIBUR_KARYAWAN,
                    \App\Support\ResolvedShiftDay::KIND_LIBUR_HARI,
                    \App\Support\ResolvedShiftDay::KIND_LIBUR_EVENT,
                    \App\Support\ResolvedShiftDay::KIND_UNSCHEDULED,
                ], true)) {
                    $row['status'] = match ($resolved->kind) {
                        \App\Support\ResolvedShiftDay::KIND_UNSCHEDULED => 'Jadwal belum diatur',
                        \App\Support\ResolvedShiftDay::KIND_LIBUR_KARYAWAN => 'Libur Rutin',
                        default => 'Libur',
                    };
                    $row['is_leave'] = false;
                    $row['compliance_ok'] = true;
                    $row['compliance_issues'] = [];
                    $row['status_parts'] = [];
                } elseif ($resolved->isWorkDay()) {
                    $row['status'] = 'Off';
                }
            }

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

        // Per jenis absensi: pakai kejadian pertama (paling awal).
        // Status hari ini tetap mengikuti log terakhir secara kronologis.
        foreach ($logs->sortBy('event_time') as $log) {
            $type = $log->attendance_type;
            if (! in_array($type, ['clock_in', 'break_start', 'break_end', 'clock_out'], true)) {
                continue;
            }

            $local = $this->toLocal($log->event_time);
            $latestType = $type;

            if ($row[$type] !== null) {
                continue;
            }

            $row[$type] = $local->format('H:i');

            if ($type === 'clock_in') {
                $clockInAt = $local;
            } elseif ($type === 'break_start') {
                $breakStartAt = $local;
            } elseif ($type === 'break_end') {
                $breakEndAt = $local;
            } elseif ($type === 'clock_out') {
                $clockOutAt = $local;
            }
        }

        $netMinutes = null;
        $required = (int) ($schedule?->work_duration_minutes ?? 0);
        $allowedBreak = (int) ($schedule?->break_duration_minutes ?? 0);

        // Pasangan istirahat lengkap → hitung durasi & over break.
        if ($breakStartAt && $breakEndAt && $breakEndAt->greaterThan($breakStartAt)) {
            $breakMinutes = (int) round(abs($breakStartAt->diffInMinutes($breakEndAt)));
            $row['break_duration'] = $this->formatDuration($breakMinutes);

            if ($schedule && $breakMinutes > $allowedBreak) {
                $row['is_over_break'] = true;
            }
        }

        // Sudah absen istirahat (keluar) tapi belum kembali.
        $missingBreakEnd = filled($row['break_start'])
            && blank($row['break_end'])
            && $this->requiresBreakEnd($date, $schedule, $breakStartAt);

        $row['missing_break_end'] = $missingBreakEnd;
        $row['orphan_break_end'] = blank($row['break_start']) && filled($row['break_end']);
        $row['break_on_site'] = blank($row['break_start']) && blank($row['break_end']) && filled($row['clock_in']);

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

            // Jam kerja hanya dihitung jika absen pulang lengkap & tidak menunggu kembali istirahat.
            if ($clockInAt && $clockOutAt && $clockOutAt->greaterThan($clockInAt) && ! $missingBreakEnd) {
                $grossMinutes = (int) round(abs($clockInAt->diffInMinutes($clockOutAt)));

                if ($breakMinutes !== null) {
                    // Keluar istirahat (ada pasangan scan): potong min(aktual, jatah).
                    $breakForNet = min($breakMinutes, $allowedBreak);
                } else {
                    // Istirahat di kantor (tanpa scan): potong jatah jadwal.
                    $breakForNet = $allowedBreak;
                }

                $netMinutes = max(0, $grossMinutes - $breakForNet);
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
                $row['over_break_minutes'] = $breakMinutes - $allowedBreak;
            }
        }

        $missingClockOut = $this->requiresClockOut($date, $schedule)
            && filled($row['clock_in'])
            && blank($row['clock_out']);

        $row['missing_clock_out'] = $missingClockOut;

        // Tanpa absen pulang / tanpa absen kembali: jangan hitung jam kerja / lembur / jam kurang / over break spekulatif.
        if ($missingClockOut || $missingBreakEnd) {
            unset(
                $row['net_work_minutes'],
                $row['overtime_minutes'],
                $row['short_work_minutes'],
                $row['over_break_minutes'],
            );
            $row['is_short_work'] = false;
            $row['is_early_out'] = false;
            $row['is_over_break'] = false;
            unset($row['early_out_minutes']);
            $netMinutes = null;
        }

        if ($missingBreakEnd) {
            $row['status'] = 'Tidak absen kembali';
        } elseif ($missingClockOut) {
            $row['status'] = 'Tidak absen pulang';
        } else {
            $row['status'] = match ($latestType) {
                null => 'Off',
                'clock_in', 'break_end' => 'Bekerja',
                'break_start' => 'Istirahat',
                'clock_out' => 'Pulang',
                default => 'Off',
            };
        }

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
                'label' => 'Tidak absen pulang',
                'ok' => false,
                'display' => null,
                'negative' => false,
                'metric' => false,
            ];
        }

        if (! empty($row['missing_break_end'])) {
            $parts[] = [
                'label' => 'Tidak absen kembali',
                'ok' => false,
                'display' => null,
                'negative' => false,
                'metric' => false,
            ];
        }

        if (! empty($row['orphan_break_end'])) {
            $parts[] = [
                'label' => 'Kembali tanpa Istirahat',
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
            $issues[] = 'Tidak absen pulang (menunggu HRD)';
        }

        if (! empty($row['missing_break_end'])) {
            $issues[] = 'Tidak absen kembali (menunggu HRD)';
        }

        if (! empty($row['orphan_break_end'])) {
            $issues[] = 'Kembali tanpa absen Istirahat';
        }

        $hasAttendance = (bool) (($row['clock_in'] ?? null) || ($row['clock_out'] ?? null));

        return [
            'compliance_ok' => $hasAttendance && $issues === [],
            'compliance_issues' => $issues,
        ];
    }

    /**
     * Absen kembali wajib dicek setelah jatah istirahat habis,
     * atau setelah jam pulang jadwal / tanggal yang sudah lewat.
     */
    private function requiresBreakEnd(?string $date, ?WorkSchedule $schedule, ?Carbon $breakStartAt): bool
    {
        if (! $date || ! $breakStartAt) {
            return false;
        }

        $today = AppTimezone::nowDisplay()->toDateString();

        if ($date < $today) {
            return true;
        }

        if ($date > $today) {
            return false;
        }

        $allowed = (int) ($schedule?->break_duration_minutes ?? 0);
        $deadline = $breakStartAt->copy()->addMinutes(max(0, $allowed));
        $now = AppTimezone::nowDisplay();

        if ($now->greaterThanOrEqualTo($deadline)) {
            return true;
        }

        // Atau sudah lewat jam pulang kantor.
        return $this->requiresClockOut($date, $schedule);
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
