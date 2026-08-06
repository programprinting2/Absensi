<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryDetail;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Models\WorkSchedule;
use App\Support\AppTimezone;
use Illuminate\Support\Facades\DB;

class PayrollCalculationService
{
    public function __construct(
        private AttendanceReportService $attendanceService,
        private CashBonService $cashBonService,
    ) {}

    /**
     * @return array{employees: int, with_salary: int, without_salary: int}
     */
    public function generateForPeriod(PayrollPeriod $period): array
    {
        $settings = PayrollSetting::active();

        $employees = Employee::query()
            ->where('is_active', true)
            ->with([
                'activeSalary',
                'employeeAllowances.allowanceType',
                'employeeDeductions.deductionType',
            ])
            ->orderBy('full_name')
            ->get();

        $withSalary = 0;
        $withoutSalary = 0;

        DB::transaction(function () use ($period, $settings, $employees, &$withSalary, &$withoutSalary) {
            foreach ($employees as $employee) {
                if ($employee->activeSalary) {
                    $withSalary++;
                } else {
                    $withoutSalary++;
                }

                $this->calculateForEmployee($employee, $period, $settings);
            }

            $period->update([
                'status' => 'review',
                'generated_at' => now(),
            ]);
        });

        return [
            'employees' => $employees->count(),
            'with_salary' => $withSalary,
            'without_salary' => $withoutSalary,
        ];
    }

    public function calculateForEmployee(Employee $employee, PayrollPeriod $period, PayrollSetting $settings): PayrollEntry
    {
        $existing = PayrollEntry::query()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        if ($existing) {
            $this->cashBonService->releaseFromEntry($existing);
        }

        $entry = PayrollEntry::updateOrCreate(
            ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
            $this->buildEntryData($employee, $period, $settings),
        );

        $entry->details()->delete();
        $this->createEntryDetails($entry, $employee, $settings);

        return $entry;
    }

    public function recalculateEntry(PayrollEntry $entry): PayrollEntry
    {
        $settings = PayrollSetting::active();
        $employee = $entry->employee()->with([
            'activeSalary',
            'employeeAllowances.allowanceType',
            'employeeDeductions.deductionType',
        ])->firstOrFail();
        $period = $entry->period;

        $this->cashBonService->releaseFromEntry($entry);

        $entry->update($this->buildEntryData($employee, $period, $settings));
        $entry->details()->delete();
        $this->createEntryDetails($entry, $employee, $settings);

        return $entry->fresh(['employee', 'details', 'period']);
    }

    /**
     * Penyesuaian manual: hitung ulang OT / gross / net / detail OT.
     *
     * @param  array{base_salary: float|int|string|null, overtime_hours: float|int|string|null, notes: ?string}  $data
     */
    public function applyAdjustment(PayrollEntry $entry, array $data): PayrollEntry
    {
        $settings = PayrollSetting::active();

        $baseSalary = (float) ($data['base_salary'] ?? $entry->base_salary);
        $overtimeHours = (float) ($data['overtime_hours'] ?? $entry->overtime_hours);
        $overtimeAmount = round($overtimeHours * (float) $settings->overtime_rate_per_hour, 2);

        $totalAllowances = (float) $entry->total_allowances;
        $totalDeductions = (float) $entry->total_deductions;
        $totalPenalties = (float) $entry->late_penalty
            + (float) $entry->absent_penalty
            + (float) $entry->early_out_penalty
            + (float) ($entry->short_work_penalty ?? 0)
            + (float) ($entry->over_break_penalty ?? 0);

        $grossSalary = $baseSalary + $totalAllowances + $overtimeAmount;

        $pph21 = 0;
        if ($settings->enable_pph21) {
            $pph21 = $this->calculatePph21($grossSalary - $totalDeductions - $totalPenalties);
        }

        $netSalary = $grossSalary - $totalDeductions - $totalPenalties - $pph21;

        $entry->update([
            'base_salary' => $baseSalary,
            'overtime_hours' => $overtimeHours,
            'overtime_amount' => $overtimeAmount,
            'pph21_amount' => $pph21,
            'gross_salary' => $grossSalary,
            'net_salary' => $netSalary,
            'notes' => $data['notes'] ?? $entry->notes,
            'is_adjusted' => true,
        ]);

        $entry->details()->where('category', 'overtime')->delete();
        if ($overtimeAmount > 0) {
            PayrollEntryDetail::create([
                'payroll_entry_id' => $entry->id,
                'category' => 'overtime',
                'label' => "Lembur ({$overtimeHours} jam)",
                'amount' => $overtimeAmount,
            ]);
        }

        $entry->details()->where('category', 'tax')->delete();
        if ($pph21 > 0) {
            PayrollEntryDetail::create([
                'payroll_entry_id' => $entry->id,
                'category' => 'tax',
                'label' => 'PPh 21',
                'amount' => $pph21,
            ]);
        }

        return $entry->fresh(['employee', 'details', 'period']);
    }

