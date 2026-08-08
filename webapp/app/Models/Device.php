<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'device_code',
        'name',
        'location',
        'firmware_version',
        'last_seen_at',
        'last_ip',
        'is_active',
        'fingerprint_capacity',
        'lcd_config',
    ];

    protected $hidden = [
        'device_secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'created_at' => 'datetime',
            'lcd_config' => 'array',
        ];
    }

    public function commands()
    {
        return $this->hasMany(DeviceCommand::class);
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function fingerprintTemplates()
    {
        return $this->hasMany(FingerprintTemplate::class);
    }
}
