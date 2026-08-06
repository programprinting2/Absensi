<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'company_name',
        'trade_name',
        'npwp',
        'nib',
        'address',
        'city',
        'province',
        'postal_code',
        'phone',
        'email',
        'website',
        'owner_name',
        'display_timezone',
    ];

    public static function active(): static
    {
        return static::firstOrCreate([], [
            'display_timezone' => config('app.display_timezone', 'Asia/Jakarta'),
        ]);
    }
}
