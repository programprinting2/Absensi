<?php

namespace App\Jobs;

use App\Services\DatabaseBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    private string $token;
    private string $userName;
    private string $storageType;
    private string $backupScope;

    public function __construct(string $token, string $userName, string $storageType = 'local', string $backupScope = 'full')
    {
        $this->token = $token;
        $this->userName = $userName;
        $this->storageType = $storageType;
        $this->backupScope = $backupScope;
        $this->onConnection('database');
        $this->onQueue('database-tools');
    }

    public function handle(DatabaseBackupService $backupService): void
    {
        $backupService->runBackup(
            $this->token,
            $this->userName,
            $this->storageType,
            $this->backupScope
        );
    }

    public function failed(\Throwable $exception): void
    {
        app(DatabaseBackupService::class)->setProgress(
            $this->token,
            -1,
            'Backup gagal.',
            null,
            $exception->getMessage(),
            [
                'operation' => 'backup',
                'user' => $this->userName,
                'storage_type' => $this->storageType,
                'backup_scope' => $this->backupScope,
                'backup_scope_label' => $this->backupScope === 'structure' ? 'Database Structure' : 'Full Backup',
                'backup_date' => now()->format('d/m/Y H:i:s'),
                'stage' => 'failed',
                'stage_label' => 'Gagal',
                'queue' => 'database-tools',
                'duration_label' => '00:00',
            ]
        );
    }
}