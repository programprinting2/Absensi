<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Support\AppTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class AttendanceClockOutFillService
{
    /**
     * Rekan se-departemen yang sudah absen pulang pada tanggal tersebut.
     *
     * @return list<array{id: string, full_name: string, employee_code: mixed, clock_out: string}>
     */
    public function peersWithClockOut(Employee|string $employee, string $date): array
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

        $logs = AttendanceLog::query()
            ->whereIn('employee_id', $peerIds)
            ->where('attendance_type', 'clock_out')
            ->whereBetween('event_time', [$dayStart, $dayEnd])
            ->orderBy('event_time')
            ->get(['employee_id', 'event_time']);

        $firstOutByEmployee = [];
        foreach ($logs as $log) {
            if (isset($firstOutByEmployee[$log->employee_id])) {
                continue;
            }
            $firstOutByEmployee[$log->employee_id] = AppTimezone::toDisplay($log->event_time)->format('H:i');
        }

        if ($firstOutByEmployee === []) {
            return [];
        }

        return Employee::query()
            ->whereIn('id', array_keys($firstOutByEmployee))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code'])
            ->map(fn (Employee $peer) => [
                'id' => $peer->id,
                'full_name' => $peer->full_name,
                'employee_code' => $peer->employee_code,
                'clock_out' => $firstOutByEmployee[$peer->id],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{time: string, note: string}
     */
    public function resolveFillTime(
        Employee $employee,
        string $date,
        string $mode,
        ?string $peerEmployeeId = null,
    ): array {
        if ($mode === 'schedule') {
            $schedule = WorkSchedule::active();
            $time = substr((string) ($schedule?->clock_out_time ?: '17:00'), 0, 5);

            return [
                'time' => $time,
                'note' => 'HRD isi: jam pulang kantor ('.$time.')',
            ];
        }

        if ($mode === 'peer') {
            if (! filled($peerEmployeeId)) {
                throw new RuntimeException('Pilih karyawan se-divisi yang jam pulangnya akan disamakan.');
            }

            $peers = collect($this->peersWithClockOut($employee, $date))->keyBy('id');
            $peer = $peers->get($peerEmployeeId);
            if (! $peer) {
                throw new RuntimeException('Karyawan acuan tidak valid atau belum absen pulang di hari yang sama / beda divisi.');
            }

            return [
                'time' => $peer['clock_out'],
                'note' => 'HRD isi: samakan dengan '.$peer['full_name'].' ('.$peer['clock_out'].')',
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

        $hasClockIn = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('attendance_type', 'clock_in')
            ->whereBetween('event_time', [$dayStart, $dayEnd])
            ->exists();

        if (! $hasClockIn) {
            throw new RuntimeException('Karyawan belum absen masuk pada tanggal ini.');
        }

        $existingOut = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('attendance_type', 'clock_out')
            ->whereBetween('event_time', [$dayStart, $dayEnd])
            ->orderBy('event_time')
            ->first();

        if ($existingOut) {
            throw new RuntimeException('Sudah ada absen pulang. Edit jam pulang secara manual jika perlu diubah.');
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
            throw new RuntimeException('Tidak ada perangkat. Tidak bisa menambah jam pulang.');
        }

        $note = $resolved['note'];
        if (filled($actorLabel)) {
            $note .= ' · oleh '.$actorLabel;
        }

        return AttendanceLog::create([
            'device_id' => $deviceId,
            'employee_id' => $employee->id,
            'attendance_type' => 'clock_out',
            'method' => 'manual',
            'event_time' => $eventTime,
            'client_uuid' => (string) Str::uuid(),
            'raw_notes' => $note,
            'synced_at' => now(),
            'is_offline_capture' => false,
        ]);
    }
}
