<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'clock_in_time',
        'clock_out_time',
        'break_duration_minutes',
        'work_duration_minutes',
        'late_after_time',
        'crosses_midnight',
        'is_active',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_enabled' => 'boolean',
            'crosses_midnight' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Jadwal default perusahaan (fallback jika karyawan belum punya assignment).
     */
    public static function active(): ?self
    {
        return once(fn () => static::where('is_active', true)->first());
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function assignments()
    {
        return $this->hasMany(EmployeeShiftAssignment::class, 'work_schedule_id');
    }
}
