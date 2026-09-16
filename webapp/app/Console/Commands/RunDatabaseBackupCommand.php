<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use App\Services\DatabaseScheduledBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RunDatabaseBackupCommand extends Command
{
    protected $signature = 'database:backup
                            {--storage=local : local|cloud}
                            {--scope=full : full|structure}
                            {--user=Scheduler : Nama user untuk log backup}';

    protected $description = 'Jalankan backup database (untuk CLI / scheduled task).';

    public function handle(
        DatabaseBackupService $backupService,
        DatabaseScheduledBackupService $scheduleService
    ): int {
        $storageType = (string) $this->option('storage');
        $backupScope = (string) $this->option('scope');
        $userName = (string) $this->option('user');

        if (!in_array($storageType, ['local', 'cloud'], true)) {
            $this->error('Opsi --storage harus local atau cloud.');

            return self::FAILURE;
        }

        if (!in_array($backupScope, ['full', 'structure'], true)) {
            $this->error('Opsi --scope harus full atau structure.');

            return self::FAILURE;
        }

        $token = 'bkp_cli_' . Str::uuid()->toString();

        $this->info('Memulai backup database (' . $backupScope . ', ' . $storageType . ')...');

        try {
            $backupService->queueBackup($token, $userName, $storageType, $backupScope);
            $backupService->runBackup($token, $userName, $storageType, $backupScope, 'scheduled');

            $progress = $backupService->readProgress($token);

            if (!empty($progress['error'])) {
                $scheduleService->recordRun(false, (string) $progress['error']);
                $this->error('Backup gagal: ' . $progress['error']);

                return self::FAILURE;
            }

            $file = (string) ($progress['file'] ?? '-');
            $scheduleService->recordRun(true, 'Backup selesai', $file);
            $this->info('Backup selesai: ' . $file);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $scheduleService->recordRun(false, $e->getMessage());
            $this->error('Backup gagal: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
