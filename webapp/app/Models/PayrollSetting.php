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
        'late_penalty_per_incident',
        'absent_penalty_per_day',
        'early_out_penalty_per_incident',
        'short_work_penalty_per_hour',
        'over_break_penalty_per_incident',
        'overtime_rate_per_hour',
        'enable_pph21',
        'pph21_method',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_start_day' => 'integer',
            'cutoff_end_day' => 'integer',
            'late_penalty_per_incident' => 'decimal:2',
            'absent_penalty_per_day' => 'decimal:2',
            'early_out_penalty_per_incident' => 'decimal:2',
            'short_work_penalty_per_hour' => 'decimal:2',
            'over_break_penalty_per_incident' => 'decimal:2',
            'overtime_rate_per_hour' => 'decimal:2',
            'enable_pph21' => 'boolean',
        ];
    }

    public static function active(): static
    {
        return static::firstOrCreate([], [
            'cutoff_start_day' => 1,
            'cutoff_end_day' => 31,
            'late_penalty_per_incident' => 0,
            'absent_penalty_per_day' => 0,
            'early_out_penalty_per_incident' => 0,
            'short_work_penalty_per_hour' => 0,
            'over_break_penalty_per_incident' => 0,
            'overtime_rate_per_hour' => 0,
            'enable_pph21' => false,
            'pph21_method' => 'gross',
        ]);
    }
}
