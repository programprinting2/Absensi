<?php

namespace App\Support;

use App\Models\WorkSchedule;

/**
 * Hasil resolusi jadwal karyawan pada satu tanggal.
 */
final class ResolvedShiftDay
{
    public const KIND_WORK = 'work';

    public const KIND_LIBUR_REQUEST = 'libur_request';

    public const KIND_LIBUR_KARYAWAN = 'libur_karyawan';

    public const KIND_LIBUR_HARI = 'libur_hari';

    public const KIND_LIBUR_EVENT = 'libur_event';

    public const KIND_UNSCHEDULED = 'unscheduled';

    public function __construct(
        public readonly string $kind,
        public readonly ?WorkSchedule $schedule = null,
        public readonly ?string $label = null,
        public readonly bool $isExcused = false,
        public readonly bool $isCompanyHoliday = false,
        public readonly ?int $workDurationOverride = null,
        public readonly ?int $breakDurationOverride = null,
        public readonly ?string $breakEarliestTimeOverride = null,
    ) {}

    public function isWorkDay(): bool
    {
        return $this->kind === self::KIND_WORK && $this->schedule !== null;
    }

    public function statusLabel(): string
    {
        return $this->label ?? match ($this->kind) {
            self::KIND_LIBUR_REQUEST => 'Libur request',
            self::KIND_LIBUR_KARYAWAN => 'Libur Rutin',
            self::KIND_LIBUR_HARI, self::KIND_LIBUR_EVENT => 'Libur',
            self::KIND_UNSCHEDULED => 'Jadwal belum diatur',
            default => $this->schedule?->name ?? 'Kerja',
        };
    }

    public function effectiveWorkDurationMinutes(): ?int
    {
        return $this->workDurationOverride
            ?? $this->schedule?->work_duration_minutes;
    }

    public function effectiveBreakDurationMinutes(): ?int
    {
        return $this->breakDurationOverride
            ?? $this->schedule?->break_duration_minutes;
    }

    public function effectiveBreakEarliestTime(): ?string
    {
        if ($this->breakEarliestTimeOverride !== null) {
            return $this->breakEarliestTimeOverride;
        }

        $scheduleTime = $this->schedule?->break_earliest_time;
        if ($scheduleTime === null) {
            return null;
        }

        return substr((string) $scheduleTime, 0, 5);
    }

    public function displayClockIn(): ?string
    {
        $clockIn = $this->schedule?->clock_in_time;
        if ($clockIn === null || $clockIn === '') {
            return null;
        }

        return substr((string) $clockIn, 0, 5);
    }

    public function displayClockOut(): ?string
    {
        if (! $this->schedule) {
            return null;
        }

        $clockIn = $this->displayClockIn();
        if ($clockIn === null) {
            return null;
        }

        if ($this->workDurationOverride !== null) {
            return self::calcClockOut(
                $clockIn,
                $this->workDurationOverride,
                $this->effectiveBreakDurationMinutes() ?? 0,
            );
        }

        $clockOut = $this->schedule->clock_out_time;
        if ($clockOut === null || $clockOut === '') {
            return null;
        }

        return substr((string) $clockOut, 0, 5);
    }

    public static function calcClockOut(string $clockIn, int $workMinutes, int $breakMinutes): string
    {
        [$h, $m] = array_map('intval', explode(':', $clockIn));
        $total = ($h * 60) + $m + max(0, $workMinutes) + max(0, $breakMinutes);
        $hours = intdiv($total, 60) % 24;
        $minutes = $total % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
