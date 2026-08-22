<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\ShiftCalendarEntry;
use App\Models\ShiftDaySetting;
use App\Models\ShiftEmployeeLibur;
use App\Models\ShiftEmployeeShiftOverride;
use App\Models\WorkSchedule;
use App\Support\AppTimezone;
use App\Support\ResolvedShiftDay;
use Illuminate\Support\Carbon;

class ShiftResolver
{
    /** @var array<string, ResolvedShiftDay> */
    private array $dayCache = [];

    /** @var array<string, ?WorkSchedule> */
    private array $scheduleCache = [];

    public function forgetCache(): void
    {
        $this->dayCache = [];
        $this->scheduleCache = [];
    }

    /**
     * Resolusi lengkap hari kerja karyawan.
     *
     * Urutan: libur request → libur karyawan → libur perusahaan (hari/event)
     * → tukar sif override → roster kalender (karyawan/group) → jadwal belum diatur.
     */
    public function resolveDay(Employee|string $employee, Carbon|string $date): ResolvedShiftDay
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $day = $this->toDateString($date);
        $cacheKey = $employeeId.'|'.$day;

        if (isset($this->dayCache[$cacheKey])) {
            return $this->dayCache[$cacheKey];
        }

        $daySetting = ShiftDaySetting::query()->whereDate('work_date', $day)->first();
        $workOverride = $daySetting?->work_duration_minutes;
        $breakOverride = $daySetting?->break_duration_minutes;
        $breakEarliestOverride = $this->formatBreakEarliestTime($daySetting?->break_earliest_time);

        // 1) Libur request (cuti/sakit/izin approved)
        $leaveMap = app(LeaveService::class)->approvedLeavesByEmployeeDate(
            [$employeeId],
            Carbon::parse($day, AppTimezone::display()),
            Carbon::parse($day, AppTimezone::display()),
        );
        if (! empty($leaveMap[$employeeId][$day])) {
            return $this->dayCache[$cacheKey] = new ResolvedShiftDay(
                kind: ResolvedShiftDay::KIND_LIBUR_REQUEST,
                label: 'Libur request',
                isExcused: true,
                workDurationOverride: $workOverride,
                breakDurationOverride: $breakOverride,
                breakEarliestTimeOverride: $breakEarliestOverride,
            );
        }

