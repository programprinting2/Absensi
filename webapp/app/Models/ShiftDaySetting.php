<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ShiftDaySetting extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const HOLIDAY_ROUTINE = 'routine';

    public const HOLIDAY_EVENT = 'event';

    protected $fillable = [
        'work_date',
        'is_company_holiday',
        'holiday_kind',
        'work_duration_minutes',
        'break_duration_minutes',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'is_company_holiday' => 'boolean',
            'work_duration_minutes' => 'integer',
            'break_duration_minutes' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
