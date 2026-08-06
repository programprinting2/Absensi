<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EmployeeAllowance extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'allowance_type_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function allowanceType()
    {
        return $this->belongsTo(AllowanceType::class);
    }
}
