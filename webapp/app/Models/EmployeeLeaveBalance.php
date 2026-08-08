<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveBalance extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'employee_id',
        'year',
        'entitlement_days',
        'used_days',
        'expired_days',
        'cashed_days',
        'cash_amount',
        'status',
        'notes',
        'closed_by',
        'closed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'entitlement_days' => 'integer',
            'used_days' => 'integer',
            'expired_days' => 'integer',
            'cashed_days' => 'integer',
            'cash_amount' => 'decimal:2',
            'closed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function remainingDays(): int
    {
        return max(0, $this->entitlement_days - $this->used_days - $this->expired_days - $this->cashed_days);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
