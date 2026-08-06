<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'employee_code',
        'full_name',
        'nik',
        'phone',
        'address',
        'position',
        'department',
        'join_date',
        'npwp',
        'bpjs_kes',
        'bpjs_tk',
        'bank_name',
        'bank_account',
        'bank_holder',
        'ptkp_status',
        'pin_salt',
        'pin_hash',
        'is_active',
    ];

    protected $hidden = [
        'pin_salt',
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'join_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function fingerprintTemplates()
    {
        return $this->hasMany(FingerprintTemplate::class);
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function salaries()
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function activeSalary()
    {
        // Avoid latestOfMany(): PostgreSQL cannot MAX(uuid) on the primary key.
        // Only one row should be is_active=true per employee (see EmployeeSalaryController).
        return $this->hasOne(EmployeeSalary::class)
            ->where('is_active', true)
            ->orderByDesc('effective_date');
    }

    /**
     * Jejak perubahan gaji pokok (terbaru di atas).
     *
     * @return array<int, array<string, mixed>>
     */
    public function salaryHistoryTimeline(): array
    {
        $salaries = $this->relationLoaded('salaries')
            ? $this->salaries->sortBy([
                fn ($a, $b) => $a->effective_date <=> $b->effective_date,
                fn ($a, $b) => strcmp((string) $a->id, (string) $b->id),
            ])->values()
            : $this->salaries()
                ->orderBy('effective_date')
                ->orderBy('id')
                ->get();

        $rows = [];
        $previous = null;

        foreach ($salaries as $index => $salary) {
            $amount = (float) $salary->base_salary;
            $prevAmount = $previous ? (float) $previous->base_salary : null;
            $change = $prevAmount === null ? null : $amount - $prevAmount;

            $monthsSincePrevious = null;
            if ($previous) {
                $monthsSincePrevious = (int) $previous->effective_date->diffInMonths($salary->effective_date);
            }

            $label = match (true) {
                $index === 0 => 'Gaji awal',
                $change > 0 => 'Kenaikan gaji',
                $change < 0 => 'Penurunan gaji',
                default => 'Penyesuaian tanggal',
            };

            $note = null;
            if ($index === 0) {
                $note = 'Gaji pertama dicatat';
            } elseif ($monthsSincePrevious !== null) {
                $note = $monthsSincePrevious === 0
                    ? 'Berubah di bulan yang sama'
                    : "{$monthsSincePrevious} bulan sejak perubahan sebelumnya";
            }

            $rows[] = [
                'id' => $salary->id,
                'base_salary' => $amount,
                'base_salary_label' => 'Rp '.number_format($amount, 0, ',', '.'),
                'effective_date' => $salary->effective_date->format('Y-m-d'),
                'effective_date_label' => $salary->effective_date->locale('id')->translatedFormat('j F Y'),
                'is_active' => (bool) $salary->is_active,
                'change' => $change,
                'change_label' => $change === null
                    ? null
                    : (($change >= 0 ? '+' : '−').'Rp '.number_format(abs($change), 0, ',', '.')),
                'months_since_previous' => $monthsSincePrevious,
                'label' => $label,
                'note' => $note,
            ];

            $previous = $salary;
        }

        return array_reverse($rows);
    }

    public function employeeAllowances()
    {
        return $this->hasMany(EmployeeAllowance::class);
    }

    public function employeeDeductions()
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    public function cashBons()
    {
        return $this->hasMany(CashBon::class);
    }

    public function payrollEntries()
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function portalUser()
    {
        return $this->hasOne(User::class);
    }
}
