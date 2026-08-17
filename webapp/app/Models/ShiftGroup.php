<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftGroup extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'color',
        'is_system_unassigned',
        'is_solo',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_system_unassigned' => 'boolean',
            'is_solo' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(ShiftGroupMember::class, 'group_id');
    }

    public function calendarEntries(): HasMany
    {
        return $this->hasMany(ShiftCalendarEntry::class, 'group_id');
    }

    public static function unassigned(): ?self
    {
        return once(fn () => static::query()->where('is_system_unassigned', true)->first());
    }

    public function displayName(): string
    {
        return $this->is_solo ? $this->name : $this->name;
    }
}
