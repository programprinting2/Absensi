<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DeviceCommand extends Model
{
    use HasUuids;

    protected $fillable = [
        'device_id',
        'command_type',
        'payload',
        'status',
        'result',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
        ];
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
