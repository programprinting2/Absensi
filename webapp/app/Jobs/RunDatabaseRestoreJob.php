<?php

namespace App\Jobs;

use App\Services\DatabaseBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDatabaseRestoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;
    private $token;
    private $archivePath;
    private $userName;
    private $originalFilename;
    private $sourceType;
    private $driveFileId;

    public function __construct(string $token, ?string $archivePath, string $userName, ?string $originalFilename = null, string $sourceType = 'local', ?string $driveFileId = null)
    {
        $this->token = $token;
        $this->archivePath = $archivePath;
        $this->userName = $userName;
        $this->originalFilename = $originalFilename;
        $this->sourceType = $sourceType;
        $this->driveFileId = $driveFileId;
        $this->onConnection('database');
        $this->onQueue('database-tools');
    }

    public function handle(DatabaseBackupService $backupService): void
    {
        $backupService->runRestore(
            $this->token,
            $this->archivePath,
            $this->userName,
            $this->originalFilename,
            $this->sourceType,
            $this->driveFileId
        );
    }

    public function failed(\Throwable $exception): void
    {
        app(DatabaseBackupService::class)->setProgress(
            $this->token,
            -1,
            'Restore gagal.',
            null,
            $exception->getMessage(),
            [
                'operation' => 'restore',
                'user' => $this->userName,
                'source_type' => $this->sourceType,
                'source_label' => $this->sourceType === 'cloud' ? 'Google Drive' : 'Local Drive',
                'backup_date' => now()->format('d/m/Y H:i:s'),
                'stage' => 'failed',
                'stage_label' => 'Gagal',
                'queue' => 'database-tools',
                'duration_label' => '00:00',
            ]
        );
    }
}