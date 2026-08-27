<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftGroupMember extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'group_id',
        'employee_id',
        'effective_from',
        'effective_to',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ShiftGroup::class, 'group_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeActiveOn($query, string $date)
    {
        return $query
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });
    }
}
