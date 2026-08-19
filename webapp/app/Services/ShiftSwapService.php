<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ShiftEmployeeShiftOverride;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\AppTimezone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ShiftSwapService
{
    public function createRequest(
        Employee $employee,
        string $workDate,
        string $toScheduleId,
        ?string $reason = null,
    ): ShiftSwapRequest {
        if (! WorkSchedule::query()->enabled()->whereKey($toScheduleId)->exists()) {
            throw new InvalidArgumentException('Rule shift tujuan tidak valid.');
        }

        $targetSchedule = WorkSchedule::query()->enabled()->findOrFail($toScheduleId);
        if (! $targetSchedule->isImplementedOnDate($workDate)) {
            throw new InvalidArgumentException('Shift tujuan tidak berlaku di tanggal tersebut.');
        }

        $pending = ShiftSwapRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate)
            ->where('status', ShiftSwapRequest::STATUS_PENDING)
            ->exists();

        if ($pending) {
            throw new RuntimeException('Sudah ada pengajuan tukar sif menunggu untuk tanggal itu.');
        }

        $req = ShiftSwapRequest::query()->create([
            'employee_id' => $employee->id,
            'work_date' => $workDate,
            'to_work_schedule_id' => $toScheduleId,
            'reason' => $reason,
            'status' => ShiftSwapRequest::STATUS_PENDING,
            'created_at' => now(),
        ]);

        ActivityLogger::normal(
            'Mengajukan tukar sif '.$employee->full_name.' → '.$workDate,
            'shift.swap.request',
            ['employee_id' => $employee->id, 'work_date' => $workDate, 'to_schedule_id' => $toScheduleId],
        );

        return $req->load(['toSchedule', 'employee']);
    }

    public function approve(ShiftSwapRequest $request, ?User $reviewer = null, ?string $notes = null): void
    {
        if ($request->status !== ShiftSwapRequest::STATUS_PENDING) {
            throw new RuntimeException('Pengajuan sudah diproses.');
        }

        DB::transaction(function () use ($request, $reviewer, $notes) {
            app(ShiftCalendarService::class)->setShiftOverride(
                $request->employee_id,
                $request->work_date->toDateString(),
                $request->to_work_schedule_id,
                'swap_request:'.$request->id,
            );

            $request->update([
                'status' => ShiftSwapRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);
        });

        app(ShiftResolver::class)->forgetCache();

        ActivityLogger::medium(
            'Menyetujui tukar sif '.$request->employee?->full_name.' ('.$request->work_date->toDateString().')',
            'shift.swap.approve',
            ['request_id' => $request->id],
        );
    }

    public function reject(ShiftSwapRequest $request, ?User $reviewer = null, ?string $notes = null): void
    {
        if ($request->status !== ShiftSwapRequest::STATUS_PENDING) {
            throw new RuntimeException('Pengajuan sudah diproses.');
        }

        $request->update([
            'status' => ShiftSwapRequest::STATUS_REJECTED,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        ActivityLogger::normal(
            'Menolak tukar sif '.$request->employee?->full_name.' ('.$request->work_date->toDateString().')',
            'shift.swap.reject',
            ['request_id' => $request->id],
        );
    }

    public function cancel(ShiftSwapRequest $request, Employee $employee): void
    {
        if ((string) $request->employee_id !== (string) $employee->id) {
            throw new RuntimeException('Pengajuan bukan milik Anda.');
        }
        if ($request->status !== ShiftSwapRequest::STATUS_PENDING) {
            throw new RuntimeException('Hanya pengajuan menunggu yang bisa dibatalkan.');
        }

        $request->update(['status' => ShiftSwapRequest::STATUS_CANCELLED]);

        ActivityLogger::normal(
            'Membatalkan pengajuan tukar sif ('.$request->work_date->toDateString().')',
            'shift.swap.cancel',
            ['request_id' => $request->id],
        );
    }
}
