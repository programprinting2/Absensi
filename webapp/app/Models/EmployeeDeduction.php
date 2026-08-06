<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EmployeeDeduction extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'deduction_type_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function deductionType()
    {
        return $this->belongsTo(DeductionType::class);
    }
}