    public function addManualDetail(PayrollEntry $entry, string $side, string $label, float $amount): PayrollEntry
    {
        $this->assertEntryEditable($entry);

        $side = strtolower($side);
        if (! in_array($side, ['credit', 'debit'], true)) {
            throw new \InvalidArgumentException('Jenis komponen tidak valid.');
        }

        $label = trim($label);
        if ($label === '') {
            throw new \InvalidArgumentException('Nama komponen wajib diisi.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Nominal harus lebih dari 0.');
        }

        PayrollEntryDetail::create([
            'payroll_entry_id' => $entry->id,
            'category' => $side === 'credit' ? 'allowance' : 'deduction',
            'label' => $label,
            'amount' => round($amount, 2),
        ]);

        return $this->recomputeTotalsFromDetails($entry->fresh('details'));
    }

    public function deleteDetail(PayrollEntry $entry, string $detailId): PayrollEntry
    {
        $this->assertEntryEditable($entry);

        $detail = $entry->details()->whereKey($detailId)->firstOrFail();

        if ($detail->category === 'cash_bon') {
            $this->cashBonService->releaseFromEntry($entry);
            $entry->details()->where('category', 'cash_bon')->delete();
        } else {
            $detail->delete();
        }

        return $this->recomputeTotalsFromDetails($entry->fresh('details'));
    }

    public function updateDetailAmount(PayrollEntry $entry, string $detailId, float $amount): PayrollEntry
    {
        $this->assertEntryEditable($entry);

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Nominal harus lebih dari 0.');
        }

        $detail = $entry->details()->whereKey($detailId)->firstOrFail();
        $oldAmount = (float) $detail->amount;

        if (abs($oldAmount - $amount) < 0.009) {
            return $entry->fresh(['employee', 'details', 'period']);
        }

        if ($detail->category === 'cash_bon') {
            $installment = $this->resolveCashBonInstallment($entry, $detail);
            if (! $installment) {
                throw new \RuntimeException('Cicilan cash bon tidak ditemukan untuk komponen ini.');
            }

            $this->cashBonService->adjustDeductedAmount($installment, $amount);
            $installment->refresh();
            $bon = $installment->cashBon()->first();
            $count = $bon?->installments()->count() ?? $installment->sequence;

            $detail->update([
                'amount' => $amount,
                'label' => 'Cash Bon cicilan '.$installment->sequence.'/'.$count,
                'reference_id' => $installment->id,
                'reference_type' => 'cash_bon_installment',
            ]);
        } else {
            $detail->update(['amount' => $amount]);
        }

