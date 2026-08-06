<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parameter extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
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

    public function details(): HasMany
    {
        return $this->hasMany(ParameterDetail::class)->orderBy('sort_order')->orderBy('name');
    }

    public function activeDetails(): HasMany
    {
        return $this->details()->where('is_active', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
