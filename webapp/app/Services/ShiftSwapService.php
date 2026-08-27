<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\AppTimezone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ShiftSwapService
{
    public function createMoveRequest(
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

        $this->assertNoPendingRequest($employee->id, $workDate);

        $req = ShiftSwapRequest::query()->create([
            'employee_id' => $employee->id,
            'request_type' => ShiftSwapRequest::TYPE_MOVE,
            'work_date' => $workDate,
            'to_work_schedule_id' => $toScheduleId,
            'reason' => $reason,
            'status' => ShiftSwapRequest::STATUS_PENDING,
            'created_at' => now(),
        ]);

        ActivityLogger::normal(
            'Mengajukan pindah shift '.$employee->full_name.' → '.$workDate,
            'shift.swap.request',
            ['employee_id' => $employee->id, 'work_date' => $workDate, 'to_schedule_id' => $toScheduleId],
        );

        return $req->load(['toSchedule', 'employee']);
    }

    /** @deprecated Use createMoveRequest() */
    public function createRequest(
        Employee $employee,
        string $workDate,
        string $toScheduleId,
        ?string $reason = null,
    ): ShiftSwapRequest {
        return $this->createMoveRequest($employee, $workDate, $toScheduleId, $reason);
    }

    public function createPeerSwapRequest(
        Employee $employee,
        string $workDate,
        string $counterpartyEmployeeId,
        ?string $reason = null,
    ): ShiftSwapRequest {
        if ((string) $employee->id === (string) $counterpartyEmployeeId) {
            throw new InvalidArgumentException('Pilih karyawan yang berbeda.');
        }

        $counterparty = Employee::query()->where('is_active', true)->find($counterpartyEmployeeId);
        if (! $counterparty) {
            throw new InvalidArgumentException('Karyawan lawan tidak valid.');
        }

        $resolver = app(ShiftResolver::class);
        $requesterSchedule = $resolver->placementScheduleForEmployeeOnDate($employee->id, $workDate);
        $counterpartySchedule = $resolver->placementScheduleForEmployeeOnDate($counterparty->id, $workDate);

        if (! $requesterSchedule) {
            throw new RuntimeException('Anda tidak punya jadwal shift di tanggal tersebut.');
        }
        if (! $counterpartySchedule) {
            throw new RuntimeException('Karyawan lawan tidak punya jadwal shift di tanggal tersebut.');
        }
        if ((string) $requesterSchedule->id === (string) $counterpartySchedule->id) {
            throw new RuntimeException('Kedua karyawan sudah di shift yang sama pada tanggal itu.');
        }

        $this->assertNoPendingRequest($employee->id, $workDate);
        $this->assertNoPendingRequest($counterparty->id, $workDate);

        $req = ShiftSwapRequest::query()->create([
            'employee_id' => $employee->id,
            'request_type' => ShiftSwapRequest::TYPE_PEER_SWAP,
            'counterparty_employee_id' => $counterparty->id,
            'work_date' => $workDate,
            'to_work_schedule_id' => $counterpartySchedule->id,
            'reason' => $reason,
            'status' => ShiftSwapRequest::STATUS_PENDING,
            'peer_status' => ShiftSwapRequest::PEER_STATUS_PENDING,
            'created_at' => now(),
        ]);

        ActivityLogger::normal(
            'Mengajukan tukar shift '.$employee->full_name.' ↔ '.$counterparty->full_name.' ('.$workDate.')',
            'shift.swap.peer.request',
            [
                'employee_id' => $employee->id,
                'counterparty_employee_id' => $counterparty->id,
                'work_date' => $workDate,
            ],
        );

        return $req->load(['toSchedule', 'employee', 'counterparty']);
    }

    public function approvePeer(ShiftSwapRequest $request, Employee $counterparty): void
    {
        if ((string) $request->counterparty_employee_id !== (string) $counterparty->id) {
            throw new RuntimeException('Pengajuan ini bukan untuk Anda.');
        }
        if (! $request->awaitsPeerApproval()) {
            throw new RuntimeException('Pengajuan sudah diproses.');
        }

        $request->update([
            'peer_status' => ShiftSwapRequest::PEER_STATUS_APPROVED,
            'peer_reviewed_at' => now(),
        ]);

        ActivityLogger::normal(
            'Menyetujui tukar shift dari '.$request->employee?->full_name.' ('.$request->work_date->toDateString().')',
            'shift.swap.peer.approve',
            ['request_id' => $request->id],
        );
    }

    public function rejectPeer(ShiftSwapRequest $request, Employee $counterparty): void
    {
        if ((string) $request->counterparty_employee_id !== (string) $counterparty->id) {
            throw new RuntimeException('Pengajuan ini bukan untuk Anda.');
        }
        if (! $request->awaitsPeerApproval()) {
            throw new RuntimeException('Pengajuan sudah diproses.');
        }

        $request->update([
            'status' => ShiftSwapRequest::STATUS_REJECTED,
            'peer_status' => ShiftSwapRequest::PEER_STATUS_REJECTED,
            'peer_reviewed_at' => now(),
        ]);

        ActivityLogger::normal(
            'Menolak tukar shift dari '.$request->employee?->full_name.' ('.$request->work_date->toDateString().')',
            'shift.swap.peer.reject',
            ['request_id' => $request->id],
        );
    }

    public function approve(ShiftSwapRequest $request, ?User $reviewer = null, ?string $notes = null): void
    {
        if (! $request->awaitsAdminApproval()) {
            throw new RuntimeException('Pengajuan sudah diproses atau belum dikonfirmasi rekan.');
        }

        DB::transaction(function () use ($request, $reviewer, $notes) {
            $calendar = app(ShiftCalendarService::class);
            $date = $request->work_date->toDateString();

            if ($request->isPeerSwap()) {
                $this->applyPeerSwapOverrides($request, $calendar, $date);
            } else {
                $calendar->setShiftOverride(
                    $request->employee_id,
                    $date,
                    $request->to_work_schedule_id,
                    'swap_request:'.$request->id,
                );
            }

            $request->update([
                'status' => ShiftSwapRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);
        });

        app(ShiftResolver::class)->forgetCache();

        ActivityLogger::medium(
            'Menyetujui pengajuan shift '.$request->employee?->full_name.' ('.$request->work_date->toDateString().')',
            'shift.swap.approve',
            ['request_id' => $request->id],
        );
    }

    public function reject(ShiftSwapRequest $request, ?User $reviewer = null, ?string $notes = null): void
    {
        if (! $request->awaitsAdminApproval()) {
            throw new RuntimeException('Pengajuan sudah diproses atau belum dikonfirmasi rekan.');
        }

        $request->update([
            'status' => ShiftSwapRequest::STATUS_REJECTED,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        ActivityLogger::normal(
            'Menolak pengajuan shift '.$request->employee?->full_name.' ('.$request->work_date->toDateString().')',
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
            'Membatalkan pengajuan shift ('.$request->work_date->toDateString().')',
            'shift.swap.cancel',
            ['request_id' => $request->id],
        );
    }

    /**
     * @return list<array{id: string, full_name: string, schedule_id: string, schedule_name: string}>
     */
    public function employeesScheduledOnDate(string $workDate, ?string $excludeEmployeeId = null): array
    {
        $resolver = app(ShiftResolver::class);
        $result = [];

        $employees = Employee::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        foreach ($employees as $emp) {
            if ($excludeEmployeeId !== null && (string) $emp->id === $excludeEmployeeId) {
                continue;
            }

            $resolved = $resolver->resolveDay($emp->id, $workDate);
            if (! $resolved->isWorkDay()) {
                continue;
            }

            $placement = $resolver->placementScheduleForEmployeeOnDate($emp->id, $workDate);
            if (! $placement) {
                continue;
            }

            $result[] = [
                'id' => (string) $emp->id,
                'full_name' => $emp->full_name,
                'schedule_id' => (string) $placement->id,
                'schedule_name' => $placement->name,
            ];
        }

        return $result;
    }

    private function applyPeerSwapOverrides(ShiftSwapRequest $request, ShiftCalendarService $calendar, string $date): void
    {
        $resolver = app(ShiftResolver::class);
        $idA = (string) $request->employee_id;
        $idB = (string) $request->counterparty_employee_id;

        $scheduleA = $resolver->placementScheduleForEmployeeOnDate($idA, $date);
        $scheduleB = $resolver->placementScheduleForEmployeeOnDate($idB, $date);

        if (! $scheduleA || ! $scheduleB) {
            throw new RuntimeException('Jadwal roster salah satu karyawan sudah tidak valid. Tolak pengajuan ini.');
        }
        if ((string) $scheduleA->id === (string) $scheduleB->id) {
            throw new RuntimeException('Kedua karyawan sudah di shift yang sama. Tolak pengajuan ini.');
        }

        $label = 'peer_swap:'.$request->id;
        $calendar->setShiftOverride($idA, $date, $scheduleB->id, $label);
        $calendar->setShiftOverride($idB, $date, $scheduleA->id, $label);
    }

    private function assertNoPendingRequest(string $employeeId, string $workDate): void
    {
        $pending = ShiftSwapRequest::query()
            ->where('status', ShiftSwapRequest::STATUS_PENDING)
            ->whereDate('work_date', $workDate)
            ->where(function ($q) use ($employeeId) {
                $q->where('employee_id', $employeeId)
                    ->orWhere('counterparty_employee_id', $employeeId);
            })
            ->exists();

        if ($pending) {
            throw new RuntimeException('Sudah ada pengajuan shift menunggu untuk tanggal itu.');
        }
    }
}
