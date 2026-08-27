<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Support\AppTimezone;
use App\Support\DeviceLcdConfig;
use App\Support\ResolvedShiftDay;
use App\Models\WorkSchedule;
use Illuminate\Support\Carbon;

/**
 * Evaluasi indikator LCD ESP32 saat scan — selaras ShiftResolver + aturan laporan absen.
 */
class AttendanceEvaluateService
{
  public function evaluate(
    string $employeeId,
    string $attendanceType,
    Carbon $eventTime,
    ?Carbon $breakStartTime = null,
    ?array $lcdConfig = null,
  ): array {
    if (! Employee::query()->whereKey($employeeId)->exists()) {
      throw new \InvalidArgumentException('Karyawan tidak ditemukan.');
    }

    if (! in_array($attendanceType, ['clock_in', 'break_start', 'break_end', 'clock_out'], true)) {
      throw new \InvalidArgumentException('Tipe absensi tidak valid.');
    }

    $lcd = DeviceLcdConfig::mergeWithDefaults($lcdConfig);
    $mode = $lcd['modes'][$attendanceType] ?? DeviceLcdConfig::defaults()['modes'][$attendanceType];

    $guard = app(AttendanceScheduleGuard::class)->check($employeeId, $eventTime);

    if (! $guard['allowed']) {
      return [
        'allowed' => false,
        'level' => 'rejected',
        'bar_text' => $guard['reject_label'],
        'day_kind' => $guard['resolved']->kind,
        'schedule_name' => null,
        'is_work_day' => false,
      ];
    }

    $resolved = $guard['resolved'];
    $local = AppTimezone::toDisplay($eventTime);
    $eventMinutes = (int) $local->format('G') * 60 + (int) $local->format('i');

    $schedule = $resolved->schedule;
    $breakMinutes = $resolved->effectiveBreakDurationMinutes()
      ?? (int) ($schedule?->break_duration_minutes ?? 0);

    return match ($attendanceType) {
      'clock_in' => $this->evaluateClockIn($eventMinutes, $schedule, $mode, $resolved),
      'break_start' => $this->evaluateBreakStart($eventTime, $breakMinutes, $mode, $resolved),
      'break_end' => $this->evaluateBreakEnd(
        $employeeId,
        $eventTime,
        $breakMinutes,
        $breakStartTime,
        $mode,
        $resolved,
      ),
      'clock_out' => $this->evaluateClockOut($eventMinutes, $schedule, $mode, $resolved),
      default => $this->okResult($mode, $resolved),
    };
  }

  private function okResult(array $mode, ResolvedShiftDay $resolved, ?WorkSchedule $schedule = null): array
  {
    return [
      'allowed' => true,
      'level' => 'ok',
      'bar_text' => $mode['indicator_ok'],
      'day_kind' => $resolved->kind,
      'schedule_name' => $schedule?->name ?? $resolved->schedule?->name,
      'is_work_day' => $resolved->isWorkDay(),
    ];
  }

  private function evaluateClockIn(
    int $eventMinutes,
    ?WorkSchedule $schedule,
    array $mode,
    ResolvedShiftDay $resolved,
  ): array {
    if (! $schedule) {
      return $this->okResult($mode, $resolved);
    }

    $lateAfter = substr((string) ($schedule->late_after_time ?: $schedule->clock_in_time), 0, 5);
    $lateMinutes = $this->timeToMinutes($lateAfter);

    if ($this->isLateClockIn($eventMinutes, $lateMinutes, $schedule)) {
      $delta = $this->lateClockInDeltaMinutes($eventMinutes, $lateMinutes, $schedule);

      return [
        'allowed' => true,
        'level' => 'warning',
        'bar_text' => $mode['indicator_warn_prefix'].' '.$this->formatDuration($delta),
        'day_kind' => $resolved->kind,
        'schedule_name' => $schedule->name,
        'is_work_day' => true,
      ];
    }

    return $this->okResult($mode, $resolved, $schedule);
  }

