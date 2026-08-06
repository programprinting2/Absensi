<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AllowanceType extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'is_fixed',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_fixed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
