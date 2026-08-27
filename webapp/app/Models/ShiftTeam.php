<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftTeam extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'shift_team_members')
            ->withPivot('id')
            ->orderBy('full_name');
    }

    public function memberRows(): HasMany
    {
        return $this->hasMany(ShiftTeamMember::class);
    }
}