  private function evaluateBreakStart(
    Carbon $eventTime,
    int $breakMinutes,
    array $mode,
    ResolvedShiftDay $resolved,
  ): array {
    $earliest = $resolved->effectiveBreakEarliestTime();
    if ($earliest !== null) {
      $local = AppTimezone::toDisplay($eventTime);
      $eventMinutes = (int) $local->format('G') * 60 + (int) $local->format('i');
      $earliestMinutes = $this->timeToMinutes($earliest);

      if ($this->isBeforeBreakEarliest($eventMinutes, $earliestMinutes, $resolved->schedule)) {
        return [
          'allowed' => false,
          'level' => 'rejected',
          'bar_text' => app(AttendanceScheduleGuard::class)->breakEarliestRejectLabel(),
          'day_kind' => $resolved->kind,
          'schedule_name' => $resolved->schedule?->name,
          'is_work_day' => true,
        ];
      }
    }

    if ($breakMinutes <= 0) {
      return $this->okResult($mode, $resolved, $resolved->schedule);
    }

    $deadline = AppTimezone::toDisplay($eventTime)->copy()->addMinutes($breakMinutes);

    return [
      'allowed' => true,
      'level' => 'info',
      'bar_text' => $mode['indicator_info_prefix'].' '.$deadline->format('H:i'),
      'day_kind' => $resolved->kind,
      'schedule_name' => $resolved->schedule?->name,
      'is_work_day' => true,
    ];
  }

  private function evaluateBreakEnd(
    string $employeeId,
    Carbon $eventTime,
    int $allowedBreakMinutes,
    ?Carbon $breakStartTime,
    array $mode,
    ResolvedShiftDay $resolved,
  ): array {
    $breakStart = $breakStartTime
      ?? $this->findBreakStartLog($employeeId, $eventTime);

    if (! $breakStart || $allowedBreakMinutes <= 0) {
      return $this->okResult($mode, $resolved, $resolved->schedule);
    }

    $breakStartLocal = AppTimezone::toDisplay($breakStart);
    $eventLocal = AppTimezone::toDisplay($eventTime);

    if ($breakStartLocal->toDateString() !== $eventLocal->toDateString()) {
      return $this->okResult($mode, $resolved, $resolved->schedule);
    }

    $elapsed = (int) round(abs($breakStartLocal->diffInMinutes($eventLocal)));

    if ($elapsed > $allowedBreakMinutes) {
      return [
        'allowed' => true,
        'level' => 'warning',
        'bar_text' => $mode['indicator_warn_prefix'].' '.$this->formatDuration($elapsed - $allowedBreakMinutes),
        'day_kind' => $resolved->kind,
        'schedule_name' => $resolved->schedule?->name,
        'is_work_day' => true,
      ];
    }

    return $this->okResult($mode, $resolved, $resolved->schedule);
  }

  private function evaluateClockOut(
    int $eventMinutes,
    ?WorkSchedule $schedule,
    array $mode,
    ResolvedShiftDay $resolved,
  ): array {
    if (! $schedule) {
      return $this->okResult($mode, $resolved);
    }

    $clockOutMinutes = $this->timeToMinutes(substr((string) $schedule->clock_out_time, 0, 5));

    if ($this->isEarlyClockOut($eventMinutes, $schedule)) {
      $early = $this->earlyClockOutDeltaMinutes($eventMinutes, $clockOutMinutes, $schedule);

      return [
        'allowed' => true,
        'level' => 'warning',
        'bar_text' => $mode['indicator_warn_prefix'].' '.$this->formatDuration($early),
        'day_kind' => $resolved->kind,
        'schedule_name' => $schedule->name,
        'is_work_day' => true,
      ];
    }

    return $this->okResult($mode, $resolved, $schedule);
  }

