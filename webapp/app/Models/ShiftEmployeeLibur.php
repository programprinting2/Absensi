<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftEmployeeLibur extends Model
{
    use HasUuids;

    protected $table = 'shift_employee_libur';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'work_date',
        'source',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
