<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ShiftScheduleTemplate extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'is_default',
        'is_active',
        'payload',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'payload' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function defaultTemplate(): ?self
    {
        return once(fn () => static::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first());
    }
}
