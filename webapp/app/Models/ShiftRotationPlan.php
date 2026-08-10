<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftRotationPlan extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'start_date',
        'phase_work_days',
        'phase_count',
        'schedule_a_id',
        'schedule_b_id',
        'is_active',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'phase_work_days' => 'integer',
            'phase_count' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ShiftRotationSlot::class, 'plan_id');
    }

    public function scheduleA(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'schedule_a_id');
    }

    public function scheduleB(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'schedule_b_id');
    }

    public static function active(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->with(['scheduleA', 'scheduleB'])
            ->first();
    }
}
