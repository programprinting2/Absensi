<?php

namespace App\Services;

use App\Support\AppTimezone;
use App\Support\ResolvedShiftDay;
use Illuminate\Support\Carbon;

class AttendanceScheduleGuard
{
    public function check(string $employeeId, Carbon $eventTime): array
    {
        $local = AppTimezone::toDisplay($eventTime);
        $resolved = app(ShiftResolver::class)->resolveDay($employeeId, $local->toDateString());

        if ($resolved->isWorkDay()) {
            return [
                'allowed' => true,
                'resolved' => $resolved,
                'reject_label' => null,
            ];
        }

        return [
            'allowed' => false,
            'resolved' => $resolved,
            'reject_label' => $this->rejectLabel($resolved),
        ];
    }

    public function rejectLabel(ResolvedShiftDay $resolved): string
    {
        return match ($resolved->kind) {
            ResolvedShiftDay::KIND_UNSCHEDULED => 'TIDAK ADA JADWAL',
            ResolvedShiftDay::KIND_LIBUR_KARYAWAN => 'LIBUR RUTIN',
            ResolvedShiftDay::KIND_LIBUR_REQUEST => 'LIBUR REQUEST',
            ResolvedShiftDay::KIND_LIBUR_HARI, ResolvedShiftDay::KIND_LIBUR_EVENT => 'LIBUR',
            default => 'TIDAK ADA JADWAL',
        };
    }
}
