<?php

namespace App\Jobs;

use App\Services\DatabaseMigrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDatabaseMigrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 7200;

    private string $token;
    private string $userName;
    private array $sourceConfig;
    private array $destinationConfig;
    private string $mode;

    public function __construct(string $token, string $userName, array $sourceConfig, array $destinationConfig, string $mode = 'full')
    {
        $this->token = $token;
        $this->userName = $userName;
        $this->sourceConfig = $sourceConfig;
        $this->destinationConfig = $destinationConfig;
        $this->mode = $mode;
        $this->onConnection('database');
        $this->onQueue('database-tools');
    }

    public function handle(DatabaseMigrationService $migrationService): void
    {
        $migrationService->runMigration(
            $this->token,
            $this->userName,
            $this->sourceConfig,
            $this->destinationConfig,
            $this->mode
        );
    }

    public function failed(\Throwable $exception): void
    {
        app(DatabaseMigrationService::class)->setProgress(
            $this->token,
            -1,
            'Migration gagal.',
            null,
            $exception->getMessage(),
            [
                'operation' => 'migration',
                'user' => $this->userName,
                'migration_mode' => $this->mode,
                'migration_mode_label' => $this->mode === 'structure' ? 'DB Structure Only' : 'Full Migration (Structure + data)',
                'migration_date' => now()->format('d/m/Y H:i:s'),
                'stage' => 'failed',
                'stage_label' => 'Gagal',
                'queue' => 'database-tools',
                'duration_label' => '00:00',
            ]
        );
    }
}
