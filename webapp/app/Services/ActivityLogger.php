<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ActivityLogger
{
    public static function normal(
        string $description,
        ?string $action = null,
        array $context = [],
        ?Request $request = null,
        ?User $user = null,
    ): void {
        self::log(ActivityLog::LEVEL_NORMAL, $description, $action, $context, $request, $user);
    }

    public static function medium(
        string $description,
        ?string $action = null,
        array $context = [],
        ?Request $request = null,
        ?User $user = null,
    ): void {
        self::log(ActivityLog::LEVEL_MEDIUM, $description, $action, $context, $request, $user);
    }

    public static function warning(
        string $description,
        ?string $action = null,
        array $context = [],
        ?Request $request = null,
        ?User $user = null,
    ): void {
        self::log(ActivityLog::LEVEL_WARNING, $description, $action, $context, $request, $user);
    }

    public static function log(
        string $level,
        string $description,
        ?string $action = null,
        array $context = [],
        ?Request $request = null,
        ?User $user = null,
    ): void {
        try {
            $request ??= request();
            $user ??= Auth::user();

            ActivityLog::query()->create([
                'level' => in_array($level, [
                    ActivityLog::LEVEL_NORMAL,
                    ActivityLog::LEVEL_MEDIUM,
                    ActivityLog::LEVEL_WARNING,
                ], true) ? $level : ActivityLog::LEVEL_NORMAL,
                'action' => $action ? mb_substr($action, 0, 120) : null,
                'description' => mb_substr(trim($description), 0, 2000),
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'ip_address' => $request?->ip(),
                'method' => $request?->method(),
                'url' => $request ? mb_substr($request->fullUrl(), 0, 500) : null,
                'context' => $context !== [] ? $context : null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Jangan ganggu alur utama jika logging gagal.
        }
    }

    public static function clear(?string $level = null): int
    {
        $query = ActivityLog::query();

        if ($level && in_array($level, [
            ActivityLog::LEVEL_NORMAL,
            ActivityLog::LEVEL_MEDIUM,
            ActivityLog::LEVEL_WARNING,
        ], true)) {
            $query->where('level', $level);
        }

        return $query->delete();
    }

    public static function inferLevelFromMethod(string $method): string
    {
        return match (strtoupper($method)) {
            'DELETE' => ActivityLog::LEVEL_WARNING,
            'PUT', 'PATCH' => ActivityLog::LEVEL_MEDIUM,
            default => ActivityLog::LEVEL_NORMAL,
        };
    }

    public static function describeRequest(Request $request): string
    {
        $route = $request->route()?->getName();
        $method = $request->method();
        $path = '/'.ltrim($request->path(), '/');

        if ($route) {
            return "{$method} {$route} ({$path})";
        }

        return "{$method} {$path}";
    }
}
