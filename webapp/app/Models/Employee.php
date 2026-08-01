<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'employee_code',
        'full_name',
        'pin_salt',
        'pin_hash',
        'is_active',
    ];

    protected $hidden = [
        'pin_salt',
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function fingerprintTemplates()
    {
        return $this->hasMany(FingerprintTemplate::class);
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
