<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftEmployeeShiftOverride extends Model
{
    use HasUuids;

    protected $table = 'shift_employee_shift_overrides';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'work_date',
        'work_schedule_id',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }
}
