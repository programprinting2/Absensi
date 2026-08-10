<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftRotationSlot extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'plan_id',
        'phase_index',
        'shift_team_id',
        'work_schedule_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'phase_index' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ShiftRotationPlan::class, 'plan_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ShiftTeam::class, 'shift_team_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }
}
