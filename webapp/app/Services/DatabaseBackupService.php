<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DatabaseBackupService
{
    private $googleDriveStorage;

    public function __construct(GoogleDriveStorageService $googleDriveStorage)
    {
        $this->googleDriveStorage = $googleDriveStorage;
    }

    public function progressPath(string $token): string
    {
        return storage_path("app/dbprogress_{$token}.json");
    }

    public function readProgress(string $token): array
    {
        $path = $this->progressPath($token);

        if (!file_exists($path)) {
            return [
                'progress' => 0,
                'message' => 'Memulai...',
                'file' => null,
                'error' => null,
                'meta' => [],
            ];
        }

        return json_decode(file_get_contents($path), true) ?: [
            'progress' => 0,
            'message' => 'Memulai...',
            'file' => null,
            'error' => 'Status progress tidak valid.',
            'meta' => [],
        ];
    }

    public function setProgress(
        string $token,
        int $pct,
        string $msg,
        ?string $file = null,
        ?string $err = null,
        ?array $meta = null
    ): void {
        file_put_contents(
            $this->progressPath($token),
            json_encode([
                'progress' => $pct,
                'message' => $msg,
                'file' => $file,
                'error' => $err,
                'meta' => $meta ?? [],
            ]),
            LOCK_EX
        );
    }

    public function queueBackup(string $token, string $userName, string $storageType = 'local', string $backupScope = 'full'): void
    {
        $queuedAt = now();
        $backupScopeLabel = $backupScope === 'structure' ? 'Database Structure' : 'Full Backup';

        $this->setProgress($token, 1, 'Backup disiapkan, proses akan segera dimulai...', null, null, [
            'operation' => 'backup',
            'user' => $userName,
            'storage_type' => $storageType,
            'storage_label' => $storageType === 'cloud' ? 'Google Drive' : 'Local Storage',
            'backup_scope' => $backupScope,
            'backup_scope_label' => $backupScopeLabel,
            'backup_date' => $queuedAt->format('d/m/Y H:i:s'),
            'stage' => 'preparing',
            'stage_label' => 'Menyiapkan proses',
            'queued_at' => $queuedAt->format('d/m/Y H:i:s'),
            'duration_label' => '00:00',
        ]);
    }

    public function queueRestore(string $token, string $userName, string $originalFilename, int $fileSize, string $sourceType = 'local'): void
    {
        $queuedAt = now();

        $message = $sourceType === 'cloud'
            ? 'File Google Drive dipilih. Restore masuk antrian...'
            : 'File diterima. Restore masuk antrian...';

        $this->setProgress($token, 5, $message, null, null, [
            'operation' => 'restore',
            'user' => $userName,
            'source_type' => $sourceType,
            'source_label' => $sourceType === 'cloud' ? 'Google Drive' : 'Local Drive',
            'backup_date' => $queuedAt->format('d/m/Y H:i:s'),
            'stage' => 'queued',
            'stage_label' => 'Masuk antrian',
            'queued_at' => $queuedAt->format('d/m/Y H:i:s'),
            'queue' => 'database-tools',
            'file_name' => $originalFilename,
            'file_size' => $fileSize,
            'file_size_human' => $this->formatBytes($fileSize),
            'duration_label' => '00:00',
        ]);
    }

    public function failQueueing(string $token, string $operation, string $userName, \Throwable $e): void
    {
        $this->setProgress($token, -1, ucfirst($operation) . ' gagal dimasukkan ke antrian.', null, $e->getMessage(), [
            'operation' => $operation,
            'user' => $userName,
            'backup_date' => now()->format('d/m/Y H:i:s'),
            'stage' => 'failed',
            'stage_label' => 'Gagal',
            'queue' => 'database-tools',
            'duration_label' => '00:00',
        ]);
    }

    public function runBackup(string $token, string $userName, string $storageType = 'local', string $backupScope = 'full'): void
    {
        $startedAt = now();
        $startedTs = microtime(true);

        if (!in_array($storageType, ['local', 'cloud'], true)) {
            throw new \InvalidArgumentException('Storage type backup tidak valid.');
        }

        if (!in_array($backupScope, ['full', 'structure'], true)) {
            throw new \InvalidArgumentException('Backup scope tidak valid.');
        }

        $backupScopeLabel = $backupScope === 'structure' ? 'Database Structure' : 'Full Backup';

        try {
            $this->setProgress($token, 2, 'Inisialisasi backup...', null, null, [
                'operation' => 'backup',
                'user' => $userName,
                'storage_type' => $storageType,
                'backup_scope' => $backupScope,
                'backup_scope_label' => $backupScopeLabel,
                'backup_date' => $startedAt->format('d/m/Y H:i:s'),
                'stage' => 'initializing',
                'stage_label' => 'Inisialisasi',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]);

            $this->setProgress($token, 5, 'Mengambil daftar tabel...', null, null, [
                'operation' => 'backup',
                'user' => $userName,
                'storage_type' => $storageType,
                'backup_scope' => $backupScope,
                'backup_scope_label' => $backupScopeLabel,
                'backup_date' => $startedAt->format('d/m/Y H:i:s'),
                'stage' => 'loading_tables',
                'stage_label' => 'Membaca daftar tabel',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]);

            $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE' ORDER BY table_name");
            $tableNames = collect($tables)->pluck('table_name')->toArray();
            $total = count($tableNames);
            $timestamp = now()->format('Ymd_His');

            $tempDir = storage_path("app/backup_tmp_{$timestamp}");
            $tarPath = storage_path("app/backup_{$timestamp}.tar");
            $targzPath = $tarPath . '.gz';

            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            if (!is_dir($tempDir . '/csv')) {
                mkdir($tempDir . '/csv', 0755, true);
            }

            $baseMeta = [
                'operation' => 'backup',
                'user' => $userName,
                'storage_type' => $storageType,
                'storage_label' => $storageType === 'cloud' ? 'Google Drive' : 'Local Storage',
                'backup_scope' => $backupScope,
                'backup_scope_label' => $backupScopeLabel,
                'backup_date' => $startedAt->format('d/m/Y H:i:s'),
                'total_tables' => $total,
                'queue' => 'database-tools',
            ];

            $this->setProgress($token, 10, 'Menyiapkan folder backup...', null, null, array_merge($baseMeta, [
                'stage' => 'preparing',
                'stage_label' => 'Menyiapkan folder',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]));

            $recentTables = [];

            if ($backupScope === 'structure') {
                $this->writeSchemaOnlyDump($tableNames, $timestamp, $tempDir, $token, 12, 86, $baseMeta, $startedTs, $recentTables);
            } else {
                $this->writeSqlDump($tableNames, $timestamp, $tempDir, $token, 12, 60, $baseMeta, $startedTs, $recentTables);

                foreach ($tableNames as $i => $table) {
                    $processed = $i + 1;
                    $tableStartPct = 60 + (int) (($i / max($total, 1)) * 26);
                    $tableEndPct = 60 + (int) (($processed / max($total, 1)) * 26);

                    $recentTables[] = "{$table} (CSV)";
                    $recentSlice = array_slice($recentTables, -25);

                    $this->setProgress($token, $tableStartPct, "Membuat CSV tabel {$table} ({$processed}/{$total})...", null, null, array_merge($baseMeta, [
                        'stage' => 'csv_export',
                        'stage_label' => 'Ekspor CSV',
                        'processed_tables' => $processed,
                        'current_table' => $table,
                        'table_rows_processed' => 0,
                        'recent_tables' => $recentSlice,
                        'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
                    ]));

                    $lastEmit = microtime(true);
                    $this->writeCsv($table, $tempDir . '/csv', function ($rowsDone, $rowTotal) use (
                        &$lastEmit, $token, $baseMeta, $startedTs, $processed, $table, $tableStartPct, $tableEndPct, $recentSlice
                    ) {
                        $now = microtime(true);
                        if ($now - $lastEmit < 0.3) {
                            return;
                        }
                        $frac = $rowTotal > 0 ? min($rowsDone / $rowTotal, 1) : 1;
                        $pct = $tableStartPct + (int) (($tableEndPct - $tableStartPct) * $frac);
                        $this->setProgress($token, min($pct, $tableEndPct), "Membuat CSV tabel {$table} - {$rowsDone}/{$rowTotal} baris...", null, null, array_merge($baseMeta, [
                            'stage' => 'csv_export',
                            'stage_label' => 'Ekspor CSV',
                            'processed_tables' => $processed,
                            'current_table' => $table,
                            'table_rows_total' => $rowTotal,
                            'table_rows_processed' => $rowsDone,
                            'recent_tables' => $recentSlice,
                            'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
                        ]));
                        $lastEmit = $now;
                    });
                }
            }

            $finalRecentTables = array_slice($recentTables, -25);

            $this->writeBackupLog("{$tempDir}/log.txt", [
                'app' => config('app.name'),
                'started_at' => $startedAt->format('d/m/Y H:i:s'),
                'user' => $userName,
                'backup_scope_label' => $backupScopeLabel,
                'storage_label' => $storageType === 'cloud' ? 'Google Drive' : 'Local Storage',
                'total_tables' => $total,
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
                'steps' => $recentTables,
            ]);

            $this->setProgress($token, 87, 'Mempersiapkan kompresi arsip...', null, null, array_merge($baseMeta, [
                'stage' => 'compressing',
                'stage_label' => 'Kompresi arsip',
                'processed_tables' => $total,
                'recent_tables' => $finalRecentTables,
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]));

            $phar = new \PharData($tarPath);
            $phar->addFile("{$tempDir}/dump.sql", 'dump.sql');
            if (file_exists("{$tempDir}/log.txt")) {
                $phar->addFile("{$tempDir}/log.txt", 'log.txt');
            }
            $csvFiles = $backupScope === 'full' ? glob("{$tempDir}/csv/*.csv") : [];
            $totalCsv = max(count($csvFiles), 1);
            foreach ($csvFiles as $idx => $file) {
                $pct = 88 + (int) (((($idx + 1) / $totalCsv) * 8));
                $this->setProgress($token, min($pct, 96), 'Menambahkan file ke arsip...', null, null, array_merge($baseMeta, [
                    'stage' => 'compressing',
                    'stage_label' => 'Kompresi arsip',
                    'processed_tables' => $total,
                    'processed_files' => $idx + 1,
                    'total_files' => $totalCsv,
                    'recent_tables' => $finalRecentTables,
                    'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
                ]));
                $phar->addFile($file, 'csv/' . basename($file));
            }
            $phar->compress(\Phar::GZ);
            unset($phar);
            @unlink($tarPath);

            if (is_dir("{$tempDir}/csv")) {
                foreach (glob("{$tempDir}/csv/*.csv") as $file) {
                    @unlink($file);
                }
                @rmdir("{$tempDir}/csv");
            }
            @unlink("{$tempDir}/dump.sql");
            @unlink("{$tempDir}/log.txt");
            @rmdir($tempDir);

            $fileSize = file_exists($targzPath) ? (int) filesize($targzPath) : 0;
            $fileName = basename($targzPath);

            if ($storageType === 'cloud') {
                $this->setProgress($token, 97, 'Mengupload backup ke Google Drive...', $targzPath, null, array_merge($baseMeta, [
                    'stage' => 'cloud_upload',
                    'stage_label' => 'Upload ke Google Drive',
                    'processed_tables' => $total,
                    'recent_tables' => $finalRecentTables,
                    'file_name' => $fileName,
                    'file_size' => $fileSize,
                    'file_size_human' => $this->formatBytes($fileSize),
                    'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
                ]));

                $driveFile = $this->googleDriveStorage->uploadFile($targzPath, $fileName, 'application/gzip');
                @unlink($targzPath);

                $this->setProgress($token, 100, 'Backup selesai! File berhasil diupload ke Google Drive.', null, null, array_merge($baseMeta, [
                    'stage' => 'completed',
                    'stage_label' => 'Selesai',
                    'storage_label' => 'Google Drive',
                    'processed_tables' => $total,
                    'recent_tables' => $finalRecentTables,
                    'file_name' => $driveFile['filename'],
                    'file_size' => $fileSize,
                    'file_size_human' => $this->formatBytes($fileSize),
                    'backup_scope_label' => $backupScopeLabel,
                    'cloud_provider' => 'google_drive',
                    'drive_file_id' => $driveFile['file_id'],
                    'cloud_url' => $driveFile['web_view_link'],
                    'finished_at' => now()->format('d/m/Y H:i:s'),
                    'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
                ]));

                return;
            }

            Cache::put("db_backup_{$token}", $targzPath, now()->addHours(1));

            $this->setProgress($token, 100, 'Backup selesai! File siap diunduh.', $targzPath, null, array_merge($baseMeta, [
                'stage' => 'completed',
                'stage_label' => 'Selesai',
                'storage_label' => 'Local Storage',
                'processed_tables' => $total,
                'recent_tables' => $finalRecentTables,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'file_size_human' => $this->formatBytes($fileSize),
                'backup_scope_label' => $backupScopeLabel,
                'download_url' => route('tools.database.backup.download', ['token' => $token]),
                'finished_at' => now()->format('d/m/Y H:i:s'),
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]));
        } catch (\Throwable $e) {
            $this->setProgress($token, -1, 'Backup gagal.', null, $e->getMessage(), [
                'operation' => 'backup',
                'user' => $userName,
                'storage_type' => $storageType,
                'backup_scope' => $backupScope,
                'backup_scope_label' => $backupScopeLabel,
                'backup_date' => $startedAt->format('d/m/Y H:i:s'),
                'stage' => 'failed',
                'stage_label' => 'Gagal',
                'queue' => 'database-tools',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]);

            throw $e;
        }
    }

    public function downloadDriveArchive(string $driveFileId, string $targetPath): array
    {
        return $this->googleDriveStorage->downloadFileToPath($driveFileId, $targetPath);
    }

    public function humanBytes(int $bytes): string
    {
        return $this->formatBytes($bytes);
    }

    /**
     * Membaca isi log.txt & memastikan dump.sql ada di dalam arsip backup,
     * tanpa menjalankan proses restore.
     */
    public function inspectArchive(string $archivePath): array
    {
        if (!file_exists($archivePath)) {
            throw new \RuntimeException('File arsip backup tidak ditemukan.');
        }

        $log = null;
        $hasDump = false;

        $phar = new \PharData($archivePath);
        try {
            if (isset($phar['log.txt'])) {
                $log = $phar['log.txt']->getContent();
            }
            if (isset($phar['dump.sql'])) {
                $hasDump = true;
            }
        } finally {
            unset($phar);
        }

        return ['log' => $log, 'has_dump' => $hasDump];
    }

    /**
     * Daftar tabel di dalam backup beserta jumlah record-nya (dari dump.sql),
     * dibandingkan dengan jumlah record tabel saat ini di database.
     * dump.sql diekstrak ke {$tempDir}/inspect agar bisa dipakai ulang saat runTableRestore.
     */
    public function inspectBackupTables(string $archivePath, string $tempDir): array
    {
        $dumpPath = $tempDir . DIRECTORY_SEPARATOR . 'inspect' . DIRECTORY_SEPARATOR . 'dump.sql';

        if (!file_exists($dumpPath)) {
            $phar = new \PharData($archivePath);
            $phar->extractTo($tempDir . DIRECTORY_SEPARATOR . 'inspect', ['dump.sql'], true);
            unset($phar);
        }

        if (!file_exists($dumpPath)) {
            return [];
        }

        $backupCounts = [];
        $order = [];

        $handle = fopen($dumpPath, 'r');
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^--\s*INSERT:\s*(.+?)\s*$/', $line, $m)) {
                $table = $m[1];
                if (!array_key_exists($table, $backupCounts)) {
                    $backupCounts[$table] = 0;
                    $order[] = $table;
                }
            } elseif (preg_match('/^\s*INSERT INTO "([^"]+)"/', $line, $m)) {
                $table = $m[1];
                if (!array_key_exists($table, $backupCounts)) {
                    $backupCounts[$table] = 0;
                    $order[] = $table;
                }
                $backupCounts[$table]++;
            }
        }
        fclose($handle);

        sort($order);

        $existing = collect(DB::select("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
        "))->pluck('table_name')->all();
        $existingSet = array_flip($existing);

        // Hitung record tabel saat ini secara akurat dalam satu query (UNION ALL).
        $countable = array_values(array_filter($order, fn ($t) => isset($existingSet[$t])));
        $currentMap = [];
        if (!empty($countable)) {
            $parts = array_map(function ($t) {
                $quoted = '"' . str_replace('"', '""', $t) . '"';
                $literal = "'" . str_replace("'", "''", $t) . "'";

                return "SELECT {$literal} AS name, (SELECT COUNT(*) FROM {$quoted}) AS cnt";
            }, $countable);

            foreach (DB::select(implode(' UNION ALL ', $parts)) as $row) {
                $currentMap[$row->name] = (int) $row->cnt;
            }
        }

        $tables = [];
        foreach ($order as $table) {
            $existsInDb = isset($existingSet[$table]);
            $tables[] = [
                'name' => $table,
                'backup_records' => $backupCounts[$table],
                'current_records' => $existsInDb ? ($currentMap[$table] ?? 0) : null,
                'exists_in_db' => $existsInDb,
            ];
        }

        return $tables;
    }

    public function runTableRestore(string $token, string $archivePath, array $selectedTables, string $userName, ?string $originalFilename = null, ?string $sourceLabel = null): void
    {
        $startedTs = microtime(true);
        $startedAt = now();
        $tempDir = dirname($archivePath);
        $dumpPath = $tempDir . DIRECTORY_SEPARATOR . 'inspect' . DIRECTORY_SEPARATOR . 'dump.sql';

        $baseMeta = [
            'operation' => 'restore',
            'user' => $userName,
            'source_type' => 'local',
            'source_label' => $sourceLabel ?? 'Local Drive',
            'restore_mode' => 'table',
            'restore_mode_label' => 'Restore per Tabel',
            'backup_date' => $startedAt->format('d/m/Y H:i:s'),
            'queue' => 'database-tools',
        ];
        if ($originalFilename) {
            $baseMeta['file_name'] = $originalFilename;
        }

        try {
            if (!file_exists($dumpPath)) {
                $phar = new \PharData($archivePath);
                $phar->extractTo($tempDir . DIRECTORY_SEPARATOR . 'inspect', ['dump.sql'], true);
                unset($phar);
            }
            if (!file_exists($dumpPath)) {
                throw new \RuntimeException('File dump.sql tidak ditemukan di dalam backup.');
            }

            // Validasi tabel terhadap tabel asli untuk mencegah SQL injection.
            $existing = collect(DB::select("
                SELECT table_name
                FROM information_schema.tables
                WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
            "))->pluck('table_name')->all();

            $targets = array_values(array_intersect($selectedTables, $existing));
            if (empty($targets)) {
                throw new \RuntimeException('Tidak ada tabel valid yang dipilih untuk restore.');
            }
            $targetSet = array_flip($targets);

            $this->setProgress($token, 8, 'Menyiapkan restore per tabel...', null, null, array_merge($baseMeta, [
                'stage' => 'starting',
                'stage_label' => 'Memulai restore tabel',
                'total_tables' => count($targets),
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]));

            // Pre-scan jumlah INSERT untuk tabel terpilih (untuk progress).
            $totalInserts = 0;
            $scan = fopen($dumpPath, 'r');
            while (($line = fgets($scan)) !== false) {
                if (preg_match('/^\s*INSERT INTO "([^"]+)"/', $line, $m) && isset($targetSet[$m[1]])) {
                    $totalInserts++;
                }
            }
            fclose($scan);

            DB::statement('SET session_replication_role = replica;');
            DB::beginTransaction();

            try {
                // 1) Kosongkan isi tabel terpilih (timpa data lama).
                foreach ($targets as $i => $table) {
                    $this->setProgress($token, 12, "Menghapus isi tabel {$table} ({$i}/" . count($targets) . ')...', null, null, array_merge($baseMeta, [
                        'stage' => 'truncating',
                        'stage_label' => 'Menghapus isi tabel lama',
                        'current_table' => $table,
                        'processed_tables' => $i,
                        'total_tables' => count($targets),
                        'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
                    ]));
                    $quoted = '"' . str_replace('"', '""', $table) . '"';
                    DB::statement("DELETE FROM {$quoted};");
                }

                // 2) Terapkan INSERT dari dump hanya untuk tabel terpilih.
                $handle = fopen($dumpPath, 'r');
                $buffer = '';
                $applied = 0;
                $lastPct = 15;

                while (($line = fgets($handle)) !== false) {
                    $trimmed = trim($line);
                    if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                        continue;
                    }
                    if (in_array(strtoupper(rtrim($trimmed, ';')), ['BEGIN', 'COMMIT'], true)) {
                        continue;
                    }
                    if (str_starts_with(strtoupper($trimmed), 'SET SESSION_REPLICATION_ROLE')) {
                        continue;
                    }
                    if (str_starts_with(strtoupper($trimmed), 'TRUNCATE TABLE')) {
                        continue;
                    }

                    $buffer .= ' ' . $trimmed;
                    if (!str_ends_with(rtrim($buffer), ';')) {
                        continue;
                    }

                    $stmt = trim(rtrim($buffer, ';'));
                    $buffer = '';
                    if ($stmt === '') {
                        continue;
                    }

                    // Hanya jalankan INSERT untuk tabel terpilih. Lewati setval, INSERT tabel lain, dll.
                    if (preg_match('/^INSERT INTO "([^"]+)"/', $stmt, $m) && isset($targetSet[$m[1]])) {
                        DB::statement($stmt);
                        $applied++;

                        $pct = 15 + (int) (($applied / max($totalInserts, 1)) * 75);
                        if ($pct > $lastPct) {
                            $lastPct = $pct;
                            $this->setProgress($token, min($pct, 90), "Menerapkan data tabel... ({$applied}/{$totalInserts})", null, null, array_merge($baseMeta, [
                                'stage' => 'restoring',
                                'stage_label' => 'Menerapkan data tabel',
                                'current_table' => $m[1],
                                'processed_records' => $applied,
                                'total_records' => $totalInserts,
                                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
                            ]));
                        }
                    }
                }
                fclose($handle);

                // 3) Reset sequence/identity kolom id tabel terpilih.
                $this->setProgress($token, 94, 'Menyesuaikan sequence tabel...', null, null, array_merge($baseMeta, [
                    'stage' => 'reset_sequence',
                    'stage_label' => 'Reset sequence',
                    'total_tables' => count($targets),
                    'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
                ]));

                foreach ($targets as $table) {
                    $idCol = DB::select("SELECT data_type FROM information_schema.columns WHERE table_schema='public' AND table_name=? AND column_name='id'", [$table]);
                    if ($idCol && in_array(strtolower($idCol[0]->data_type), ['integer', 'bigint', 'smallint'], true)) {
                        $seqRow = DB::selectOne("SELECT pg_get_serial_sequence(?, 'id') AS seq", ["public.{$table}"]);
                        if ($seqRow && !empty($seqRow->seq)) {
                            $quoted = '"' . str_replace('"', '""', $table) . '"';
                            $maxRow = DB::selectOne("SELECT COALESCE(MAX(id), 0) AS m FROM {$quoted}");
                            DB::statement('SELECT setval(?, ?, false)', [$seqRow->seq, ((int) ($maxRow->m ?? 0)) + 1]);
                        }
                    }
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            } finally {
                DB::statement('SET session_replication_role = DEFAULT;');
            }

            $this->setProgress($token, 100, 'Restore tabel selesai!', null, null, array_merge($baseMeta, [
                'stage' => 'completed',
                'stage_label' => 'Selesai',
                'processed_tables' => count($targets),
                'total_tables' => count($targets),
                'finished_at' => now()->format('d/m/Y H:i:s'),
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]));
        } catch (\Throwable $e) {
            $this->setProgress($token, -1, 'Restore tabel gagal.', null, $e->getMessage(), array_merge($baseMeta, [
                'stage' => 'failed',
                'stage_label' => 'Gagal',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]));

            throw $e;
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    public function runRestore(string $token, ?string $archivePath, string $userName, ?string $originalFilename = null, string $sourceType = 'local', ?string $driveFileId = null, ?string $sourceLabel = null): void
    {
        $startedTs = microtime(true);
        $startedAt = now();
        $tempDir = $archivePath ? dirname($archivePath) : storage_path("app/restore_queue_{$token}");
        $baseMeta = [
            'operation' => 'restore',
            'user' => $userName,
            'source_type' => $sourceType,
            'source_label' => $sourceLabel ?? ($sourceType === 'cloud' ? 'Google Drive' : 'Local Drive'),
            'backup_date' => $startedAt->format('d/m/Y H:i:s'),
            'queue' => 'database-tools',
        ];

        if ($originalFilename) {
            $baseMeta['file_name'] = $originalFilename;
        }
        if (file_exists($archivePath)) {
            $size = (int) filesize($archivePath);
            $baseMeta['file_size'] = $size;
            $baseMeta['file_size_human'] = $this->formatBytes($size);
        }

        try {
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            if ($sourceType === 'cloud') {
                if (empty($driveFileId)) {
                    throw new \RuntimeException('File Google Drive belum dipilih untuk restore.');
                }

                $archivePath = $tempDir . DIRECTORY_SEPARATOR . 'restore.tar.gz';
                $this->setProgress($token, 8, 'Mengambil file dari Google Drive...', null, null, array_merge($baseMeta, [
                    'stage' => 'downloading_cloud',
                    'stage_label' => 'Mengambil file cloud',
                    'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
                ]));

                $driveFile = $this->googleDriveStorage->downloadFileToPath($driveFileId, $archivePath);
                $baseMeta['file_name'] = $driveFile['filename'];
                $baseMeta['file_size'] = (int) filesize($archivePath);
                $baseMeta['file_size_human'] = $this->formatBytes((int) filesize($archivePath));
                $baseMeta['drive_file_id'] = $driveFile['file_id'];
                $baseMeta['cloud_provider'] = 'google_drive';
                $baseMeta['cloud_url'] = $driveFile['web_view_link'];
            }

            $this->setProgress($token, 10, 'Worker memulai restore...', null, null, array_merge($baseMeta, [
                'stage' => 'starting',
                'stage_label' => 'Memulai restore',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]));

            $this->setProgress($token, 15, 'Mengekstrak arsip...', null, null, array_merge($baseMeta, [
                'stage' => 'extracting',
                'stage_label' => 'Ekstraksi arsip',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]));
            $phar = new \PharData($archivePath);
            $phar->decompress();
            unset($phar);

            $tarPath = preg_replace('/\.gz$/', '', $archivePath);
            $tar = new \PharData($tarPath);
            $tar->extractTo("{$tempDir}/extracted");
            unset($tar);

            $sqlFile = "{$tempDir}/extracted/dump.sql";
            if (!file_exists($sqlFile)) {
                throw new \RuntimeException('File dump.sql tidak ditemukan di dalam backup.');
            }

            $this->setProgress($token, 25, 'Menghitung ukuran data...', null, null, array_merge($baseMeta, [
                'stage' => 'counting',
                'stage_label' => 'Menghitung data SQL',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]));

            $totalLines = 0;
            $handle = fopen($sqlFile, 'r');
            while (fgets($handle) !== false) {
                $totalLines++;
            }
            fclose($handle);

            $this->setProgress($token, 30, 'Menjalankan restore...', null, null, array_merge($baseMeta, [
                'stage' => 'restoring',
                'stage_label' => 'Eksekusi SQL',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]));

            DB::statement('SET session_replication_role = replica;');
            DB::beginTransaction();

            try {
                $handle = fopen($sqlFile, 'r');
                $buffer = '';
                $done = 0;
                $lastPct = 30;

                while (($line = fgets($handle)) !== false) {
                    $done++;
                    $trimmed = trim($line);
                    if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                        continue;
                    }
                    if (in_array(strtoupper(rtrim($trimmed, ';')), ['BEGIN', 'COMMIT'], true)) {
                        continue;
                    }
                    if (str_starts_with(strtoupper($trimmed), 'SET SESSION_REPLICATION_ROLE')) {
                        continue;
                    }

                    $buffer .= ' ' . $trimmed;
                    if (str_ends_with(rtrim($buffer), ';')) {
                        $stmt = trim(rtrim($buffer, ';'));
                        if ($stmt !== '') {
                            DB::statement($stmt);
                        }
                        $buffer = '';

                        $pct = 30 + (int) (($done / max($totalLines, 1)) * 65);
                        if ($pct > $lastPct + 1) {
                            $lastPct = $pct;
                            $this->setProgress($token, min($pct, 94), 'Memproses data SQL...', null, null, array_merge($baseMeta, [
                                'stage' => 'restoring',
                                'stage_label' => 'Eksekusi SQL',
                                'processed_lines' => $done,
                                'total_lines' => $totalLines,
                                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
                            ]));
                        }
                    }
                }
                fclose($handle);
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            } finally {
                DB::statement('SET session_replication_role = DEFAULT;');
            }

            $this->setProgress($token, 100, 'Restore selesai!', null, null, array_merge($baseMeta, [
                'stage' => 'completed',
                'stage_label' => 'Selesai',
                'finished_at' => now()->format('d/m/Y H:i:s'),
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]));
        } catch (\Throwable $e) {
            $this->setProgress($token, -1, 'Restore gagal.', null, $e->getMessage(), array_merge($baseMeta, [
                'stage' => 'failed',
                'stage_label' => 'Gagal',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]));

            throw $e;
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    private function writeSqlDump(array $tableNames, string $ts, string $dir, string $token, int $pctStart, int $pctEnd, array $baseMeta, float $startedTs, array &$recentTables = []): void
    {
        $total = count($tableNames);
        $file = fopen("{$dir}/dump.sql", 'w');

        fwrite($file, "-- PostgreSQL Backup\n-- Generated : {$ts}\n-- App : " . config('app.name') . "\n\n");
        fwrite($file, "SET session_replication_role = replica;\nBEGIN;\n\n");

        $tableList = implode(', ', array_map(fn ($table) => '"' . $table . '"', $tableNames));
        fwrite($file, "-- TRUNCATE ALL\nTRUNCATE TABLE {$tableList} CASCADE;\n\n");
        $this->setProgress($token, $pctStart + 5, 'TRUNCATE selesai, mulai dump SQL...', null, null, array_merge($baseMeta, [
            'stage' => 'sql_dump',
            'stage_label' => 'Membuat SQL dump',
            'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
        ]));

        $range = $pctEnd - $pctStart - 10;
        $baseSqlPct = $pctStart + 5;
        foreach ($tableNames as $i => $table) {
            $processed = $i + 1;
            $tableStartPct = $baseSqlPct + (int) (($i / max($total, 1)) * $range);
            $tableEndPct = $baseSqlPct + (int) (($processed / max($total, 1)) * $range);

            $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema='public' AND table_name=? ORDER BY ordinal_position", [$table]);
            if (empty($columns)) {
                continue;
            }

            $colNames = collect($columns)->pluck('column_name');
            $colList = $colNames->map(fn ($column) => '"' . $column . '"')->implode(', ');
            $firstCol = $colNames->first();

            $rowTotal = (int) DB::table($table)->count();

            $recentTables[] = "{$table} (SQL)";
            $recentSlice = array_slice($recentTables, -25);

            $this->setProgress($token, $tableStartPct, "Dump SQL tabel {$table} ({$processed}/{$total})...", null, null, array_merge($baseMeta, [
                'stage' => 'sql_dump',
                'stage_label' => 'Membuat SQL dump',
                'processed_tables' => $processed,
                'current_table' => $table,
                'table_rows_total' => $rowTotal,
                'table_rows_processed' => 0,
                'recent_tables' => $recentSlice,
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]));

            fwrite($file, "-- INSERT: {$table}\n");

            $rowsDone = 0;
            $lastEmit = microtime(true);
            DB::table($table)->orderBy($firstCol)->chunk(500, function ($rows) use (
                $file, $table, $colList, $token, $baseMeta, $startedTs,
                $processed, $rowTotal, $tableStartPct, $tableEndPct, $recentSlice, &$rowsDone, &$lastEmit
            ) {
                foreach ($rows as $row) {
                    $values = collect((array) $row)->map(function ($value) {
                        if (is_null($value)) {
                            return 'NULL';
                        }
                        if (is_bool($value)) {
                            return $value ? 'TRUE' : 'FALSE';
                        }
                        if (is_numeric($value)) {
                            return $value;
                        }

                        return "'" . str_replace("'", "''", (string) $value) . "'";
                    })->implode(', ');

                    fwrite($file, "INSERT INTO \"{$table}\" ({$colList}) VALUES ({$values});\n");
                }

                $rowsDone += count($rows);

                // Throttle progress writes so large tables advance smoothly without thrashing the disk.
                $now = microtime(true);
                if ($now - $lastEmit >= 0.3) {
                    $frac = $rowTotal > 0 ? min($rowsDone / $rowTotal, 1) : 1;
                    $pct = $tableStartPct + (int) (($tableEndPct - $tableStartPct) * $frac);
                    $this->setProgress($token, min($pct, $tableEndPct), "Dump SQL tabel {$table} - {$rowsDone}/{$rowTotal} baris...", null, null, array_merge($baseMeta, [
                        'stage' => 'sql_dump',
                        'stage_label' => 'Membuat SQL dump',
                        'processed_tables' => $processed,
                        'current_table' => $table,
                        'table_rows_total' => $rowTotal,
                        'table_rows_processed' => $rowsDone,
                        'recent_tables' => $recentSlice,
                        'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
                    ]));
                    $lastEmit = $now;
                }
            });
            fwrite($file, "\n");
        }

        $this->setProgress($token, $pctEnd - 3, 'Menyesuaikan sequence tabel...', null, null, array_merge($baseMeta, [
            'stage' => 'reset_sequence',
            'stage_label' => 'Reset sequence',
            'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
        ]));

        $intTypes = ['integer', 'bigint', 'smallint', 'int4', 'int8', 'serial', 'bigserial'];
        foreach ($tableNames as $table) {
            $idCol = DB::select("SELECT data_type FROM information_schema.columns WHERE table_schema='public' AND table_name=? AND column_name='id'", [$table]);
            if ($idCol && in_array(strtolower($idCol[0]->data_type), $intTypes, true)) {
                $seq = DB::select("SELECT pg_get_serial_sequence('\"" . $table . "\"', 'id') AS seq");
                if ($seq && !empty($seq[0]->seq)) {
                    fwrite($file, "SELECT setval(pg_get_serial_sequence('\"{$table}\"', 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 0) + 1, false);\n");
                }
            }
        }

        fwrite($file, "\nCOMMIT;\nSET session_replication_role = DEFAULT;\n");
        fclose($file);
    }

    private function writeSchemaOnlyDump(array $tableNames, string $ts, string $dir, string $token, int $pctStart, int $pctEnd, array $baseMeta, float $startedTs, array &$recentTables = []): void
    {
        $filePath = "{$dir}/dump.sql";
        $total = max(count($tableNames), 1);
        $range = max($pctEnd - $pctStart - 2, 1);

        $fkRows = DB::select(
            "SELECT tc.constraint_name,
                    tc.table_name,
                    kcu.column_name,
                    ccu.table_name AS foreign_table_name,
                    ccu.column_name AS foreign_column_name,
                    rc.update_rule,
                    rc.delete_rule,
                    kcu.ordinal_position
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_name = kcu.constraint_name
              AND tc.constraint_schema = kcu.constraint_schema
             JOIN information_schema.constraint_column_usage ccu
               ON ccu.constraint_name = tc.constraint_name
              AND ccu.constraint_schema = tc.constraint_schema
             JOIN information_schema.referential_constraints rc
               ON rc.constraint_name = tc.constraint_name
              AND rc.constraint_schema = tc.constraint_schema
             WHERE tc.constraint_type = 'FOREIGN KEY'
               AND tc.table_schema = 'public'
             ORDER BY tc.table_name, tc.constraint_name, kcu.ordinal_position"
        );

        $foreignKeys = [];
        foreach ($fkRows as $row) {
            $key = $row->table_name . '|' . $row->constraint_name;
            if (!isset($foreignKeys[$key])) {
                $foreignKeys[$key] = [
                    'table_name' => (string) $row->table_name,
                    'constraint_name' => (string) $row->constraint_name,
                    'foreign_table_name' => (string) $row->foreign_table_name,
                    'update_rule' => (string) $row->update_rule,
                    'delete_rule' => (string) $row->delete_rule,
                    'columns' => [],
                    'foreign_columns' => [],
                ];
            }

            $foreignKeys[$key]['columns'][] = (string) $row->column_name;
            $foreignKeys[$key]['foreign_columns'][] = (string) $row->foreign_column_name;
        }

        $indexRows = DB::select(
            "SELECT tablename, indexname, indexdef
             FROM pg_indexes
             WHERE schemaname = 'public'
             ORDER BY tablename, indexname"
        );

        $indexesByTable = [];
        foreach ($indexRows as $indexRow) {
            $tableName = (string) $indexRow->tablename;
            $indexName = (string) $indexRow->indexname;

            if ($indexName === $tableName . '_pkey') {
                continue;
            }

            $indexDef = (string) $indexRow->indexdef;
            $indexDef = preg_replace('/^CREATE UNIQUE INDEX\s+/i', 'CREATE UNIQUE INDEX IF NOT EXISTS ', $indexDef) ?? $indexDef;
            $indexDef = preg_replace('/^CREATE INDEX\s+/i', 'CREATE INDEX IF NOT EXISTS ', $indexDef) ?? $indexDef;

            $indexesByTable[$tableName][] = rtrim($indexDef, ';') . ';';
        }

        $triggerRows = DB::select(
            "SELECT c.relname AS table_name,
                    t.tgname AS trigger_name,
                    pg_get_triggerdef(t.oid, true) AS trigger_def
             FROM pg_trigger t
             JOIN pg_class c ON c.oid = t.tgrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = 'public'
               AND NOT t.tgisinternal
             ORDER BY c.relname, t.tgname"
        );

        $triggersByTable = [];
        foreach ($triggerRows as $triggerRow) {
            $tableName = (string) $triggerRow->table_name;
            $triggerDef = (string) $triggerRow->trigger_def;
            $triggersByTable[$tableName][] = rtrim($triggerDef, ';') . ';';
        }

        $triggerFunctionRows = DB::select(
            "SELECT DISTINCT p.oid,
                    p.proname AS function_name,
                    pg_get_functiondef(p.oid) AS function_def
             FROM pg_trigger t
             JOIN pg_class c ON c.oid = t.tgrelid
             JOIN pg_namespace n ON n.oid = c.relnamespace
             JOIN pg_proc p ON p.oid = t.tgfoid
             WHERE n.nspname = 'public'
               AND NOT t.tgisinternal
             ORDER BY p.proname"
        );

        $triggerFunctionDefs = [];
        foreach ($triggerFunctionRows as $triggerFunctionRow) {
            $triggerFunctionDefs[] = rtrim((string) $triggerFunctionRow->function_def, ';') . ';';
        }

        $identityBackedSequenceRows = DB::select(
            "SELECT DISTINCT regexp_replace(col.column_default, '^nextval\\(''([^'']+)''::regclass\\)$', '\\1') AS sequence_name
             FROM information_schema.columns col
             WHERE col.table_schema = 'public'
               AND col.column_default LIKE 'nextval(%'"
        );

        $identityBackedSequenceNames = collect($identityBackedSequenceRows)
            ->pluck('sequence_name')
            ->filter()
            ->map(function ($sequenceName) {
                $parts = explode('.', (string) $sequenceName);

                return end($parts) ?: (string) $sequenceName;
            })
            ->values()
            ->all();

        // Keep standalone sequences only. Serial-style backing sequences are emitted as IDENTITY columns.
        $sequenceRows = DB::select(
            "SELECT c.relname AS sequence_name,
                    s.seqstart AS start_value,
                    s.seqincrement AS increment,
                    s.seqmax AS max_value,
                    s.seqmin AS min_value,
                    s.seqcache AS cache_size,
                    s.seqcycle AS cycle
             FROM pg_class c
             JOIN pg_namespace n ON n.oid = c.relnamespace
             LEFT JOIN pg_sequence s ON s.seqrelid = c.oid
             WHERE n.nspname = 'public'
               AND c.relkind = 'S'
             ORDER BY c.relname"
        );

        $sequenceDefs = [];
        foreach ($sequenceRows as $seqRow) {
            $seqName = (string) $seqRow->sequence_name;
            if (in_array($seqName, $identityBackedSequenceNames, true)) {
                continue;
            }

            $startVal = (int) ($seqRow->start_value ?? 1);
            $incBy = (int) ($seqRow->increment ?? 1);
            $maxVal = (string) ($seqRow->max_value ?? '9223372036854775807');
            $minVal = (string) ($seqRow->min_value ?? 1);
            $cache = (int) ($seqRow->cache_size ?? 1);
            $cycled = $seqRow->cycle ? 'CYCLE' : 'NO CYCLE';

            $seqDef = "CREATE SEQUENCE IF NOT EXISTS " . $this->quoteIdentifier('public') . "." . $this->quoteIdentifier($seqName)
                . " START WITH {$startVal} INCREMENT BY {$incBy}"
                . " MINVALUE {$minVal} MAXVALUE {$maxVal}"
                . " CACHE {$cache} {$cycled};";

            $sequenceDefs[] = $seqDef;
        }

        $lines = [];
        $lines[] = "-- PostgreSQL Structure Backup";
        $lines[] = "-- Generated : {$ts}";
        $lines[] = "-- App : " . config('app.name');
        $lines[] = '';
        $lines[] = 'BEGIN;';
        $lines[] = '';

        // Add sequences first
        if (!empty($sequenceDefs)) {
            $lines[] = '-- SEQUENCES';
            $lines = array_merge($lines, $sequenceDefs);
            $lines[] = '';
        }

        foreach ($tableNames as $i => $table) {
            $processed = $i + 1;
            $pct = $pctStart + (int) (($processed / $total) * $range);

            $recentTables[] = $table;

            $this->setProgress($token, min($pct, $pctEnd - 1), "Menyusun struktur tabel {$table} ({$processed}/{$total})...", null, null, array_merge($baseMeta, [
                'stage' => 'schema_export',
                'stage_label' => 'Ekspor struktur',
                'processed_tables' => $processed,
                'current_table' => $table,
                'recent_tables' => array_slice($recentTables, -25),
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]));

            $columns = DB::select(
                "SELECT col.column_name, col.data_type, col.udt_name, col.character_maximum_length, col.numeric_precision, col.numeric_scale, col.is_nullable, col.column_default, col.is_identity, col.identity_generation, pt.typname
                 FROM information_schema.columns col
                 LEFT JOIN pg_type pt ON col.udt_name = pt.typname
                 WHERE col.table_schema = 'public' AND col.table_name = ?
                 ORDER BY col.ordinal_position",
                [$table]
            );

            if (empty($columns)) {
                continue;
            }

            $columnLines = [];
            foreach ($columns as $column) {
                $columnName = $this->quoteIdentifier((string) $column->column_name);
                $typeSql = $this->buildColumnTypeSql($column);
                $nullableSql = strtoupper((string) $column->is_nullable) === 'NO' ? ' NOT NULL' : '';
                $defaultSql = $this->buildColumnDefaultSql($column);

                $columnLines[] = "    {$columnName} {$typeSql}{$defaultSql}{$nullableSql}";
            }

            $pkColumns = DB::select(
                "SELECT a.attname AS column_name
                 FROM pg_index i
                 JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                 WHERE i.indrelid = to_regclass(?)
                   AND i.indisprimary
                 ORDER BY a.attnum",
                ["public.{$table}"]
            );

            if (!empty($pkColumns)) {
                $pkList = collect($pkColumns)
                    ->pluck('column_name')
                    ->map(fn ($name) => $this->quoteIdentifier((string) $name))
                    ->implode(', ');
                $columnLines[] = "    PRIMARY KEY ({$pkList})";
            }

            $lines[] = '-- TABLE: ' . $table;
            $lines[] = 'DROP TABLE IF EXISTS ' . $this->quoteIdentifier('public') . '.' . $this->quoteIdentifier($table) . ' CASCADE;';
            $lines[] = 'CREATE TABLE ' . $this->quoteIdentifier('public') . '.' . $this->quoteIdentifier($table) . ' (';
            $lines[] = implode(",\n", $columnLines);
            $lines[] = ');';
            $lines[] = '';
        }

        if (!empty($foreignKeys)) {
            $lines[] = '-- FOREIGN KEYS';
            foreach ($foreignKeys as $fk) {
                $localCols = collect($fk['columns'])
                    ->map(fn ($name) => $this->quoteIdentifier((string) $name))
                    ->implode(', ');
                $foreignCols = collect($fk['foreign_columns'])
                    ->map(fn ($name) => $this->quoteIdentifier((string) $name))
                    ->implode(', ');

                $onUpdate = $this->buildForeignKeyRuleSql((string) $fk['update_rule'], 'UPDATE');
                $onDelete = $this->buildForeignKeyRuleSql((string) $fk['delete_rule'], 'DELETE');

                $lines[] = 'ALTER TABLE ' . $this->quoteIdentifier('public') . '.' . $this->quoteIdentifier((string) $fk['table_name'])
                    . ' ADD CONSTRAINT ' . $this->quoteIdentifier((string) $fk['constraint_name'])
                    . ' FOREIGN KEY (' . $localCols . ') REFERENCES '
                    . $this->quoteIdentifier('public') . '.' . $this->quoteIdentifier((string) $fk['foreign_table_name'])
                    . ' (' . $foreignCols . ')' . $onUpdate . $onDelete . ';';
            }
            $lines[] = '';
        }

        if (!empty($indexesByTable)) {
            $lines[] = '-- INDEXES';
            foreach ($tableNames as $table) {
                foreach ($indexesByTable[$table] ?? [] as $indexSql) {
                    $lines[] = $indexSql;
                }
            }
            $lines[] = '';
        }

        if (!empty($triggerFunctionDefs)) {
            $lines[] = '-- TRIGGER FUNCTIONS';
            foreach ($triggerFunctionDefs as $triggerFunctionDef) {
                $lines[] = $triggerFunctionDef;
                $lines[] = '';
            }
        }

        if (!empty($triggersByTable)) {
            $lines[] = '-- TRIGGERS';
            foreach ($tableNames as $table) {
                foreach ($triggersByTable[$table] ?? [] as $triggerSql) {
                    $lines[] = $triggerSql;
                }
            }
            $lines[] = '';
        }

        $lines[] = 'COMMIT;';
        $lines[] = '';
        file_put_contents($filePath, implode("\n", $lines));
    }

    private function buildForeignKeyRuleSql(string $rule, string $kind): string
    {
        $normalized = strtoupper(trim($rule));
        $allowedRules = ['NO ACTION', 'RESTRICT', 'CASCADE', 'SET NULL', 'SET DEFAULT'];

        if (!in_array($normalized, $allowedRules, true)) {
            return '';
        }

        return " ON {$kind} {$normalized}";
    }

    private function buildColumnTypeSql(object $column): string
    {
        $dataType = strtolower((string) $column->data_type);
        $udtName = (string) ($column->udt_name ?? '');
        $length = $column->character_maximum_length;
        $precision = $column->numeric_precision;
        $scale = $column->numeric_scale;

        if ($dataType === 'character varying' && $length) {
            return "varchar({$length})";
        }
        if ($dataType === 'character' && $length) {
            return "char({$length})";
        }
        if ($dataType === 'numeric' && $precision) {
            return $scale !== null ? "numeric({$precision},{$scale})" : "numeric({$precision})";
        }
        if ($dataType === 'ARRAY' || stripos($dataType, 'array') !== false) {
            $elementType = $this->getArrayElementType($column);
            if ($elementType) {
                return $elementType . '[]';
            }
            return 'text[]';
        }
        if ($dataType === 'USER-DEFINED' || $dataType === 'user-defined') {
            return $this->quoteIdentifier($udtName);
        }

        return $dataType;
    }

    private function buildColumnDefaultSql(object $column): string
    {
        $isIdentity = strtoupper((string) ($column->is_identity ?? 'NO')) === 'YES';
        if ($isIdentity) {
            $identityGeneration = strtoupper((string) ($column->identity_generation ?? 'BY DEFAULT'));
            $identityGeneration = $identityGeneration === 'ALWAYS' ? 'ALWAYS' : 'BY DEFAULT';

            return ' GENERATED ' . $identityGeneration . ' AS IDENTITY';
        }

        $columnDefault = (string) ($column->column_default ?? '');
        if ($columnDefault !== '' && preg_match("/^nextval\\('([^']+)'::regclass\\)$/", $columnDefault) === 1) {
            return ' GENERATED BY DEFAULT AS IDENTITY';
        }

        return $column->column_default !== null ? ' DEFAULT ' . $column->column_default : '';
    }

    private function getArrayElementType(object $column): string
    {
        $udtName = (string) ($column->udt_name ?? '');
        if ($udtName) {
            $typeRow = DB::selectOne(
                "SELECT et.typname FROM pg_type pt JOIN pg_type et ON pt.typelem = et.oid WHERE pt.typname = ? LIMIT 1",
                [$udtName]
            );
            if ($typeRow && !empty($typeRow->typname)) {
                return strtolower((string) $typeRow->typname);
            }
        }
        return '';
    }

    private function quoteIdentifier(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    private function writeBackupLog(string $path, array $info): void
    {
        $steps = $info['steps'] ?? [];
        $line = str_repeat('=', 60);

        $lines = [];
        $lines[] = $line;
        $lines[] = ' BACKUP LOG - ' . ($info['app'] ?? config('app.name'));
        $lines[] = $line;
        $lines[] = 'Tanggal proses : ' . ($info['started_at'] ?? '-');
        $lines[] = 'User           : ' . ($info['user'] ?? '-');
        $lines[] = 'Mode backup    : ' . ($info['backup_scope_label'] ?? '-');
        $lines[] = 'Storage        : ' . ($info['storage_label'] ?? '-');
        $lines[] = 'Total tabel    : ' . ($info['total_tables'] ?? 0);
        $lines[] = 'Durasi proses  : ' . ($info['duration_label'] ?? '-');
        $lines[] = 'Log dibuat     : ' . now()->format('d/m/Y H:i:s');
        $lines[] = '';
        $lines[] = 'Langkah yang diproses (' . count($steps) . '):';

        foreach ($steps as $idx => $entry) {
            $lines[] = sprintf('%4d. %s', $idx + 1, (string) $entry);
        }

        $lines[] = '';
        $lines[] = $line;

        file_put_contents($path, implode("\n", $lines));
    }

    private function writeCsv(string $table, string $dir, ?callable $onProgress = null): void
    {
        $first = true;
        $handle = fopen("{$dir}/{$table}.csv", 'w');
        $rowTotal = (int) DB::table($table)->count();
        $rowsDone = 0;

        $hasId = DB::select("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name=? AND column_name='id'", [$table]);

        if ($hasId) {
            DB::table($table)->chunkById(500, function ($rows) use ($handle, &$first, &$rowsDone, $rowTotal, $onProgress) {
                foreach ($rows as $row) {
                    $arr = (array) $row;
                    if ($first) {
                        fputcsv($handle, array_keys($arr));
                        $first = false;
                    }
                    fputcsv($handle, $arr);
                }

                $rowsDone += count($rows);
                if ($onProgress) {
                    $onProgress($rowsDone, $rowTotal);
                }
            });
        } else {
            foreach (DB::table($table)->orderBy(DB::raw('1'))->lazy(500) as $row) {
                $arr = (array) $row;
                if ($first) {
                    fputcsv($handle, array_keys($arr));
                    $first = false;
                }
                fputcsv($handle, $arr);

                $rowsDone++;
                if ($onProgress && $rowsDone % 500 === 0) {
                    $onProgress($rowsDone, $rowTotal);
                }
            }
        }

        fclose($handle);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return number_format($bytes / 1048576, 2) . ' MB';
    }

    private function formatDuration(float $seconds): string
    {
        $totalSeconds = (int) max(0, round($seconds));
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $secs = $totalSeconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}