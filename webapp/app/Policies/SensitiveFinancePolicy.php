<?php

namespace App\Policies;

use App\Models\CashBon;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveGrant;
use App\Models\PayrollPeriod;
use App\Models\User;

/**
 * Aksi sensitif (uang / finalisasi) hanya untuk admin.
 * Operasi rutin (catat cuti, generate draft, buat kasbon) tetap lewat hak menu.
 */
class SensitiveFinancePolicy
{
    public function managePayrollPeriods(User $user, ?PayrollPeriod $period = null): bool
    {
        return $user->isAdmin();
    }

    public function manageLeaveQuotaMoney(User $user): bool
    {
        return $user->isAdmin();
    }

    public function deleteLeaveGrant(User $user, ?EmployeeLeaveGrant $grant = null): bool
    {
        return $user->isAdmin();
    }

    public function cancelCashBon(User $user, ?CashBon $cashBon = null): bool
    {
        return $user->isAdmin();
    }

    public function approveLeave(User $user, ?EmployeeLeave $leave = null): bool
    {
        return $user->isAdmin() || $user->canAccessMenu('leaves');
    }
}
