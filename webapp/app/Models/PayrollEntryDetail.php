<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PayrollEntryDetail extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'payroll_entry_id',
        'category',
        'label',
        'amount',
        'reference_id',
        'reference_type',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function entry()
    {
        return $this->belongsTo(PayrollEntry::class, 'payroll_entry_id');
    }
}