    private function findBreakStartLog(string $employeeId, Carbon $eventTime): ?Carbon
    {
        $local = AppTimezone::toDisplay($eventTime);
        [$startUtc, $endUtc] = AppTimezone::dayBoundsUtc($local->toDateString());

        $log = AttendanceLog::query()
            ->where('employee_id', $employeeId)
            ->where('attendance_type', 'break_start')
            ->whereBetween('event_time', [$startUtc, $endUtc])
            ->orderBy('event_time')
            ->first();

        return $log ? AppTimezone::toDisplay($log->event_time) : null;
    }

  private function isLateClockIn(int $eventMinutes, int $lateAfterMinutes, WorkSchedule $schedule): bool
  {
    if (! $schedule->crosses_midnight) {
      return $eventMinutes > $lateAfterMinutes;
    }

    $clockInMinutes = $this->timeToMinutes(substr((string) $schedule->clock_in_time, 0, 5));
    $clockOutMinutes = $this->timeToMinutes(substr((string) $schedule->clock_out_time, 0, 5));

    if ($eventMinutes < $clockInMinutes) {
      return false;
    }

    if ($eventMinutes >= $lateAfterMinutes) {
      return true;
    }

    if ($eventMinutes < $clockOutMinutes) {
      return $eventMinutes > $lateAfterMinutes;
    }

    return false;
  }

  private function lateClockInDeltaMinutes(int $eventMinutes, int $lateAfterMinutes, WorkSchedule $schedule): int
  {
    if (! $schedule->crosses_midnight) {
      return max(0, $eventMinutes - $lateAfterMinutes);
    }

    $clockInMinutes = $this->timeToMinutes(substr((string) $schedule->clock_in_time, 0, 5));

    if ($eventMinutes >= $clockInMinutes) {
      return max(0, $eventMinutes - $lateAfterMinutes);
    }

    return max(0, $eventMinutes - $lateAfterMinutes);
  }

  private function isEarlyClockOut(int $eventMinutes, WorkSchedule $schedule): bool
  {
    $clockOutMinutes = $this->timeToMinutes(substr((string) $schedule->clock_out_time, 0, 5));

    if (! $schedule->crosses_midnight) {
      return $eventMinutes < $clockOutMinutes;
    }

    $clockInMinutes = $this->timeToMinutes(substr((string) $schedule->clock_in_time, 0, 5));

    if ($eventMinutes < $clockOutMinutes) {
      return true;
    }

    if ($eventMinutes >= $clockInMinutes) {
      return true;
    }

    return false;
  }

  private function earlyClockOutDeltaMinutes(
    int $eventMinutes,
    int $clockOutMinutes,
    WorkSchedule $schedule,
  ): int {
    if (! $schedule->crosses_midnight) {
      return max(0, $clockOutMinutes - $eventMinutes);
    }

    $clockInMinutes = $this->timeToMinutes(substr((string) $schedule->clock_in_time, 0, 5));

    if ($eventMinutes >= $clockInMinutes) {
      return max(0, (24 * 60 - $eventMinutes) + $clockOutMinutes);
    }

    return max(0, $clockOutMinutes - $eventMinutes);
  }

  private function isBeforeBreakEarliest(int $eventMinutes, int $earliestMinutes, ?WorkSchedule $schedule): bool
  {
    if (! $schedule || ! $schedule->crosses_midnight) {
      return $eventMinutes < $earliestMinutes;
    }

    $clockInMinutes = $this->timeToMinutes(substr((string) $schedule->clock_in_time, 0, 5));
    $clockOutMinutes = $this->timeToMinutes(substr((string) $schedule->clock_out_time, 0, 5));

    if ($earliestMinutes < $clockInMinutes) {
      if ($eventMinutes >= $clockInMinutes) {
        return true;
      }

      if ($eventMinutes < $clockOutMinutes) {
        return $eventMinutes < $earliestMinutes;
      }

      return false;
    }

    return $eventMinutes < $earliestMinutes;
  }

  private function timeToMinutes(string $time): int
  {
    [$hour, $minute] = array_map('intval', explode(':', $time));

    return $hour * 60 + $minute;
  }

  private function formatDuration(int $minutes): string
  {
    $minutes = max(0, $minutes);
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
