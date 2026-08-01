<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FingerprintTemplate extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'device_id',
        'fingerprint_slot_id',
        'enrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
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
