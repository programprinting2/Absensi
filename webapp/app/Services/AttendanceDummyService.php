<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Support\AppTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Dev helper: isi / hapus absensi dummy untuk karyawan yang sudah ada.
 * Log ditandai raw_notes = "DEV_DUMMY".
 *
 * Rentang acak: masuk 08:30–09:30, istirahat 12:00–14:00, pulang 17:00–18:00
 * (wall-clock timezone tampilan bisnis), disimpan sebagai UTC sejati.
 */
class AttendanceDummyService
{
    public const NOTES_MARKER = 'DEV_DUMMY';

    private const ABSENT_CHANCE = 8;

    /** Peluang (%) satu hari jadi Not OK (sisanya diusahakan OK). */
    private const NOT_OK_CHANCE = 25;

    private const WINDOW_IN_START = 8 * 60 + 30;
    private const WINDOW_IN_END = 9 * 60 + 30;
    private const WINDOW_BREAK_START = 12 * 60;
    private const WINDOW_BREAK_END = 14 * 60;
    private const WINDOW_OUT_START = 17 * 60;
    private const WINDOW_OUT_END = 18 * 60;

    /**
     * @return array{created_logs: int, employees: int, days: int, skipped_days: int}
     */
    public function createForRange(Carbon $start, Carbon $end, ?string $employeeId = null): array
    {
        $device = Device::where('is_active', true)->first()
            ?? Device::query()->first()
            ?? Device::create([
                'device_code' => 'DEV-DUMMY',
                'name' => 'Perangkat Dummy',
                'location' => 'Development',
                'is_active' => true,
            ]);

        $employees = Employee::query()
            ->where('is_active', true)
            ->when($employeeId, fn ($q) => $q->where('id', $employeeId))
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        $now = AppTimezone::nowDisplay();
        $today = $now->copy()->startOfDay();

        $rangeStart = AppTimezone::toDisplay($start)->startOfDay();
        $rangeEnd = AppTimezone::toDisplay($end)->endOfDay();

        $workDays = collect();
        for ($date = $rangeStart->copy()->startOfDay(); $date->lte($rangeEnd); $date->addDay()) {
            if ($date->isSunday()) {
                continue;
            }
            if ($date->gt($today)) {
                continue;
            }
            $workDays->push($date->copy()->startOfDay());
        }

        $resolver = app(ShiftResolver::class);
        $createdLogs = 0;
        $skippedDays = 0;

        foreach ($employees as $employee) {
            foreach ($workDays as $date) {
                [$dayStart, $dayEnd] = AppTimezone::dayBoundsUtc($date);

                $hasReal = AttendanceLog::query()
                    ->where('employee_id', $employee->id)
                    ->whereBetween('event_time', [$dayStart, $dayEnd])
                    ->where(function ($q) {
                        $q->whereNull('raw_notes')
                            ->orWhere('raw_notes', '!=', self::NOTES_MARKER);
                    })
                    ->exists();

                if ($hasReal) {
                    $skippedDays++;
                    continue;
                }

                AttendanceLog::query()
                    ->where('employee_id', $employee->id)
                    ->whereBetween('event_time', [$dayStart, $dayEnd])
                    ->where('raw_notes', self::NOTES_MARKER)
                    ->delete();

                $schedule = $resolver->forEmployeeOnDate($employee, $date);
                $createdLogs += $this->seedDay($device, $employee, $date, $now, $schedule);
            }
        }

        return [
            'created_logs' => $createdLogs,
            'employees' => $employees->count(),
            'days' => $workDays->count(),
            'skipped_days' => $skippedDays,
        ];
    }

    public function clearForRange(Carbon $start, Carbon $end, ?string $employeeId = null): int
    {
        $rangeStart = AppTimezone::toDisplay($start)->startOfDay()->utc();
        $rangeEnd = AppTimezone::toDisplay($end)->endOfDay()->utc();

        return AttendanceLog::query()
            ->where('raw_notes', self::NOTES_MARKER)
            ->whereBetween('event_time', [$rangeStart, $rangeEnd])
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->delete();
    }

