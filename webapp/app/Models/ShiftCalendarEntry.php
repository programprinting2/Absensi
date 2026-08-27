<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftCalendarEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'work_date',
        'work_schedule_id',
        'group_id',
        'employee_id',
        'sort_order',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ShiftGroup::class, 'group_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
