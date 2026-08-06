<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DeductionType extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'calculation_method',
        'default_value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
