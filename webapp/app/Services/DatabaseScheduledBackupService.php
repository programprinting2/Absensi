<?php

namespace App\Services;

class DatabaseScheduledBackupService
{
    public const TASK_NAME = 'Absensi_DatabaseBackup';

    public const CRON_MARKER = '# absensi-scheduled-backup';

    private string $configPath;

    public function __construct()
    {
        $this->configPath = storage_path('app/backup_schedule.json');
    }

    public function detectOs(): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => 'windows',
            'Linux' => 'linux',
            default => 'unknown',
        };
    }

    public function defaultConfig(): array
    {
        return [
            'enabled' => false,
            'frequency' => 'daily',
            'time' => '02:00',
            'weekday' => 1,
            'storage_type' => 'local',
            'backup_scope' => 'full',
            'installed' => false,
            'last_run_at' => null,
            'last_status' => null,
            'last_message' => null,
            'last_file' => null,
            'updated_at' => null,
        ];
    }

    public function loadConfig(): array
    {
        if (!is_file($this->configPath)) {
            return $this->defaultConfig();
        }

        $data = json_decode((string) file_get_contents($this->configPath), true);

        return array_merge($this->defaultConfig(), is_array($data) ? $data : []);
    }

    public function saveConfig(array $payload): array
    {
        $config = array_merge($this->loadConfig(), [
            'enabled' => (bool) ($payload['enabled'] ?? false),
            'frequency' => in_array($payload['frequency'] ?? '', ['hourly', 'daily', 'weekly'], true)
                ? $payload['frequency']
                : 'daily',
            'time' => $this->normalizeTime((string) ($payload['time'] ?? '02:00')),
            'weekday' => max(0, min(6, (int) ($payload['weekday'] ?? 1))),
            'storage_type' => ($payload['storage_type'] ?? 'local') === 'cloud' ? 'cloud' : 'local',
            'backup_scope' => ($payload['backup_scope'] ?? 'full') === 'structure' ? 'structure' : 'full',
            'updated_at' => now()->toIso8601String(),
        ]);

        file_put_contents($this->configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

        return $config;
    }

    public function recordRun(bool $success, string $message, ?string $file = null): void
    {
        $config = $this->loadConfig();
        $config['last_run_at'] = now()->toIso8601String();
        $config['last_status'] = $success ? 'success' : 'failed';
        $config['last_message'] = $message;
        $config['last_file'] = $file;

        file_put_contents($this->configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public function getStatus(): array
    {
        $config = $this->loadConfig();
        $os = $this->detectOs();
        $installed = $this->isJobInstalled();

        // Sinkronkan file config dengan kondisi scheduler OS (cegah badge/toggle tidak selaras).
        if ($installed !== (bool) ($config['enabled'] ?? false)
            || $installed !== (bool) ($config['installed'] ?? false)) {
            $config['enabled'] = $installed;
            $config['installed'] = $installed;
            file_put_contents($this->configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        }

        $preview = $this->buildPreview($config);

        return [
            'os' => $os,
            'os_label' => $this->osLabel($os),
            'scheduler_type' => $os === 'windows' ? 'Task Scheduler' : ($os === 'linux' ? 'Cron' : 'Manual'),
            'installed' => $installed,
            'config' => $config,
            'preview' => $preview,
            'php_binary' => $this->resolvePhpBinary(),
            'project_path' => base_path(),
            'local_backup_dir' => storage_path('app'),
            'local_backup_pattern' => 'backup_*.tar.gz',
            'scheduled_log' => storage_path('logs/scheduled-backup.log'),
            'cloud_storage_label' => trim((string) env('GOOGLE_DRIVE_FOLDER_ID', '')) !== ''
                ? 'Google Drive (folder ID: ' . env('GOOGLE_DRIVE_FOLDER_ID') . ')'
                : 'Google Drive (atur GOOGLE_DRIVE_FOLDER_ID di .env)',
        ];
    }

    public function applySchedule(array $payload): array
    {
        $config = $this->saveConfig($payload);
        $os = $this->detectOs();

        if (!$config['enabled']) {
            return $this->removeSchedule();
        }

        if ($os === 'unknown') {
            return [
                'success' => false,
                'error' => 'OS server tidak dikenali. Gunakan Windows atau Linux.',
                'config' => $config,
            ];
        }

        $this->writeRunnerScript($config);

        if ($os === 'windows') {
            $result = $this->applyWindowsTask($config);
        } else {
            $result = $this->applyLinuxCron($config);
        }

        $config['installed'] = $result['success'];
        file_put_contents($this->configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

        return array_merge($result, [
            'config' => $config,
            'preview' => $this->buildPreview($config),
        ]);
    }

    public function removeSchedule(): array
    {
        $config = $this->loadConfig();
        $os = $this->detectOs();

        if ($os === 'windows') {
            $this->runShell('schtasks /Delete /TN "' . self::TASK_NAME . '" /F 2>nul');
        } elseif ($os === 'linux') {
            $this->removeLinuxCronEntry();
        }

        $config['enabled'] = false;
        $config['installed'] = false;
        $config['updated_at'] = now()->toIso8601String();
        file_put_contents($this->configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

        return [
            'success' => true,
            'message' => 'Scheduled backup dinonaktifkan.',
            'config' => $config,
            'installed' => false,
        ];
    }

    public function isJobInstalled(): bool
    {
        $os = $this->detectOs();

        if ($os === 'windows') {
            exec('schtasks /Query /TN "' . self::TASK_NAME . '" 2>nul', $output, $code);

            return $code === 0;
        }

        if ($os === 'linux') {
            $crontab = $this->readCrontab();

            return str_contains($crontab, self::CRON_MARKER);
        }

        return false;
    }

    private function applyWindowsTask(array $config): array
    {
        $this->runShell('schtasks /Delete /TN "' . self::TASK_NAME . '" /F 2>nul');

        $script = $this->runnerScriptPath();
        $schedule = $this->windowsScheduleArgs($config);
        $command = 'schtasks /Create /TN "' . self::TASK_NAME . '" /TR ' . escapeshellarg($script) . ' ' . $schedule . ' /F';

        $output = $this->runShell($command);

        if (!$this->isJobInstalled()) {
            return [
                'success' => false,
                'error' => 'Gagal membuat Task Scheduler. Pastikan aplikasi dijalankan dengan hak akses yang cukup.',
                'output' => $output,
                'command' => $command,
            ];
        }

        return [
            'success' => true,
            'message' => 'Task Scheduler berhasil dibuat (' . self::TASK_NAME . ').',
            'output' => $output,
            'command' => $command,
        ];
    }

    private function applyLinuxCron(array $config): array
    {
        $line = $this->buildCronLine($config);
        $crontab = $this->readCrontab();
        $lines = array_values(array_filter(
            explode("\n", str_replace("\r", '', $crontab)),
            fn (string $row) => !str_contains($row, self::CRON_MARKER)
        ));

        $lines[] = trim($line) . ' ' . self::CRON_MARKER;
        $newCrontab = implode("\n", $lines) . "\n";

        $temp = tempnam(sys_get_temp_dir(), 'printing-cron-');
        file_put_contents($temp, $newCrontab);

        $output = $this->runShell('crontab ' . escapeshellarg($temp));
        @unlink($temp);

        if (!$this->isJobInstalled()) {
            return [
                'success' => false,
                'error' => 'Gagal menginstal cron. Pastikan user web server memiliki akses crontab.',
                'output' => $output,
                'cron_line' => $line,
            ];
        }

        return [
            'success' => true,
            'message' => 'Cron job berhasil diinstal.',
            'output' => $output,
            'cron_line' => $line,
        ];
    }

    private function removeLinuxCronEntry(): void
    {
        $crontab = $this->readCrontab();
        $lines = array_values(array_filter(
            explode("\n", str_replace("\r", '', $crontab)),
            fn (string $row) => !str_contains($row, self::CRON_MARKER)
        ));

        $newCrontab = implode("\n", $lines);
        if ($newCrontab !== '') {
            $newCrontab .= "\n";
        }

        $temp = tempnam(sys_get_temp_dir(), 'printing-cron-');
        file_put_contents($temp, $newCrontab);
        $this->runShell('crontab ' . escapeshellarg($temp));
        @unlink($temp);
    }

    private function readCrontab(): string
    {
        $output = $this->runShell('crontab -l 2>/dev/null');

        return is_string($output) ? $output : '';
    }

    private function writeRunnerScript(array $config): void
    {
        $script = $this->runnerScriptPath();
        $php = $this->resolvePhpBinary();
        $artisan = base_path('artisan');
        $log = storage_path('logs/scheduled-backup.log');
        $storage = $config['storage_type'];
        $scope = $config['backup_scope'];

        if ($this->detectOs() === 'windows') {
            $content = implode("\r\n", [
                '@echo off',
                'cd /d "' . str_replace('/', '\\', base_path()) . '"',
                '"' . $php . '" "' . str_replace('/', '\\', $artisan) . '" database:backup --storage=' . $storage . ' --scope=' . $scope . ' >> "' . str_replace('/', '\\', $log) . '" 2>&1',
            ]) . "\r\n";
        } else {
            $content = implode("\n", [
                '#!/bin/bash',
                'cd "' . base_path() . '"',
                escapeshellarg($php) . ' ' . escapeshellarg($artisan) . ' database:backup --storage=' . escapeshellarg($storage) . ' --scope=' . escapeshellarg($scope) . ' >> ' . escapeshellarg($log) . ' 2>&1',
                '',
            ]);
        }

        file_put_contents($script, $content, LOCK_EX);

        if ($this->detectOs() === 'linux') {
            @chmod($script, 0755);
        }
    }

    public function runnerScriptPath(): string
    {
        $ext = $this->detectOs() === 'windows' ? 'bat' : 'sh';

        return storage_path('app/run-scheduled-backup.' . $ext);
    }

    private function buildCronLine(array $config): string
    {
        $expr = $this->buildCronExpression($config);
        $script = $this->runnerScriptPath();

        return $expr . ' ' . escapeshellarg($script);
    }

    private function buildCronExpression(array $config): string
    {
        [$hour, $minute] = $this->timeParts($config['time']);

        return match ($config['frequency']) {
            'hourly' => sprintf('%d * * * *', $minute),
            'weekly' => sprintf('%d %d * * %d', $minute, $hour, (int) $config['weekday']),
            default => sprintf('%d %d * * *', $minute, $hour),
        };
    }

    private function windowsScheduleArgs(array $config): string
    {
        [$hour, $minute] = $this->timeParts($config['time']);
        $time = sprintf('%02d:%02d', $hour, $minute);

        return match ($config['frequency']) {
            'hourly' => '/SC HOURLY /MO 1 /ST ' . $time,
            'weekly' => '/SC WEEKLY /D ' . $this->windowsWeekday((int) $config['weekday']) . ' /ST ' . $time,
            default => '/SC DAILY /ST ' . $time,
        };
    }

    private function buildPreview(array $config): array
    {
        $os = $this->detectOs();

        return [
            'runner_script' => $this->runnerScriptPath(),
            'cron_expression' => $this->buildCronExpression($config),
            'cron_line' => $os === 'linux' ? $this->buildCronLine($config) . ' ' . self::CRON_MARKER : null,
            'task_name' => self::TASK_NAME,
            'schedule_label' => $this->scheduleLabel($config),
        ];
    }

    private function scheduleLabel(array $config): string
    {
        $time = $config['time'];

        return match ($config['frequency']) {
            'hourly' => 'Setiap jam pada menit ke-' . $this->timeParts($time)[1],
            'weekly' => 'Setiap ' . $this->weekdayLabel((int) $config['weekday']) . ' pukul ' . $time,
            default => 'Setiap hari pukul ' . $time,
        };
    }

    private function weekdayLabel(int $day): string
    {
        return ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$day] ?? 'Senin';
    }

    private function windowsWeekday(int $day): string
    {
        return ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'][$day] ?? 'MON';
    }

    private function osLabel(string $os): string
    {
        return match ($os) {
            'windows' => 'Windows (Task Scheduler)',
            'linux' => 'Linux (Cron)',
            default => 'Tidak dikenali',
        };
    }

    private function normalizeTime(string $time): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
            return sprintf('%02d:%02d', min(23, (int) $m[1]), min(59, (int) $m[2]));
        }

        return '02:00';
    }

    private function timeParts(string $time): array
    {
        [$hour, $minute] = array_map('intval', explode(':', $this->normalizeTime($time)));

        return [$hour, $minute];
    }

    private function resolvePhpBinary(): string
    {
        $configured = trim((string) env('BACKUP_PHP_BINARY', ''));

        if ($configured !== '') {
            return $configured;
        }

        if (defined('PHP_BINARY') && PHP_BINARY !== '') {
            return PHP_BINARY;
        }

        return 'php';
    }

    private function runShell(string $command): string
    {
        $output = [];
        exec($command, $output, $code);

        return trim(implode("\n", $output));
    }
}
