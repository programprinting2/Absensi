<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PayrollEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'base_salary',
        'total_allowances',
        'total_deductions',
        'overtime_hours',
        'overtime_amount',
        'late_count',
        'late_penalty',
        'absent_days',
        'absent_penalty',
        'early_out_count',
        'early_out_penalty',
        'short_work_hours',
        'short_work_penalty',
        'over_break_count',
        'over_break_penalty',
        'pph21_amount',
        'gross_salary',
        'net_salary',
        'notes',
        'is_adjusted',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'total_allowances' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'overtime_amount' => 'decimal:2',
            'late_count' => 'integer',
            'late_penalty' => 'decimal:2',
            'absent_days' => 'integer',
            'absent_penalty' => 'decimal:2',
            'early_out_count' => 'integer',
            'early_out_penalty' => 'decimal:2',
            'short_work_hours' => 'decimal:2',
            'short_work_penalty' => 'decimal:2',
            'over_break_count' => 'integer',
            'over_break_penalty' => 'decimal:2',
            'pph21_amount' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'is_adjusted' => 'boolean',
        ];
    }

    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function details()
    {
        return $this->hasMany(PayrollEntryDetail::class);
    }
}
