<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DatabaseBackupController extends Controller
{
    private $backupService;

    public function __construct(DatabaseBackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    // GET /tools/database/progress/{token}
    public function progress(string $token)
    {
        session()->save(); // release session lock so polling never gets blocked
        return response()->json($this->backupService->readProgress($token));
    }

    // ── BACKUP ────────────────────────────────────────────────────────────
    // POST /tools/database/backup/start
    public function backupStart(Request $request)
    {
        $request->validate([
            'storage_type' => 'nullable|in:local,cloud',
            'backup_scope' => 'nullable|in:full,structure',
        ]);

        $token = 'bkp_' . Str::uuid()->toString();
        $user = Auth::user();
        $userName = $user->name ?? $user->username ?? 'System';
        $storageType = $request->input('storage_type', 'local');
        $backupScope = $request->input('backup_scope', 'full');
        session()->save();

        try {
            $this->backupService->queueBackup($token, $userName, $storageType, $backupScope);

            return response()->json([
                'token' => $token,
                'queued' => true,
                'storage_type' => $storageType,
                'backup_scope' => $backupScope,
                'message' => 'Backup disiapkan.',
            ]);
        } catch (\Throwable $e) {
            $this->backupService->failQueueing($token, 'backup', $userName, $e);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // POST /tools/database/backup/run
    // Menjalankan proses backup secara sinkron pada request terpisah agar progress
    // bisa dipantau realtime via polling, tanpa bergantung pada worker queue.
    public function backupRun(Request $request)
    {
        $payload = $request->validate([
            'token' => 'required|string|max:120',
            'storage_type' => 'nullable|in:local,cloud',
            'backup_scope' => 'nullable|in:full,structure',
        ]);

        $token = (string) $payload['token'];
        $user = Auth::user();
        $userName = $user->name ?? $user->username ?? 'System';
        $storageType = $payload['storage_type'] ?? 'local';
        $backupScope = $payload['backup_scope'] ?? 'full';

        // Lepas lock session agar request polling progress tetap jalan paralel.
        session()->save();

        @set_time_limit(0);
        @ignore_user_abort(true);

        try {
            $this->backupService->runBackup($token, $userName, $storageType, $backupScope);

            return response()->json(['success' => true, 'token' => $token]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // GET /tools/database/backup/download/{token}
    public function backupDownload(string $token)
    {
        $path = $this->backupService->progressPath($token);
        $filePath = null;

        if (file_exists($path)) {
            $data     = json_decode(file_get_contents($path), true);
            $filePath = $data['file'] ?? null;
        }

        if (!$filePath) {
            $filePath = Cache::get("db_backup_{$token}");
        }

        if (!$filePath || !file_exists($filePath)) abort(404);

        return response()->download($filePath, basename($filePath), ['Content-Type' => 'application/gzip']);
    }

    // ── RESTORE ───────────────────────────────────────────────────────────
    // POST /tools/database/restore/prepare
    // Menerima/menyalin file backup, membaca log.txt-nya, dan memvalidasi isi arsip
    // SEBELUM proses restore dijalankan. File backup disimpan untuk dipakai di restoreRun.
    public function restorePrepare(Request $request)
    {
        $request->validate([
            'source_type' => 'nullable|in:local,cloud',
            'restore_mode' => 'nullable|in:full,table',
            'backup_file' => 'nullable|file|max:204800',
            'drive_file_id' => 'nullable|string',
            'drive_file_name' => 'nullable|string|max:255',
            'drive_file_size' => 'nullable|integer|min:0',
        ]);

        $token = 'rst_' . Str::uuid()->toString();
        $user = Auth::user();
        $userName = $user->name ?? $user->username ?? 'System';
        $sourceType = $request->input('source_type', 'local');
        $restoreMode = $request->input('restore_mode', 'full');
        $tempDir = storage_path("app/restore_queue_{$token}");
        $archivePath = $tempDir . DIRECTORY_SEPARATOR . 'restore.tar.gz';

        session()->save();
        @set_time_limit(0);

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            if ($sourceType === 'cloud') {
                $driveFileId = $request->input('drive_file_id');
                if (!$driveFileId) {
                    $this->cleanupDir($tempDir);
                    return response()->json(['error' => 'Pilih file dari Google Drive terlebih dahulu.'], 422);
                }

                $driveFile = $this->backupService->downloadDriveArchive($driveFileId, $archivePath);
                $originalName = $request->input('drive_file_name') ?: ($driveFile['filename'] ?? 'Google Drive file');
                $sourceLabel = 'Google Drive';
            } else {
                $uploadedFile = $request->file('backup_file');
                if (!$uploadedFile) {
                    $this->cleanupDir($tempDir);
                    return response()->json(['error' => 'Pilih file backup dari local drive terlebih dahulu.'], 422);
                }

                $originalName = $uploadedFile->getClientOriginalName();
                $uploadedFile->move($tempDir, 'restore.tar.gz');
                $sourceLabel = 'Local Drive';
            }

            $info = $this->backupService->inspectArchive($archivePath);
            if (!$info['has_dump']) {
                $this->cleanupDir($tempDir);
                return response()->json(['error' => 'File backup tidak valid: dump.sql tidak ditemukan di dalam arsip.'], 422);
            }

            $fileSize = (int) filesize($archivePath);

            // Untuk mode "table": ambil daftar tabel + perbandingan jumlah record.
            $tables = [];
            if ($restoreMode === 'table') {
                $tables = $this->backupService->inspectBackupTables($archivePath, $tempDir);
            }

            file_put_contents($tempDir . DIRECTORY_SEPARATOR . 'meta.json', json_encode([
                'original_name' => $originalName,
                'source_type' => $sourceType,
                'source_label' => $sourceLabel,
                'file_size' => $fileSize,
                'restore_mode' => $restoreMode,
            ]));

            $this->backupService->queueRestore($token, $userName, $originalName, $fileSize, $sourceType);

            return response()->json([
                'token' => $token,
                'source_type' => $sourceType,
                'source_label' => $sourceLabel,
                'restore_mode' => $restoreMode,
                'file_name' => $originalName,
                'file_size' => $fileSize,
                'file_size_human' => $this->backupService->humanBytes($fileSize),
                'has_log' => $info['log'] !== null,
                'log' => $info['log'],
                'tables' => $tables,
            ]);
        } catch (\Throwable $e) {
            $this->cleanupDir($tempDir);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // POST /tools/database/restore/run
    // Menjalankan restore secara sinkron dari arsip yang sudah disiapkan restorePrepare,
    // sehingga progress bisa dipantau realtime tanpa bergantung pada worker queue.
    public function restoreRun(Request $request)
    {
        $payload = $request->validate([
            'token' => 'required|string|max:120',
            'restore_mode' => 'nullable|in:full,table',
            'tables' => 'nullable|array',
            'tables.*' => 'string|max:255',
        ]);

        $token = (string) $payload['token'];
        $restoreMode = $payload['restore_mode'] ?? 'full';
        $selectedTables = $payload['tables'] ?? [];
        $user = Auth::user();
        $userName = $user->name ?? $user->username ?? 'System';
        $tempDir = storage_path("app/restore_queue_{$token}");
        $archivePath = $tempDir . DIRECTORY_SEPARATOR . 'restore.tar.gz';

        if (!is_file($archivePath)) {
            return response()->json([
                'success' => false,
                'error' => 'Arsip restore tidak ditemukan atau sudah kedaluwarsa. Silakan ulangi dari awal.',
            ], 404);
        }

        $meta = [];
        $metaPath = $tempDir . DIRECTORY_SEPARATOR . 'meta.json';
        if (is_file($metaPath)) {
            $meta = json_decode((string) file_get_contents($metaPath), true) ?: [];
        }
        $originalName = $meta['original_name'] ?? basename($archivePath);
        $sourceLabel = $meta['source_label'] ?? 'Local Drive';

        // Lepas lock session agar polling progress tetap jalan paralel.
        session()->save();
        @set_time_limit(0);
        @ignore_user_abort(true);

        try {
            if ($restoreMode === 'table') {
                if (empty($selectedTables)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Pilih minimal satu tabel untuk direstore.',
                    ], 422);
                }

                $this->backupService->runTableRestore($token, $archivePath, $selectedTables, $userName, $originalName, $sourceLabel);
            } else {
                $this->backupService->runRestore($token, $archivePath, $userName, $originalName, 'local', null, $sourceLabel);
            }

            return response()->json(['success' => true, 'token' => $token]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function cleanupDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}
