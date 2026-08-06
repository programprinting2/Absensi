<?php

namespace App\Http\Controllers;

use App\Models\AllowanceType;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeSalary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EmployeeSalaryController extends Controller
{
    public function edit(Employee $employee)
    {
        // Pengaturan gaji sekarang lewat modal di halaman karyawan.
        return redirect()
            ->route('employees.index')
            ->with('open_salary_employee', $employee->id);
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'base_salary' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
            'allowances' => ['nullable', 'array'],
            'allowances.*.allowance_type_id' => ['required', 'exists:allowance_types,id'],
            'allowances.*.amount' => ['required', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'array'],
            'deductions.*.deduction_type_id' => ['required', 'exists:deduction_types,id'],
            'deductions.*.value' => ['required', 'numeric', 'min:0'],
        ]);

        $active = $employee->activeSalary;
        $newAmount = (float) $data['base_salary'];
        $newDate = Carbon::parse($data['effective_date'])->toDateString();
        $salaryChanged = ! $active
            || (float) $active->base_salary !== $newAmount
            || $active->effective_date?->toDateString() !== $newDate;

        if ($salaryChanged) {
            $employee->salaries()->where('is_active', true)->update(['is_active' => false]);

            EmployeeSalary::create([
                'employee_id' => $employee->id,
                'base_salary' => $data['base_salary'],
                'effective_date' => $data['effective_date'],
                'is_active' => true,
            ]);
        }

        $employee->employeeAllowances()->delete();
        foreach ($data['allowances'] ?? [] as $allowance) {
            EmployeeAllowance::create([
                'employee_id' => $employee->id,
                'allowance_type_id' => $allowance['allowance_type_id'],
                'amount' => $allowance['amount'],
            ]);
        }

        $employee->employeeDeductions()->delete();
        foreach ($data['deductions'] ?? [] as $deduction) {
            EmployeeDeduction::create([
                'employee_id' => $employee->id,
                'deduction_type_id' => $deduction['deduction_type_id'],
                'value' => $deduction['value'],
            ]);
        }

        $message = $salaryChanged
            ? 'Perubahan gaji berhasil dicatat ke riwayat.'
            : 'Tunjangan/potongan berhasil disimpan.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'history' => $employee->fresh()->salaryHistoryTimeline(),
            ]);
        }

        return redirect()->route('employees.index')->with('status', $message);
    }

    public function payload(Employee $employee)
    {
        $employee->load(['activeSalary', 'employeeAllowances', 'employeeDeductions']);

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ],
            'base_salary' => (float) ($employee->activeSalary?->base_salary ?? 0),
            'effective_date' => $employee->activeSalary?->effective_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'allowances' => $employee->employeeAllowances->map(fn ($a) => [
                'allowance_type_id' => $a->allowance_type_id,
                'amount' => (float) $a->amount,
            ])->values(),
            'deductions' => $employee->employeeDeductions->map(fn ($d) => [
                'deduction_type_id' => $d->deduction_type_id,
                'value' => (float) $d->value,
            ])->values(),
            'history' => $employee->salaryHistoryTimeline(),
            'allowance_types' => AllowanceType::where('is_active', true)->orderBy('name')->get()->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'label' => $t->name,
                'value' => $t->name,
            ])->values(),
            'deduction_types' => DeductionType::where('is_active', true)->orderBy('name')->get()->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'label' => $t->name.($t->calculation_method === 'percentage' ? ' (%)' : ' (Rp)'),
                'value' => $t->name,
                'calculation_method' => $t->calculation_method,
            ])->values(),
            'update_url' => route('employees.salary.update', $employee),
        ]);
    }
}
