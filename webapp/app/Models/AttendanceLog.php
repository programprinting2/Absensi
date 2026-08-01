<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'employee_id',
        'attendance_type',
        'method',
        'event_time',
        'synced_at',
        'is_offline_capture',
        'client_uuid',
        'raw_notes',
    ];

    protected function casts(): array
    {
        return [
            'event_time' => 'datetime',
            'synced_at' => 'datetime',
            'is_offline_capture' => 'boolean',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
