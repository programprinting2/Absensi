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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }
}
