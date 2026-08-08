<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogHttpActivity
{
    /** @var list<string> */
    private array $skipExact = [
        'livewire/upload-file',
        'up',
    ];

    /** @var list<string> */
    private array $skipPrefixes = [
        'build/',
        'vendor/',
        'storage/',
        '_debugbar/',
        'sanctum/',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->shouldLog($request, $response)) {
            return;
        }

        $user = $request->user();
        if (! $user) {
            return;
        }

        [$level, $description, $action, $context] = $this->buildEntry($request, $response);

        ActivityLogger::log(
            level: $level,
            description: $description,
            action: $action,
            context: $context,
            request: $request,
            user: $user,
        );
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            // terminate masih bisa jalan di HTTP; console skip kecuali test.
        }

        $path = ltrim($request->path(), '/');

        foreach ($this->skipExact as $exact) {
            if ($path === $exact) {
                return false;
            }
        }

        foreach ($this->skipPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        // Jangan log polling Livewire tanpa aksi berarti.
        if ($path === 'livewire/update') {
            $calls = $this->extractLivewireCalls($request);

            return $calls !== [];
        }

        // GET hanya untuk login/logout-ish; selebihnya fokus mutasi.
        if ($request->isMethod('GET') || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return false;
        }

        return $response->getStatusCode() < 500;
    }

    /**
     * @return array{0: string, 1: string, 2: ?string, 3: array<string, mixed>}
     */
    private function buildEntry(Request $request, Response $response): array
    {
        $path = ltrim($request->path(), '/');
        $status = $response->getStatusCode();

        if ($path === 'livewire/update') {
            $calls = $this->extractLivewireCalls($request);
            $label = collect($calls)->map(fn ($c) => $c['component'].'::'.$c['method'])->implode(', ');
            $level = collect($calls)->contains(fn ($c) => preg_match('/delete|destroy|cancel|reset|clear|finalize|unfinalize/i', $c['method']))
                ? 'warning'
                : (collect($calls)->contains(fn ($c) => preg_match('/save|update|store|create|generate|adjust/i', $c['method']) ) ? 'medium' : 'normal');

            return [
                $level,
                'Livewire: '.$label,
                'livewire.update',
                [
                    'status' => $status,
                    'calls' => $calls,
                ],
            ];
        }

        $level = ActivityLogger::inferLevelFromMethod($request->method());
        if ($status >= 400) {
            $level = 'warning';
        }

        $route = $request->route()?->getName();

        return [
            $level,
            ActivityLogger::describeRequest($request).($status >= 400 ? " [HTTP {$status}]" : ''),
            $route,
            [
                'status' => $status,
            ],
        ];
    }

    /**
     * @return list<array{component: string, method: string}>
     */
    private function extractLivewireCalls(Request $request): array
    {
        $components = $request->input('components', []);
        if (! is_array($components)) {
            return [];
        }

        $calls = [];
        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            // snapshot is JSON string in Livewire 3
            $componentName = 'Component';
            $snapshot = $component['snapshot'] ?? null;
            if (is_string($snapshot)) {
                $decoded = json_decode($snapshot, true);
                $componentName = (string) ($decoded['memo']['name'] ?? 'Component');
            } elseif (is_array($snapshot)) {
                $componentName = (string) ($snapshot['memo']['name'] ?? 'Component');
            }

            foreach ($component['calls'] ?? [] as $call) {
                if (! is_array($call)) {
                    continue;
                }
                $method = (string) ($call['method'] ?? '');
                if ($method === '' || in_array($method, ['$set', '$refresh', '$commit'], true)) {
                    continue;
                }
                // Skip pure property sync noise
                if (str_starts_with($method, '$')) {
                    continue;
                }

                // Auth & clear-log punya keterangan eksplisit tersendiri.
                if (in_array($method, ['login', 'logout', 'clearLogs'], true)) {
                    continue;
                }

                $calls[] = [
                    'component' => $componentName,
                    'method' => $method,
                ];
            }
        }

        return $calls;
    }
}
