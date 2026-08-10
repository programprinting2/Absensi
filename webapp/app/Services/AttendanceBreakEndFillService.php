<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Employee;
use App\Support\AppTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class AttendanceBreakEndFillService
{
    /**
     * Rekan se-departemen yang sudah absen kembali pada tanggal tersebut.
     *
     * @return list<array{id: string, full_name: string, employee_code: mixed, break_end: string}>
     */
    public function peersWithBreakEnd(Employee|string $employee, string $date): array
    {
        $employee = $employee instanceof Employee ? $employee : Employee::findOrFail($employee);
        $department = trim((string) ($employee->department ?? ''));

        if ($department === '') {
            return [];
        }

        [$dayStart, $dayEnd] = AppTimezone::dayBoundsUtc($date);

        $peerIds = Employee::query()
            ->where('is_active', true)
            ->where('id', '!=', $employee->id)
            ->whereRaw('LOWER(TRIM(department)) = ?', [mb_strtolower($department)])
            ->pluck('id');

        if ($peerIds->isEmpty()) {
            return [];
        }

        $resolver = app(ShiftResolver::class);
        $myScheduleId = $resolver->forEmployeeOnDate($employee, $date)?->id;
        $scheduleMap = $resolver->scheduleIdsForEmployeesOnDate($peerIds, $date);
        $peerIds = $peerIds->filter(
            fn ($id) => ($scheduleMap[(string) $id] ?? null) === $myScheduleId
        )->values();

        if ($peerIds->isEmpty()) {
            return [];
        }

        $logs = AttendanceLog::query()
            ->whereIn('employee_id', $peerIds)
            ->where('attendance_type', 'break_end')
            ->whereBetween('event_time', [$dayStart, $dayEnd])
            ->orderBy('event_time')
            ->get(['employee_id', 'event_time']);

        $firstByEmployee = [];
        foreach ($logs as $log) {
            if (isset($firstByEmployee[$log->employee_id])) {
                continue;
            }
            $firstByEmployee[$log->employee_id] = AppTimezone::toDisplay($log->event_time)->format('H:i');
        }

        if ($firstByEmployee === []) {
            return [];
        }

        return Employee::query()
            ->whereIn('id', array_keys($firstByEmployee))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code'])
            ->map(fn (Employee $peer) => [
                'id' => $peer->id,
                'full_name' => $peer->full_name,
                'employee_code' => $peer->employee_code,
                'break_end' => $firstByEmployee[$peer->id],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{time: string, note: string, preview_label: string}
     */
    public function resolveFillTime(
        Employee $employee,
        string $date,
        string $mode,
        ?string $peerEmployeeId = null,
    ): array {
        [$dayStart, $dayEnd] = AppTimezone::dayBoundsUtc($date);

        $breakStart = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('attendance_type', 'break_start')
            ->whereBetween('event_time', [$dayStart, $dayEnd])
            ->orderBy('event_time')
            ->first();

        if (! $breakStart) {
            throw new RuntimeException('Belum ada absen Istirahat pada tanggal ini.');
        }

        $breakStartLocal = AppTimezone::toDisplay($breakStart->event_time);

        if ($mode === 'allowance') {
            $schedule = app(ShiftResolver::class)->forEmployeeOnDate($employee, $date);
            $allowed = (int) ($schedule?->break_duration_minutes ?? 0);
            $end = $breakStartLocal->copy()->addMinutes(max(0, $allowed));
            $time = $end->format('H:i');
            $shiftLabel = $schedule?->name ? ' ('.$schedule->name.')' : '';

            return [
                'time' => $time,
                'note' => 'HRD isi: kembali sesuai jatah istirahat'.$shiftLabel.' (+'.$allowed.' m → '.$time.')',
                'preview_label' => $breakStartLocal->format('H:i').' + '.$allowed.' menit = '.$time,
            ];
        }

        if ($mode === 'peer') {
            if (! filled($peerEmployeeId)) {
                throw new RuntimeException('Pilih karyawan se-divisi yang jam kembalinya akan disamakan.');
            }

            $peers = collect($this->peersWithBreakEnd($employee, $date))->keyBy('id');
            $peer = $peers->get($peerEmployeeId);
            if (! $peer) {
                throw new RuntimeException('Karyawan acuan tidak valid atau belum absen kembali di hari yang sama / beda divisi.');
            }

            return [
                'time' => $peer['break_end'],
                'note' => 'HRD isi: samakan kembali dengan '.$peer['full_name'].' ('.$peer['break_end'].')',
                'preview_label' => $peer['full_name'].' · '.$peer['break_end'],
            ];
        }

        throw new RuntimeException('Mode pengisian tidak dikenal.');
    }

    public function fill(
        Employee|string $employee,
        string $date,
        string $mode,
        ?string $peerEmployeeId = null,
        ?string $actorLabel = null,
    ): AttendanceLog {
        $employee = $employee instanceof Employee ? $employee : Employee::findOrFail($employee);
        [$dayStart, $dayEnd] = AppTimezone::dayBoundsUtc($date);

        $existingEnd = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('attendance_type', 'break_end')
            ->whereBetween('event_time', [$dayStart, $dayEnd])
            ->orderBy('event_time')
            ->first();

        if ($existingEnd) {
            throw new RuntimeException('Sudah ada absen kembali. Edit jam kembali secara manual jika perlu diubah.');
        }

        $resolved = $this->resolveFillTime($employee, $date, $mode, $peerEmployeeId);
        [$hour, $minute] = array_map('intval', explode(':', $resolved['time']));

        $day = Carbon::createFromFormat('Y-m-d', $date, AppTimezone::display());
        $eventTime = AppTimezone::wallToUtc($day->year, $day->month, $day->day, $hour, $minute);

        $deviceId = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('event_time', [$dayStart, $dayEnd])
            ->value('device_id')
            ?? Device::where('is_active', true)->value('id')
            ?? Device::query()->value('id');

        if (! $deviceId) {
            throw new RuntimeException('Tidak ada perangkat. Tidak bisa menambah jam kembali.');
        }

        $note = $resolved['note'];
        if (filled($actorLabel)) {
            $note .= ' · oleh '.$actorLabel;
        }

        return AttendanceLog::create([
            'device_id' => $deviceId,
            'employee_id' => $employee->id,
            'attendance_type' => 'break_end',
            'method' => 'manual',
            'event_time' => $eventTime,
            'client_uuid' => (string) Str::uuid(),
            'raw_notes' => $note,
            'synced_at' => now(),
            'is_offline_capture' => false,
        ]);
    }

    /**
     * Preview label untuk UI (jatah istirahat).
     */
    public function allowancePreview(Employee|string $employee, string $date): ?string
    {
        try {
            $employee = $employee instanceof Employee ? $employee : Employee::findOrFail($employee);

            return $this->resolveFillTime($employee, $date, 'allowance')['preview_label'];
        } catch (RuntimeException) {
            return null;
        }
    }
}
