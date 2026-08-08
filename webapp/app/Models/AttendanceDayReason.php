<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDayReason extends Model
{
    use HasUuids;

    protected $fillable = [
        'employee_id',
        'work_date',
        'clock_in_reason',
        'break_start_reason',
        'break_end_reason',
        'clock_out_reason',
        'day_reason',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function hasAnyReason(): bool
    {
        return filled($this->clock_in_reason)
            || filled($this->break_start_reason)
            || filled($this->break_end_reason)
            || filled($this->clock_out_reason)
            || filled($this->day_reason);
    }
}
