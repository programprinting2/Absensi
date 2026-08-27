<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSwapRequest extends Model
{
    use HasUuids;

    public $timestamps = false;

    public const TYPE_MOVE = 'move';

    public const TYPE_PEER_SWAP = 'peer_swap';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const PEER_STATUS_PENDING = 'pending';

    public const PEER_STATUS_APPROVED = 'approved';

    public const PEER_STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'employee_id',
        'request_type',
        'counterparty_employee_id',
        'work_date',
        'to_work_schedule_id',
        'reason',
        'status',
        'peer_status',
        'peer_reviewed_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'peer_reviewed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'counterparty_employee_id');
    }

    public function toSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'to_work_schedule_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isMove(): bool
    {
        return $this->request_type === self::TYPE_MOVE;
    }

    public function isPeerSwap(): bool
    {
        return $this->request_type === self::TYPE_PEER_SWAP;
    }

    public function awaitsPeerApproval(): bool
    {
        return $this->isPeerSwap()
            && $this->status === self::STATUS_PENDING
            && $this->peer_status === self::PEER_STATUS_PENDING;
    }

    public function awaitsAdminApproval(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        if ($this->isMove()) {
            return true;
        }

        return $this->peer_status === self::PEER_STATUS_APPROVED;
    }

    public function scopeAwaitingAdminApproval($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where(function ($q) {
                $q->where('request_type', self::TYPE_MOVE)
                    ->orWhere(function ($q2) {
                        $q2->where('request_type', self::TYPE_PEER_SWAP)
                            ->where('peer_status', self::PEER_STATUS_APPROVED);
                    });
            });
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_MOVE => 'Pindah Shift',
            self::TYPE_PEER_SWAP => 'Tukar Shift',
            default => $type,
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'Menunggu',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_CANCELLED => 'Dibatalkan',
            default => $status,
        };
    }

    public function displayStatusLabel(): string
    {
        if ($this->awaitsPeerApproval()) {
            return 'Menunggu rekan';
        }

        if ($this->isPeerSwap() && $this->status === self::STATUS_PENDING && $this->peer_status === self::PEER_STATUS_APPROVED) {
            return 'Menunggu admin';
        }

        return self::statusLabel($this->status);
    }
}
