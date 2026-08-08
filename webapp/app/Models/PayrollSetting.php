<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'cutoff_start_day',
        'cutoff_end_day',
        'joint_leave_days',
        'annual_leave_days',
        'leave_cash_day_divisor',
        'late_penalty_per_incident',
        'absent_penalty_per_day',
        'early_out_penalty_per_incident',
        'short_work_penalty_per_hour',
        'over_break_penalty_per_incident',
        'overtime_rate_per_hour',
        'enable_pph21',
        'pph21_method',
        'slip_paper',
        'slip_margin_top_mm',
        'slip_margin_right_mm',
        'slip_margin_bottom_mm',
        'slip_margin_left_mm',
        'slip_fit_to_width',
        'slip_font',
        'slip_font_scale',
        'slip_width_mm',
        'slip_height_mm',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_start_day' => 'integer',
            'cutoff_end_day' => 'integer',
            'joint_leave_days' => 'integer',
            'annual_leave_days' => 'integer',
            'leave_cash_day_divisor' => 'integer',
            'late_penalty_per_incident' => 'decimal:2',
            'absent_penalty_per_day' => 'decimal:2',
            'early_out_penalty_per_incident' => 'decimal:2',
            'short_work_penalty_per_hour' => 'decimal:2',
            'over_break_penalty_per_incident' => 'decimal:2',
            'overtime_rate_per_hour' => 'decimal:2',
            'enable_pph21' => 'boolean',
            'slip_margin_top_mm' => 'decimal:1',
            'slip_margin_right_mm' => 'decimal:1',
            'slip_margin_bottom_mm' => 'decimal:1',
            'slip_margin_left_mm' => 'decimal:1',
            'slip_fit_to_width' => 'boolean',
            'slip_font_scale' => 'integer',
            'slip_width_mm' => 'decimal:1',
            'slip_height_mm' => 'decimal:1',
        ];
    }

    public static function active(): static
    {
        return static::firstOrCreate([], [
            'cutoff_start_day' => 1,
            'cutoff_end_day' => 31,
            'joint_leave_days' => 0,
            'annual_leave_days' => 12,
            'leave_cash_day_divisor' => 25,
            'late_penalty_per_incident' => 0,
            'absent_penalty_per_day' => 0,
            'early_out_penalty_per_incident' => 0,
            'short_work_penalty_per_hour' => 0,
            'over_break_penalty_per_incident' => 0,
            'overtime_rate_per_hour' => 0,
            'enable_pph21' => false,
            'pph21_method' => 'gross',
            'slip_paper' => 'thermal_15x10',
            'slip_margin_top_mm' => 3,
            'slip_margin_right_mm' => 3,
            'slip_margin_bottom_mm' => 3,
            'slip_margin_left_mm' => 3,
            'slip_fit_to_width' => true,
            'slip_font' => 'helvetica',
            'slip_font_scale' => 100,
            'slip_width_mm' => null,
            'slip_height_mm' => null,
        ]);
    }
}
