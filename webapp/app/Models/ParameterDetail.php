<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParameterDetail extends Model
{
    use HasUuids;

    protected $fillable = [
        'parameter_id',
        'name',
        'value',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(Parameter::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
