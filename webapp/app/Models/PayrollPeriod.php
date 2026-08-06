<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PayrollPeriod extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'period_start',
        'period_end',
        'label',
        'status',
        'generated_at',
        'finalized_at',
        'finalized_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'generated_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function entries()
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isReview(): bool
    {
        return $this->status === 'review';
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }
}