    private function seedDay(
        Device $device,
        Employee $employee,
        Carbon $dateJakarta,
        Carbon $nowJakarta,
        ?WorkSchedule $schedule,
    ): int {
        $isToday = $dateJakarta->isSameDay($nowJakarta);

        if ($this->chance(self::ABSENT_CHANCE)) {
            return 0;
        }

        $wantOk = ! $this->chance(self::NOT_OK_CHANCE);
        $plan = $wantOk
            ? $this->planOkTimes($schedule)
            : $this->planNotOkTimes($schedule);

        if ($plan === null) {
            $plan = $this->planNotOkTimes($schedule) ?? $this->planFallbackTimes();
        }

        $created = 0;
        foreach ($plan as $type => $totalMinutes) {
            [$hour, $minute] = [intdiv($totalMinutes, 60), $totalMinutes % 60];
            $eventTime = AppTimezone::wallToUtc(
                $dateJakarta->year,
                $dateJakarta->month,
                $dateJakarta->day,
                $hour,
                $minute,
            );

            if ($isToday && $eventTime->greaterThan($nowJakarta->copy()->utc())) {
                continue;
            }

            AttendanceLog::create([
                'device_id' => $device->id,
                'employee_id' => $employee->id,
                'attendance_type' => $type,
                'method' => 'fingerprint',
                'event_time' => $eventTime,
                'client_uuid' => (string) Str::uuid(),
                'raw_notes' => self::NOTES_MARKER,
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Rencana jam yang lolos aturan jadwal, tetap dalam jendela yang diminta.
     *
     * @return array{clock_in: int, break_start: int, break_end: int, clock_out: int}|null
     */
    private function planOkTimes(?WorkSchedule $schedule): ?array
    {
        $lateAfter = $this->scheduleMinutes($schedule?->late_after_time, $schedule?->clock_in_time) ?? self::WINDOW_IN_END;
        $breakLimit = max(15, (int) ($schedule?->break_duration_minutes ?? 60));
        $workRequired = max(60, (int) ($schedule?->work_duration_minutes ?? 480));
        $scheduleOut = $this->scheduleMinutes($schedule?->clock_out_time) ?? self::WINDOW_OUT_START;

        // OK: masuk tidak lewat late_after, dan masih dalam 08:30–09:30.
        $inMax = min(self::WINDOW_IN_END, $lateAfter);
        if ($inMax < self::WINDOW_IN_START) {
            return null;
        }

        $breakDuration = random_int(max(15, (int) floor($breakLimit * 0.5)), $breakLimit);

        // Out harus >= jadwal pulang, menutupi jam kerja efektif, dan ≤ 18:00.
        $inMinForOutWindow = self::WINDOW_OUT_END - $workRequired - $breakDuration;
        $clockIn = random_int(self::WINDOW_IN_START, min($inMax, max(self::WINDOW_IN_START, $inMinForOutWindow)));

        $earliestOut = max($scheduleOut, $clockIn + $workRequired + $breakDuration, self::WINDOW_OUT_START);
        if ($earliestOut > self::WINDOW_OUT_END) {
            // Longgarkan dengan istirahat lebih pendek.
            $breakDuration = min($breakDuration, max(15, self::WINDOW_OUT_END - $scheduleOut - $workRequired));
            $earliestOut = max($scheduleOut, $clockIn + $workRequired + $breakDuration, self::WINDOW_OUT_START);
        }

        if ($earliestOut > self::WINDOW_OUT_END || $earliestOut < self::WINDOW_OUT_START) {
            return null;
        }

        $clockOut = random_int($earliestOut, self::WINDOW_OUT_END);

        $breakStartMax = self::WINDOW_BREAK_END - $breakDuration;
        if ($breakStartMax < self::WINDOW_BREAK_START) {
            return null;
        }
        $breakStart = random_int(self::WINDOW_BREAK_START, $breakStartMax);
        $breakEnd = $breakStart + $breakDuration;

        return [
            'clock_in' => $clockIn,
            'break_start' => $breakStart,
            'break_end' => $breakEnd,
            'clock_out' => $clockOut,
        ];
    }

    /**
     * Rencana jam masih dalam jendela user, tapi sengaja melanggar aturan.
     *
     * @return array{clock_in: int, break_start: int, break_end: int, clock_out: int}|null
     */
    private function planNotOkTimes(?WorkSchedule $schedule): ?array
    {
        $lateAfter = $this->scheduleMinutes($schedule?->late_after_time, $schedule?->clock_in_time) ?? (8 * 60 + 46);
        $breakLimit = max(15, (int) ($schedule?->break_duration_minutes ?? 60));
        $scheduleOut = $this->scheduleMinutes($schedule?->clock_out_time) ?? self::WINDOW_OUT_START;

        $mode = random_int(1, 3);

        // 1) Terlambat masuk (setelah late_after), tetap ≤ 09:30.
        if ($mode === 1 && $lateAfter < self::WINDOW_IN_END) {
            $clockIn = random_int(max(self::WINDOW_IN_START, $lateAfter + 1), self::WINDOW_IN_END);
            $breakDuration = random_int(30, $breakLimit);
            $breakStart = random_int(self::WINDOW_BREAK_START, self::WINDOW_BREAK_END - $breakDuration);
            $clockOut = random_int(max(self::WINDOW_OUT_START, $scheduleOut), self::WINDOW_OUT_END);

            return [
                'clock_in' => $clockIn,
                'break_start' => $breakStart,
                'break_end' => $breakStart + $breakDuration,
                'clock_out' => $clockOut,
            ];
        }

        // 2) Over break di jendela 12–14.
        if ($mode === 2) {
            $clockIn = random_int(self::WINDOW_IN_START, self::WINDOW_IN_END);
            $breakDuration = random_int($breakLimit + 1, min(90, self::WINDOW_BREAK_END - self::WINDOW_BREAK_START));
            $breakStartMax = self::WINDOW_BREAK_END - $breakDuration;
            if ($breakStartMax < self::WINDOW_BREAK_START) {
                $breakDuration = self::WINDOW_BREAK_END - self::WINDOW_BREAK_START;
                $breakStart = self::WINDOW_BREAK_START;
            } else {
                $breakStart = random_int(self::WINDOW_BREAK_START, $breakStartMax);
            }
            $clockOut = random_int(self::WINDOW_OUT_START, self::WINDOW_OUT_END);

            return [
                'clock_in' => $clockIn,
                'break_start' => $breakStart,
                'break_end' => $breakStart + $breakDuration,
                'clock_out' => $clockOut,
            ];
        }

        // 3) Pulang lebih awal dari jadwal (jika jadwal > 17:00; kalau jadwal = 17:00, pakai terlambat).
        $clockIn = random_int(self::WINDOW_IN_START, self::WINDOW_IN_END);
        $breakDuration = random_int(30, $breakLimit);
        $breakStart = random_int(self::WINDOW_BREAK_START, self::WINDOW_BREAK_END - $breakDuration);

        if ($scheduleOut > self::WINDOW_OUT_START) {
            $clockOut = random_int(self::WINDOW_OUT_START, $scheduleOut - 1);
        } else {
            // Jadwal 17:00: tidak bisa pulang lebih awal di jendela; buat terlambat + over break.
            $clockIn = random_int(max(self::WINDOW_IN_START, $lateAfter + 1), self::WINDOW_IN_END);
            $breakDuration = random_int($breakLimit + 5, min(90, 100));
            $breakStart = random_int(self::WINDOW_BREAK_START, max(self::WINDOW_BREAK_START, self::WINDOW_BREAK_END - $breakDuration));
            $clockOut = random_int(self::WINDOW_OUT_START, self::WINDOW_OUT_END);
        }

        return [
            'clock_in' => $clockIn,
            'break_start' => $breakStart,
            'break_end' => min(self::WINDOW_BREAK_END, $breakStart + $breakDuration),
            'clock_out' => $clockOut,
        ];
    }

    /**
     * @return array{clock_in: int, break_start: int, break_end: int, clock_out: int}
     */
    private function planFallbackTimes(): array
    {
        return [
            'clock_in' => random_int(self::WINDOW_IN_START, self::WINDOW_IN_END),
            'break_start' => 12 * 60,
            'break_end' => 13 * 60,
            'clock_out' => random_int(self::WINDOW_OUT_START, self::WINDOW_OUT_END),
        ];
    }

    private function scheduleMinutes(?string $primary, ?string $fallback = null): ?int
    {
        $value = $primary ?: $fallback;
        if (! filled($value)) {
            return null;
        }

        $parts = explode(':', substr((string) $value, 0, 5));

        return ((int) $parts[0]) * 60 + (int) ($parts[1] ?? 0);
    }

    private function chance(int $percent): bool
    {
        return random_int(1, 100) <= $percent;
    }
}
