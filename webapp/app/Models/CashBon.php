<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CashBon extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'amount',
        'installment_count',
        'installment_amount',
        'remaining_amount',
        'disbursed_at',
        'notes',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'disbursed_at' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function installments()
    {
        return $this->hasMany(CashBonInstallment::class)->orderBy('sequence');
    }

    public function refreshRemaining(): void
    {
        $remaining = (float) $this->installments()
            ->where('status', 'pending')
            ->sum('amount');

        $allSettled = ! $this->installments()
            ->whereIn('status', ['pending', 'deducted'])
            ->exists();

        $status = $this->status;
        if ($this->status !== 'cancelled' && $allSettled) {
            $status = 'paid';
        } elseif ($this->status === 'paid' && ! $allSettled) {
            $status = 'active';
        }

        $this->update([
            'remaining_amount' => $remaining,
            'status' => $status,
        ]);
    }
}
