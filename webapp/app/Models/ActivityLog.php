<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    public const LEVEL_NORMAL = 'normal';

    public const LEVEL_MEDIUM = 'medium';

    public const LEVEL_WARNING = 'warning';

    protected $fillable = [
        'level',
        'action',
        'description',
        'user_id',
        'user_name',
        'ip_address',
        'method',
        'url',
        'context',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function levelLabel(): string
    {
        return match ($this->level) {
            self::LEVEL_MEDIUM => 'Medium',
            self::LEVEL_WARNING => 'Warning',
            default => 'Normal',
        };
    }
}