        return $this->recomputeTotalsFromDetails($entry->fresh('details'));
    }

    private function resolveCashBonInstallment(PayrollEntry $entry, PayrollEntryDetail $detail): ?\App\Models\CashBonInstallment
    {
        if ($detail->reference_type === 'cash_bon_installment' && $detail->reference_id) {
            return \App\Models\CashBonInstallment::query()
                ->whereKey($detail->reference_id)
                ->where('payroll_entry_id', $entry->id)
                ->first();
        }

        // Fallback data lama: cocokkan Cicilan N dari label.
        if (preg_match('/cicilan\s+(\d+)\s*\//i', (string) $detail->label, $m)) {
            $sequence = (int) $m[1];

            return \App\Models\CashBonInstallment::query()
                ->where('payroll_entry_id', $entry->id)
                ->where('sequence', $sequence)
                ->first();
        }

        return \App\Models\CashBonInstallment::query()
            ->where('payroll_entry_id', $entry->id)
            ->where('amount', $detail->amount)
            ->orderBy('sequence')
            ->first();
    }

    public function recomputeTotalsFromDetails(PayrollEntry $entry): PayrollEntry
    {
        $details = $entry->details()->get();

        $totalAllowances = (float) $details->where('category', 'allowance')->sum('amount');
        $overtimeAmount = (float) $details->where('category', 'overtime')->sum('amount');
        $totalDeductions = (float) $details->whereIn('category', ['deduction', 'cash_bon'])->sum('amount');
        $totalPenalties = (float) $details->where('category', 'penalty')->sum('amount');
        $pph21 = (float) $details->where('category', 'tax')->sum('amount');

        $baseSalary = (float) $entry->base_salary;
        $grossSalary = $baseSalary + $totalAllowances + $overtimeAmount;
        $netSalary = $grossSalary - $totalDeductions - $totalPenalties - $pph21;

        $settings = PayrollSetting::active();
        $overtimeHours = (float) $settings->overtime_rate_per_hour > 0
            ? round($overtimeAmount / (float) $settings->overtime_rate_per_hour, 2)
            : (float) $entry->overtime_hours;

        $entry->update([
            'total_allowances' => $totalAllowances,
            'overtime_amount' => $overtimeAmount,
            'overtime_hours' => $overtimeHours,
            'total_deductions' => $totalDeductions,
            'late_penalty' => $totalPenalties,
            'absent_penalty' => 0,
            'early_out_penalty' => 0,
            'short_work_penalty' => 0,
            'over_break_penalty' => 0,
            'pph21_amount' => $pph21,
            'gross_salary' => $grossSalary,
            'net_salary' => $netSalary,
            'is_adjusted' => true,
        ]);

        return $entry->fresh(['employee', 'details', 'period']);
    }

    private function assertEntryEditable(PayrollEntry $entry): void
    {
        $period = $entry->period ?? $entry->period()->first();

        if (! $period || ! $period->isReview()) {
            throw new \RuntimeException('Komponen hanya bisa diubah saat periode berstatus Review.');
        }
    }

    private function buildEntryData(Employee $employee, PayrollPeriod $period, PayrollSetting $settings): array
    {
        $salary = $employee->activeSalary;
        $baseSalary = $salary ? (float) $salary->base_salary : 0;

        $allowances = $employee->relationLoaded('employeeAllowances')
            ? $employee->employeeAllowances
            : $employee->employeeAllowances()->with('allowanceType')->get();

        $totalAllowances = $allowances
            ->filter(fn ($a) => ! $a->allowanceType || $a->allowanceType->is_active !== false)
            ->sum('amount');

        $deductions = $employee->relationLoaded('employeeDeductions')
            ? $employee->employeeDeductions
            : $employee->employeeDeductions()->with('deductionType')->get();

        $totalDeductions = $deductions
            ->filter(fn ($d) => ! $d->deductionType || $d->deductionType->is_active !== false)
            ->sum(function ($d) use ($baseSalary) {
                if (($d->deductionType->calculation_method ?? 'fixed') === 'percentage') {
                    return $baseSalary * ((float) $d->value / 100);
                }

                return (float) $d->value;
            });

        $totalDeductions += $this->cashBonService->pendingTotalFor($employee);

        $attendance = $this->getAttendanceSummary($employee, $period);

        $latePenalty = $attendance['late_count'] * (float) $settings->late_penalty_per_incident;
        $absentPenalty = $attendance['absent_days'] * (float) $settings->absent_penalty_per_day;
        $earlyOutPenalty = $attendance['early_out_count'] * (float) $settings->early_out_penalty_per_incident;
        $overBreakPenalty = $attendance['over_break_count'] * (float) ($settings->over_break_penalty_per_incident ?? 0);
        $shortWorkPenalty = $attendance['short_work_hours'] * (float) ($settings->short_work_penalty_per_hour ?? 0);
        $overtimeAmount = $attendance['overtime_hours'] * (float) $settings->overtime_rate_per_hour;

        $grossSalary = $baseSalary + $totalAllowances + $overtimeAmount;
        $totalPenalties = $latePenalty + $absentPenalty + $earlyOutPenalty + $overBreakPenalty + $shortWorkPenalty;

        $pph21 = 0;
        if ($settings->enable_pph21) {
            $pph21 = $this->calculatePph21($grossSalary - $totalDeductions - $totalPenalties);
        }

        $netSalary = $grossSalary - $totalDeductions - $totalPenalties - $pph21;

        return [
            'base_salary' => $baseSalary,
            'total_allowances' => $totalAllowances,
            'total_deductions' => $totalDeductions,
            'overtime_hours' => $attendance['overtime_hours'],
            'overtime_amount' => $overtimeAmount,
            'late_count' => $attendance['late_count'],
            'late_penalty' => $latePenalty,
            'absent_days' => $attendance['absent_days'],
            'absent_penalty' => $absentPenalty,
            'early_out_count' => $attendance['early_out_count'],
            'early_out_penalty' => $earlyOutPenalty,
            'short_work_hours' => $attendance['short_work_hours'],
            'short_work_penalty' => $shortWorkPenalty,
            'over_break_count' => $attendance['over_break_count'],
            'over_break_penalty' => $overBreakPenalty,
            'pph21_amount' => $pph21,
            'gross_salary' => $grossSalary,
            'net_salary' => $netSalary,
            'is_adjusted' => false,
        ];
    }

    private function getAttendanceSummary(Employee $employee, PayrollPeriod $period): array
    {
        $schedule = WorkSchedule::active();

        $startDate = $period->period_start->toDateString();
        $endDate = $period->period_end->toDateString();
        $today = AppTimezone::nowDisplay()->toDateString();

        if ($endDate > $today) {
            $endDate = $today;
        }

        if ($startDate > $endDate) {
            return [
                'late_count' => 0,
                'absent_days' => 0,
                'early_out_count' => 0,
                'over_break_count' => 0,
                'overtime_hours' => 0.0,
                'short_work_hours' => 0.0,
            ];
        }

        [$startUtc] = AppTimezone::dayBoundsUtc($startDate);
        [, $endUtc] = AppTimezone::dayBoundsUtc($endDate);

        $logs = AttendanceLog::query()
            ->select(['id', 'employee_id', 'attendance_type', 'event_time'])
            ->where('employee_id', $employee->id)
            ->whereBetween('event_time', [$startUtc, $endUtc])
            ->orderBy('event_time')
            ->get();

        $pivoted = $this->attendanceService->pivotByEmployeeAndDate(
            $logs,
            $schedule,
            collect([$employee]),
            $startUtc,
            $endUtc,
        );

        $overtimeMinutes = (int) $pivoted->sum(fn ($row) => (int) ($row['overtime_minutes'] ?? 0));
        $shortWorkMinutes = (int) $pivoted->sum(fn ($row) => (int) ($row['short_work_minutes'] ?? 0));

        return [
            'late_count' => $pivoted->where('is_late', true)->count(),
            'absent_days' => $pivoted->where('status', 'Tidak Masuk')->count(),
            'early_out_count' => $pivoted->where('is_early_out', true)->count(),
            'over_break_count' => $pivoted->where('is_over_break', true)->count(),
            'overtime_hours' => round($overtimeMinutes / 60, 2),
            'short_work_hours' => round($shortWorkMinutes / 60, 2),
        ];
    }

    private function createEntryDetails(PayrollEntry $entry, Employee $employee, PayrollSetting $settings): void
    {
        $baseSalary = (float) $entry->base_salary;

        $allowances = $employee->relationLoaded('employeeAllowances')
            ? $employee->employeeAllowances
            : $employee->employeeAllowances()->with('allowanceType')->get();

        foreach ($allowances as $allowance) {
            if ($allowance->allowanceType && $allowance->allowanceType->is_active === false) {
                continue;
            }

            PayrollEntryDetail::create([
                'payroll_entry_id' => $entry->id,
                'category' => 'allowance',
                'label' => $allowance->allowanceType->name ?? 'Tunjangan',
                'amount' => $allowance->amount,
            ]);
        }

        $deductions = $employee->relationLoaded('employeeDeductions')
            ? $employee->employeeDeductions
            : $employee->employeeDeductions()->with('deductionType')->get();

        foreach ($deductions as $deduction) {
            if ($deduction->deductionType && $deduction->deductionType->is_active === false) {
                continue;
            }

            $amount = ($deduction->deductionType->calculation_method ?? 'fixed') === 'percentage'
                ? $baseSalary * ((float) $deduction->value / 100)
                : (float) $deduction->value;

            PayrollEntryDetail::create([
                'payroll_entry_id' => $entry->id,
                'category' => 'deduction',
                'label' => $deduction->deductionType->name ?? 'Potongan',
                'amount' => $amount,
            ]);
        }

        foreach ($this->cashBonService->applyToEntry($entry, $employee) as $installment) {
            $bon = $installment->cashBon;
            PayrollEntryDetail::create([
                'payroll_entry_id' => $entry->id,
                'category' => 'cash_bon',
                'label' => 'Cash Bon cicilan '.$installment->sequence.'/'.($bon?->installment_count ?? '?'),
                'amount' => $installment->amount,
                'reference_id' => $installment->id,
                'reference_type' => 'cash_bon_installment',
            ]);
        }

        if ($entry->late_penalty > 0) {
            PayrollEntryDetail::create([
                'payroll_entry_id' => $entry->id,
                'category' => 'penalty',
                'label' => "Denda Terlambat ({$entry->late_count}x)",
                'amount' => $entry->late_penalty,
            ]);
        }

        if ($entry->absent_penalty > 0) {
            PayrollEntryDetail::create([
                'payroll_entry_id' => $entry->id,
                'category' => 'penalty',
                'label' => "Potongan Tidak Masuk ({$entry->absent_days} hari)",
                'amount' => $entry->absent_penalty,
            ]);
        }

        if ($entry->early_out_penalty > 0) {
            PayrollEntryDetail::create([
                'payroll_entry_id' => $entry->id,
                'category' => 'penalty',
                'label' => "Denda Pulang Cepat ({$entry->early_out_count}x)",
                'amount' => $entry->early_out_penalty,
            ]);
        }

        if (($entry->over_break_penalty ?? 0) > 0) {
            PayrollEntryDetail::create([
                'payroll_entry_id' => $entry->id,
                'category' => 'penalty',
                'label' => "Denda Over Break ({$entry->over_break_count}x)",
                'amount' => $entry->over_break_penalty,
            ]);
        }

        if (($entry->short_work_penalty ?? 0) > 0) {
            PayrollEntryDetail::create([
                'payroll_entry_id' => $entry->id,
                'category' => 'penalty',
                'label' => "Potongan Jam Kerja Kurang ({$entry->short_work_hours} jam)",
                'amount' => $entry->short_work_penalty,
            ]);
        }

        if ($entry->overtime_amount > 0) {
            PayrollEntryDetail::create([
                'payroll_entry_id' => $entry->id,
                'category' => 'overtime',
                'label' => "Lembur ({$entry->overtime_hours} jam)",
                'amount' => $entry->overtime_amount,
            ]);
        }

        if ($entry->pph21_amount > 0) {
            PayrollEntryDetail::create([
                'payroll_entry_id' => $entry->id,
                'category' => 'tax',
                'label' => 'PPh 21',
                'amount' => $entry->pph21_amount,
            ]);
        }
    }

    private function calculatePph21(float $monthlyTaxable): float
    {
        $yearly = $monthlyTaxable * 12;
        $ptkp = 54_000_000;
        $pkp = max(0, $yearly - $ptkp);

        if ($pkp <= 0) {
            return 0;
        }

        $tax = 0;
        $brackets = [
            [60_000_000, 0.05],
            [250_000_000, 0.15],
            [500_000_000, 0.25],
            [5_000_000_000, 0.30],
            [PHP_FLOAT_MAX, 0.35],
        ];

        $remaining = $pkp;
        $prevLimit = 0;
        foreach ($brackets as [$limit, $rate]) {
            $bracketSize = $limit - $prevLimit;
            $taxable = min($remaining, $bracketSize);
            $tax += $taxable * $rate;
            $remaining -= $taxable;
            $prevLimit = $limit;
            if ($remaining <= 0) {
                break;
            }
        }

        return round($tax / 12, 2);
    }
}
