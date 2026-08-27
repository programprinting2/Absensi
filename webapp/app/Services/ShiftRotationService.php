<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\ShiftDayOverride;
use App\Models\ShiftRotationPlan;
use App\Models\WorkSchedule;
use App\Support\AppTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ShiftRotationService
{
    /**
     * Indeks fase (0..phase_count-1) untuk tanggal kerja.
     * Dihitung dari jumlah hari kerja (Sen–Sab) sejak start_date.
     * Minggu memakai fase hari kerja sebelumnya.
     */
    public function phaseIndexForDate(ShiftRotationPlan $plan, Carbon|string $date): ?int
    {
        $day = $date instanceof Carbon
            ? $date->copy()->timezone(AppTimezone::display())->startOfDay()
            : Carbon::parse($date, AppTimezone::display())->startOfDay();

        $start = $plan->start_date->copy()->timezone(AppTimezone::display())->startOfDay();
        if ($day->lt($start)) {
            return null;
        }

        $phaseDays = max(1, (int) $plan->phase_work_days);
        $phaseCount = max(2, (int) $plan->phase_count);

        $cursor = $start->copy();
        $workIndex = -1;
        $lastWorkIndex = -1;

        while ($cursor->lte($day)) {
            if (! $cursor->isSunday()) {
                $workIndex++;
                $lastWorkIndex = $workIndex;
            }
            if ($cursor->equalTo($day)) {
                break;
            }
            $cursor->addDay();
        }

        if ($day->isSunday()) {
            if ($lastWorkIndex < 0) {
                return null;
            }
            $workIndex = $lastWorkIndex;
        }

        if ($workIndex < 0) {
            return null;
        }

        return intdiv($workIndex, $phaseDays) % $phaseCount;
    }

    /**
     * Penempatan dasar (assignment tetap) karyawan pada tanggal.
     */
    public function basePlacement(string $employeeId, Carbon|string $date): ?WorkSchedule
    {
        $day = $date instanceof Carbon
            ? $date->copy()->timezone(AppTimezone::display())->toDateString()
            : Carbon::parse($date, AppTimezone::display())->toDateString();

        $assignment = EmployeeShiftAssignment::query()
            ->with('schedule')
            ->where('employee_id', $employeeId)
            ->whereDate('effective_from', '<=', $day)
            ->where(function ($q) use ($day) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $day);
            })
            ->orderByDesc('effective_from')
            ->first();

        return $assignment?->schedule;
    }

    /**
     * Terapkan pola rotasi pada penempatan dasar.
     * Fase genap = tetap di penempatan; fase ganjil = tukar ke pasangan shift.
     */
    public function applyRotationToPlacement(?WorkSchedule $placement, Carbon|string $date): ?WorkSchedule
    {
        if (! $placement) {
            return null;
        }

        $plan = ShiftRotationPlan::active();
        if (! $plan?->schedule_a_id || ! $plan->schedule_b_id) {
            return null;
        }

        $pair = [(string) $plan->schedule_a_id, (string) $plan->schedule_b_id];
        if (! in_array((string) $placement->id, $pair, true)) {
            return null;
        }

        $phase = $this->phaseIndexForDate($plan, $date);
        if ($phase === null) {
            return null;
        }

        // Fase 0,2,4... = penempatan; 1,3,5... = pasangan
        if ($phase % 2 === 0) {
            return $placement;
        }

        $otherId = (string) $placement->id === (string) $plan->schedule_a_id
            ? $plan->schedule_b_id
            : $plan->schedule_a_id;

        if ((string) $otherId === (string) $placement->id) {
            return $placement;
        }

        return $plan->relationLoaded('scheduleA') && $plan->relationLoaded('scheduleB')
            ? (((string) $otherId === (string) $plan->schedule_a_id) ? $plan->scheduleA : $plan->scheduleB)
            : WorkSchedule::query()->find($otherId);
    }

    /**
     * @deprecated Gunakan applyRotationToPlacement + basePlacement.
     */
    public function scheduleFromRotation(string $employeeId, Carbon|string $date): ?WorkSchedule
    {
        $placement = $this->basePlacement($employeeId, $date);

        return $this->applyRotationToPlacement($placement, $date);
    }

    public function overrideFor(string $employeeId, Carbon|string $date): ?ShiftDayOverride
    {
        $day = $date instanceof Carbon
            ? $date->copy()->timezone(AppTimezone::display())->toDateString()
            : Carbon::parse($date, AppTimezone::display())->toDateString();

        return ShiftDayOverride::query()
            ->with('schedule')
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $day)
            ->first();
    }

    public function setOverride(
        Employee|string $employee,
        Carbon|string $date,
        WorkSchedule|string $schedule,
        ?string $reason = null,
    ): ShiftDayOverride {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $scheduleId = $schedule instanceof WorkSchedule ? $schedule->id : $schedule;
        $day = $date instanceof Carbon
            ? $date->copy()->timezone(AppTimezone::display())->toDateString()
            : Carbon::parse($date, AppTimezone::display())->toDateString();

        return ShiftDayOverride::query()->updateOrCreate(
            [
                'employee_id' => $employeeId,
                'work_date' => $day,
            ],
            [
                'work_schedule_id' => $scheduleId,
                'reason' => $reason,
                'created_at' => now(),
            ],
        )->load('schedule');
    }

    public function clearOverride(Employee|string $employee, Carbon|string $date): void
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $day = $date instanceof Carbon
            ? $date->copy()->timezone(AppTimezone::display())->toDateString()
            : Carbon::parse($date, AppTimezone::display())->toDateString();

        ShiftDayOverride::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $day)
            ->delete();
    }

    /**
     * Tukar sementara: dua karyawan saling menukar shift ter-resolve di tanggal itu via override.
     */
    public function temporarySwap(
        Employee|string $employeeA,
        Employee|string $employeeB,
        Carbon|string $date,
        ?string $reason = null,
        ?ShiftResolver $resolver = null,
    ): void {
        $idA = $employeeA instanceof Employee ? $employeeA->id : $employeeA;
        $idB = $employeeB instanceof Employee ? $employeeB->id : $employeeB;
        $day = $date instanceof Carbon
            ? $date->copy()->timezone(AppTimezone::display())->toDateString()
            : Carbon::parse($date, AppTimezone::display())->toDateString();

        if ($idA === $idB) {
            throw new InvalidArgumentException('Pilih dua karyawan berbeda.');
        }

        $resolver ??= app(ShiftResolver::class);
        $resolver->forgetCache();
        $scheduleA = $resolver->baseScheduleForEmployeeOnDate($idA, $day);
        $scheduleB = $resolver->baseScheduleForEmployeeOnDate($idB, $day);

        if (! $scheduleA || ! $scheduleB) {
            throw new RuntimeException('Kedua karyawan harus punya jadwal di tanggal tersebut.');
        }

        if ($scheduleA->id === $scheduleB->id) {
            throw new RuntimeException('Kedua karyawan sudah di shift yang sama pada tanggal itu.');
        }

        $label = $reason ?: 'Tukar sementara';
        $this->setOverride($idA, $day, $scheduleB->id, $label.' (dengan rekan)');
        $this->setOverride($idB, $day, $scheduleA->id, $label.' (dengan rekan)');
        $resolver->forgetCache();
    }

    /**
     * Simpan pola rotasi berbasis pasangan shift (dari penempatan).
     * Fase genap = penempatan; fase ganjil = tukar A↔B.
     */
    public function savePairPlan(
        string $name,
        string $startDate,
        int $phaseWorkDays,
        string $scheduleAId,
        string $scheduleBId,
        bool $activate = true,
        ?string $planId = null,
    ): ShiftRotationPlan {
        if ($scheduleAId === $scheduleBId) {
            throw new InvalidArgumentException('Pilih dua rule shift yang berbeda untuk rotasi.');
        }

        return DB::transaction(function () use ($name, $startDate, $phaseWorkDays, $scheduleAId, $scheduleBId, $activate, $planId) {
            if ($activate) {
                ShiftRotationPlan::query()->where('is_active', true)->update(['is_active' => false]);
            }

            $plan = $planId
                ? ShiftRotationPlan::query()->findOrFail($planId)
                : new ShiftRotationPlan(['created_at' => now()]);

            $plan->fill([
                'name' => $name,
                'start_date' => $startDate,
                'phase_work_days' => max(1, $phaseWorkDays),
                'phase_count' => 2,
                'schedule_a_id' => $scheduleAId,
                'schedule_b_id' => $scheduleBId,
                'is_active' => $activate,
            ]);
            if (! $plan->exists) {
                $plan->created_at = now();
            }
            $plan->save();

            return $plan->fresh(['scheduleA', 'scheduleB']);
        });
    }

    public function phaseLabel(int $phaseIndex): string
    {
        return 'Fase '.($phaseIndex + 1);
    }
}
