<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EmployeeSalary extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'base_salary',
        'effective_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