        // 2) Libur rutin karyawan — jatah admin di pola
        $hasLibur = ShiftEmployeeLibur::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $day)
            ->exists();
        if ($hasLibur) {
            return $this->dayCache[$cacheKey] = new ResolvedShiftDay(
                kind: ResolvedShiftDay::KIND_LIBUR_KARYAWAN,
                label: 'Libur Rutin',
                isExcused: true,
                workDurationOverride: $workOverride,
                breakDurationOverride: $breakOverride,
                breakEarliestTimeOverride: $breakEarliestOverride,
            );
        }

        // 3) Libur perusahaan (rutin / event)
        if ($daySetting?->is_company_holiday) {
            $kind = $daySetting->holiday_kind === ShiftDaySetting::HOLIDAY_EVENT
                ? ResolvedShiftDay::KIND_LIBUR_EVENT
                : ResolvedShiftDay::KIND_LIBUR_HARI;

            return $this->dayCache[$cacheKey] = new ResolvedShiftDay(
                kind: $kind,
                label: 'Libur',
                isExcused: true,
                isCompanyHoliday: true,
                workDurationOverride: $workOverride,
                breakDurationOverride: $breakOverride,
                breakEarliestTimeOverride: $breakEarliestOverride,
            );
        }

        // 4) Tukar sif override
        $shiftOverride = ShiftEmployeeShiftOverride::query()
            ->with('schedule')
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $day)
            ->first();
        if ($shiftOverride?->schedule && $this->scheduleAppliesOnDate($shiftOverride->schedule, $day)) {
            return $this->dayCache[$cacheKey] = new ResolvedShiftDay(
                kind: ResolvedShiftDay::KIND_WORK,
                schedule: $this->withDayOverrides($shiftOverride->schedule, $workOverride, $breakOverride, $breakEarliestOverride),
                label: $shiftOverride->schedule->name,
                workDurationOverride: $workOverride,
                breakDurationOverride: $breakOverride,
                breakEarliestTimeOverride: $breakEarliestOverride,
            );
        }

        // 5) Roster langsung per karyawan (tanpa group)
        $directEntry = ShiftCalendarEntry::query()
            ->with('schedule')
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $day)
            ->orderBy('sort_order')
            ->first();
        if ($directEntry?->schedule && $this->scheduleAppliesOnDate($directEntry->schedule, $day)) {
            return $this->dayCache[$cacheKey] = new ResolvedShiftDay(
                kind: ResolvedShiftDay::KIND_WORK,
                schedule: $this->withDayOverrides($directEntry->schedule, $workOverride, $breakOverride, $breakEarliestOverride),
                label: $directEntry->schedule->name,
                workDurationOverride: $workOverride,
                breakDurationOverride: $breakOverride,
                breakEarliestTimeOverride: $breakEarliestOverride,
            );
        }

        // 6) Roster: group membership → calendar entry
        $group = app(ShiftGroupService::class)->groupForEmployeeOnDate($employeeId, $day);
        if ($group && ! $group->is_system_unassigned) {
            $entry = ShiftCalendarEntry::query()
                ->with('schedule')
                ->where('group_id', $group->id)
                ->whereDate('work_date', $day)
                ->orderBy('sort_order')
                ->first();

            if ($entry?->schedule && $this->scheduleAppliesOnDate($entry->schedule, $day)) {
                return $this->dayCache[$cacheKey] = new ResolvedShiftDay(
                    kind: ResolvedShiftDay::KIND_WORK,
                    schedule: $this->withDayOverrides($entry->schedule, $workOverride, $breakOverride, $breakEarliestOverride),
                    label: $entry->schedule->name,
                    workDurationOverride: $workOverride,
                    breakDurationOverride: $breakOverride,
                    breakEarliestTimeOverride: $breakEarliestOverride,
                );
            }
        }

        return $this->dayCache[$cacheKey] = new ResolvedShiftDay(
            kind: ResolvedShiftDay::KIND_UNSCHEDULED,
            label: 'Jadwal belum diatur',
            isExcused: true,
        );
    }

    /**
     * Jadwal karyawan pada tanggal (kompatibel konsumen lama).
     * Mengembalikan WorkSchedule jika hari kerja; null jika libur/unscheduled.
     */
    public function forEmployeeOnDate(Employee|string $employee, Carbon|string $date): ?WorkSchedule
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $day = $this->toDateString($date);
        $cacheKey = 'sched|'.$employeeId.'|'.$day;

        if (array_key_exists($cacheKey, $this->scheduleCache)) {
            return $this->scheduleCache[$cacheKey];
        }

        $resolved = $this->resolveDay($employeeId, $day);
        $schedule = $resolved->isWorkDay() ? $resolved->schedule : null;

        return $this->scheduleCache[$cacheKey] = $schedule;
    }

    public function baseScheduleForEmployeeOnDate(Employee|string $employee, Carbon|string $date): ?WorkSchedule
    {
        return $this->forEmployeeOnDate($employee, $date);
    }

    /**
     * Shift penempatan kalender (roster) tanpa memperhitungkan override tukar sif.
     */
    public function placementScheduleForEmployeeOnDate(Employee|string $employee, Carbon|string $date): ?WorkSchedule
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $day = $this->toDateString($date);

        $directEntry = ShiftCalendarEntry::query()
            ->with('schedule')
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $day)
            ->orderBy('sort_order')
            ->first();
        if ($directEntry?->schedule && $this->scheduleAppliesOnDate($directEntry->schedule, $day)) {
            return $directEntry->schedule;
        }

        $group = app(ShiftGroupService::class)->groupForEmployeeOnDate($employeeId, $day);
        if ($group && ! $group->is_system_unassigned) {
            $entry = ShiftCalendarEntry::query()
                ->with('schedule')
                ->where('group_id', $group->id)
                ->whereDate('work_date', $day)
                ->orderBy('sort_order')
                ->first();

            if ($entry?->schedule && $this->scheduleAppliesOnDate($entry->schedule, $day)) {
                return $entry->schedule;
            }
        }

        return null;
    }

    /**
     * @param  iterable<int, string>  $employeeIds
     * @return array<string, string|null>
     */
    public function scheduleIdsForEmployeesOnDate(iterable $employeeIds, Carbon|string $date): array
    {
        $ids = collect($employeeIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $day = $this->toDateString($date);
        $map = [];
        foreach ($ids as $id) {
            $map[(string) $id] = $this->forEmployeeOnDate($id, $day)?->id;
        }

        return $map;
    }

    public function currentAssignment(Employee|string $employee, ?string $onDate = null): ?EmployeeShiftAssignment
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $day = $onDate ?? AppTimezone::nowDisplay()->toDateString();

        return EmployeeShiftAssignment::query()
            ->with('schedule')
            ->where('employee_id', $employeeId)
            ->whereDate('effective_from', '<=', $day)
            ->where(function ($q) use ($day) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $day);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    public function assign(
        Employee|string $employee,
        WorkSchedule|string $schedule,
        ?string $effectiveFrom = null,
    ): EmployeeShiftAssignment {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $scheduleId = $schedule instanceof WorkSchedule ? $schedule->id : $schedule;
        $from = $effectiveFrom ?? AppTimezone::nowDisplay()->toDateString();

        $open = EmployeeShiftAssignment::query()
            ->where('employee_id', $employeeId)
            ->whereNull('effective_to')
            ->orderByDesc('effective_from')
            ->get();

        foreach ($open as $row) {
            if ($row->work_schedule_id === $scheduleId && $row->effective_from->toDateString() <= $from) {
                return $row->load('schedule');
            }

            $end = Carbon::parse($from, AppTimezone::display())->subDay()->toDateString();
            if ($end < $row->effective_from->toDateString()) {
                $row->delete();
            } else {
                $row->update(['effective_to' => $end]);
            }
        }

        $this->forgetCache();

        return EmployeeShiftAssignment::query()->create([
            'employee_id' => $employeeId,
            'work_schedule_id' => $scheduleId,
            'effective_from' => $from,
            'effective_to' => null,
            'created_at' => now(),
        ])->load('schedule');
    }

    public function swap(Employee|string $employeeA, Employee|string $employeeB, ?string $effectiveFrom = null): void
    {
        $idA = $employeeA instanceof Employee ? $employeeA->id : $employeeA;
        $idB = $employeeB instanceof Employee ? $employeeB->id : $employeeB;
        $from = $effectiveFrom ?? AppTimezone::nowDisplay()->toDateString();

        if ($idA === $idB) {
            throw new \InvalidArgumentException('Pilih dua karyawan yang berbeda.');
        }

        $scheduleA = $this->baseScheduleForEmployeeOnDate($idA, $from);
        $scheduleB = $this->baseScheduleForEmployeeOnDate($idB, $from);

        if (! $scheduleA || ! $scheduleB) {
            throw new \RuntimeException('Kedua karyawan harus punya jadwal/shift yang bisa ditukar.');
        }

        if ($scheduleA->id === $scheduleB->id) {
            throw new \RuntimeException('Kedua karyawan sudah di shift yang sama.');
        }

        $this->forgetCache();
        $this->assign($idA, $scheduleB->id, $from);
        $this->assign($idB, $scheduleA->id, $from);
        $this->forgetCache();
    }

    /**
     * @param  list<string>  $employeeIds
     */
    public function rotate(array $employeeIds, ?string $effectiveFrom = null, int $steps = 1): void
    {
        $ids = array_values(array_unique(array_filter($employeeIds)));
        $from = $effectiveFrom ?? AppTimezone::nowDisplay()->toDateString();

        if (count($ids) < 2) {
            throw new \InvalidArgumentException('Rolling minimal 2 karyawan.');
        }

        if ($steps === 0) {
            throw new \InvalidArgumentException('Langkah rolling tidak boleh 0.');
        }

        $n = count($ids);
        $current = [];
        foreach ($ids as $id) {
            $schedule = $this->baseScheduleForEmployeeOnDate($id, $from);
            if (! $schedule) {
                throw new \RuntimeException('Semua karyawan harus punya shift sebelum rolling.');
            }
            $current[$id] = $schedule->id;
        }

        $uniqueShifts = array_unique(array_values($current));
        if (count($uniqueShifts) < 2) {
            throw new \RuntimeException('Rolling butuh minimal 2 shift berbeda di antara karyawan yang dipilih.');
        }

        $mod = (($steps % $n) + $n) % $n;
        $this->forgetCache();

        foreach ($ids as $i => $id) {
            $sourceIndex = ($i - $mod + $n) % $n;
            $this->assign($id, $current[$ids[$sourceIndex]], $from);
        }

        $this->forgetCache();
    }

    /**
     * @param  iterable<int, string>  $employeeIds
     */
    public function assignMany(iterable $employeeIds, WorkSchedule|string $schedule, ?string $effectiveFrom = null): int
    {
        $count = 0;
        foreach ($employeeIds as $id) {
            $this->assign($id, $schedule, $effectiveFrom);
            $count++;
        }
        $this->forgetCache();

        return $count;
    }

    private function withDayOverrides(
        WorkSchedule $schedule,
        ?int $work,
        ?int $break,
        ?string $breakEarliest = null,
    ): WorkSchedule {
        if ($work === null && $break === null && $breakEarliest === null) {
            return $schedule;
        }

        $clone = $schedule->replicate();
        $clone->id = $schedule->id;
        $clone->exists = true;
        if ($work !== null) {
            $clone->work_duration_minutes = $work;
        }
        if ($break !== null) {
            $clone->break_duration_minutes = $break;
        }
        if ($breakEarliest !== null) {
            $clone->break_earliest_time = $breakEarliest;
        }

        return $clone;
    }

    private function scheduleAppliesOnDate(WorkSchedule $schedule, string $day): bool
    {
        return $schedule->isImplementedOnDate($day);
    }

    private function formatBreakEarliestTime(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        return substr((string) $time, 0, 5);
    }

    private function toDateString(Carbon|string $date): string
    {
        return $date instanceof Carbon
            ? $date->copy()->timezone(AppTimezone::display())->toDateString()
            : Carbon::parse($date, AppTimezone::display())->toDateString();
    }
}
