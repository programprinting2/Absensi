<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeave extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const TYPE_TAHUNAN = 'tahunan';

    public const TYPE_SAKIT = 'sakit';

    public const TYPE_PENTING = 'penting';

    public const TYPE_LAINNYA = 'lainnya';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'leave_type',
        'start_date',
        'end_date',
        'days_count',
        'reason',
        'status',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days_count' => 'integer',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_TAHUNAN => 'Cuti tahunan',
            self::TYPE_SAKIT => 'Cuti sakit',
            self::TYPE_PENTING => 'Cuti penting / keperluan mendesak',
            self::TYPE_LAINNYA => 'Lainnya',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->leave_type] ?? ucfirst($this->leave_type);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_CANCELLED => 'Dibatalkan',
            default => 'Menunggu',
        };
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
