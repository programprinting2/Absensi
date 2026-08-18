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
}
