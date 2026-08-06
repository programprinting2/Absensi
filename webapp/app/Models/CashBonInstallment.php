<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CashBonInstallment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'cash_bon_id',
        'sequence',
        'amount',
        'status',
        'payroll_entry_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function cashBon()
    {
        return $this->belongsTo(CashBon::class);
    }

    public function payrollEntry()
    {
        return $this->belongsTo(PayrollEntry::class);
    }
}
