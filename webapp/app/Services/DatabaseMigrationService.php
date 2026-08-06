<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DatabaseMigrationService
{
    // Untuk perpindahan server penuh, tidak ada tabel yang dikecualikan.
    private const EXCLUDED_TABLES = [];

    public function __construct(private DatabaseBackupService $progressService)
    {
    }

    public function readProgress(string $token): array
    {
        return $this->progressService->readProgress($token);
    }

    public function setProgress(string $token, int $pct, string $msg, ?string $file = null, ?string $err = null, ?array $meta = null): void
    {
        $this->progressService->setProgress($token, $pct, $msg, $file, $err, $meta);
    }

    public function queueMigration(string $token, string $userName, string $mode): void
    {
        $queuedAt = now();
        $modeLabel = $mode === 'structure' ? 'DB Structure Only' : 'Full Migration (Structure + data)';

        $this->setProgress($token, 1, 'Migration dimulai dan sedang diproses di server tujuan...', null, null, [
            'operation' => 'migration',
            'user' => $userName,
            'migration_mode' => $mode,
            'migration_mode_label' => $modeLabel,
            'migration_date' => $queuedAt->format('d/m/Y H:i:s'),
            'stage' => 'running',
            'stage_label' => 'Diproses di server tujuan',
            'queued_at' => $queuedAt->format('d/m/Y H:i:s'),
            'queue_description' => 'Migration berjalan langsung di server tujuan dan progress akan terus diperbarui.',
            'duration_label' => '00:00',
        ]);
    }

    public function failQueueing(string $token, string $userName, string $mode, \Throwable $e): void
    {
        $this->setProgress($token, -1, 'Migration gagal dimulai di server tujuan.', null, $e->getMessage(), [
            'operation' => 'migration',
            'user' => $userName,
            'migration_mode' => $mode,
            'migration_mode_label' => $mode === 'structure' ? 'DB Structure Only' : 'Full Migration (Structure + data)',
            'migration_date' => now()->format('d/m/Y H:i:s'),
            'stage' => 'failed',
            'stage_label' => 'Gagal',
            'duration_label' => '00:00',
        ]);
    }

    public function runMigration(string $token, string $userName, array $sourceConfig, array $destinationConfig, string $mode = 'full'): void
    {
        $startedAt = now();
        $startedTs = microtime(true);
        $modeLabel = $mode === 'structure' ? 'DB Structure Only' : 'Full Migration (Structure + data)';
        $sourceName = 'migration_source_' . $token;
        $destinationName = 'migration_destination_' . $token;
        $tempDir = storage_path("app/migration_tmp_{$token}");
        $sqlFile = $tempDir . DIRECTORY_SEPARATOR . 'dump.sql';
        $sourceLabel = $this->formatConnectionLabel($sourceConfig);
        $destinationLabel = $this->formatConnectionLabel($destinationConfig);

        $this->assertPgsqlConfig($sourceConfig, 'Source');
        $this->assertPgsqlConfig($destinationConfig, 'Destination');

        $this->attachConnection($sourceName, $this->buildConnectionConfig($sourceConfig));
        $this->attachConnection($destinationName, $this->buildConnectionConfig($destinationConfig));

        try {
            $source = DB::connection($sourceName);
            $destination = DB::connection($destinationName);
            $source->getPdo();
            $destination->getPdo();

            $this->setProgress($token, 5, 'Menghubungkan source dan membaca metadata tabel...', null, null, [
                'operation' => 'migration',
                'user' => $userName,
                'migration_mode' => $mode,
                'migration_mode_label' => $modeLabel,
                'source_label' => $sourceLabel,
                'destination_label' => $destinationLabel,
                'migration_date' => $startedAt->format('d/m/Y H:i:s'),
                'stage' => 'reading_tables',
                'stage_label' => 'Membaca tabel source',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]);

            $tableNames = $this->getTableNames($source);
            $totalTables = count($tableNames);
            if ($totalTables === 0) {
                throw new \RuntimeException('Tidak ada tabel source yang bisa dimigrasikan.');
            }

            $sourceTableListText = implode(', ', $tableNames);

            $this->setProgress($token, 8, 'Mereset schema destination...', null, null, [
                'operation' => 'migration',
                'user' => $userName,
                'migration_mode' => $mode,
                'migration_mode_label' => $modeLabel,
                'source_label' => $sourceLabel,
                'destination_label' => $destinationLabel,
                'migration_date' => $startedAt->format('d/m/Y H:i:s'),
                'total_tables' => $totalTables,

                'source_tables' => $tableNames,
                'source_tables_text' => $sourceTableListText,
                'stage' => 'preparing_destination',
                'stage_label' => 'Menyiapkan destination',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]);

            $this->resetDestinationSchema($destination);
            $source->statement('SET session_replication_role = replica;');
            $destination->statement('SET session_replication_role = replica;');

            try {
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                $this->writeStandardMigrationDump($source, $tableNames, $sqlFile, $token, $userName, $mode, $startedAt, $startedTs, $sourceLabel, $destinationLabel, $totalTables);
                $this->executeStandardMigrationDump($destination, $sqlFile, $token, $userName, $mode, $startedAt, $startedTs, $sourceLabel, $destinationLabel, $totalTables);

                $destination->statement('SET session_replication_role = DEFAULT;');
                $source->statement('SET session_replication_role = DEFAULT;');
            } catch (\Throwable $e) {
                $destination->statement('SET session_replication_role = DEFAULT;');
                $source->statement('SET session_replication_role = DEFAULT;');
                throw $e;
            }

            $this->setProgress($token, 100, 'Migration selesai!', null, null, [
                'operation' => 'migration',
                'user' => $userName,
                'migration_mode' => $mode,
                'migration_mode_label' => $modeLabel,
                'source_label' => $sourceLabel,
                'destination_label' => $destinationLabel,
                'migration_date' => $startedAt->format('d/m/Y H:i:s'),
                'stage' => 'completed',
                'stage_label' => 'Selesai',
                'finished_at' => now()->format('d/m/Y H:i:s'),
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
                'total_tables' => $totalTables,
                'processed_tables' => $totalTables,
                'table_transfer_pct' => 100,
                'source_tables' => $tableNames,
                'source_tables_text' => $sourceTableListText,
            ]);
        } catch (\Throwable $e) {
            $this->setProgress($token, -1, 'Migration gagal.', null, $e->getMessage(), [
                'operation' => 'migration',
                'user' => $userName,
                'migration_mode' => $mode,
                'migration_mode_label' => $modeLabel,
                'source_label' => $sourceLabel,
                'destination_label' => $destinationLabel,
                'migration_date' => $startedAt->format('d/m/Y H:i:s'),
                'stage' => 'failed',
                'stage_label' => 'Gagal',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]);

            throw $e;
        } finally {
            DB::disconnect($sourceName);
            DB::disconnect($destinationName);
            DB::purge($sourceName);
            DB::purge($destinationName);
            Config::set("database.connections.{$sourceName}", null);
            Config::set("database.connections.{$destinationName}", null);
            $this->deleteDirectory($tempDir);
        }
    }

    private function assertPgsqlConfig(array $config, string $label): void
    {
        if (($config['driver'] ?? 'pgsql') !== 'pgsql') {
            throw new \RuntimeException("{$label} hanya mendukung PostgreSQL untuk migration saat ini.");
        }
    }

    private function attachConnection(string $name, array $config): void
    {
        Config::set("database.connections.{$name}", $config);
        DB::purge($name);
    }

    private function buildConnectionConfig(array $payload): array
    {
        $driver = ($payload['driver'] ?? 'pgsql') === 'mysql' ? 'mysql' : 'pgsql';

        if ($driver === 'mysql') {
            return [
                'driver' => 'mysql',
                'host' => $payload['host'],
                'port' => $payload['port'] ?? 3306,
                'database' => $payload['database'],
                'username' => $payload['username'],
                'password' => $payload['password'] ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => false,
                'engine' => null,
            ];
        }

        // PostgreSQL menolak client_encoding utf8mb4 (itu MySQL) → pakai utf8.
        return [
            'driver' => 'pgsql',
            'host' => $payload['host'],
            'port' => $payload['port'] ?? 5432,
            'database' => $payload['database'],
            'username' => $payload['username'],
            'password' => $payload['password'] ?? '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'options' => extension_loaded('pdo_pgsql') ? [
                \PDO::ATTR_EMULATE_PREPARES => true,
            ] : [],
        ];
    }

    private function formatConnectionLabel(array $payload): string
    {
        $driver = strtoupper((string) ($payload['driver'] ?? 'pgsql'));
        $host = (string) ($payload['host'] ?? '-');
        $database = (string) ($payload['database'] ?? '-');

        return $driver . ' ' . $host . ' / ' . $database;
    }

    private function getTableNames(Connection $source): array
    {
        $rows = $source->select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY table_name");

        return collect($rows)
            ->pluck('table_name')
            ->map(fn ($name) => (string) $name)
            ->filter(fn ($name) => !in_array($name, self::EXCLUDED_TABLES, true))
            ->values()
            ->all();
    }

    private function resetDestinationSchema(Connection $destination): void
    {
        $destination->statement('DROP SCHEMA public CASCADE;');
        $destination->statement('CREATE SCHEMA public;');
        $this->grantPublicSchemaPrivileges($destination);
        $this->prepareDestinationExtensions($destination);
    }

    /**
     * Siapkan schema extensions + pgcrypto agar dump Supabase
     * (extensions.gen_random_bytes / gen_random_uuid) jalan di Postgres lokal.
     */
    private function prepareDestinationExtensions(Connection $destination): void
    {
        $destination->statement('CREATE SCHEMA IF NOT EXISTS extensions');

        $pgcryptoInExtensions = $destination->selectOne("
            SELECT 1 AS ok
            FROM pg_extension e
            JOIN pg_namespace n ON n.oid = e.extnamespace
            WHERE e.extname = 'pgcrypto' AND n.nspname = 'extensions'
            LIMIT 1
        ");

        if (! $pgcryptoInExtensions) {
            $pgcryptoAnywhere = $destination->selectOne("
                SELECT 1 AS ok FROM pg_extension WHERE extname = 'pgcrypto' LIMIT 1
            ");

            if (! $pgcryptoAnywhere) {
                try {
                    $destination->statement('CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA extensions');
                } catch (\Throwable) {
                    $destination->statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
                }
            }
        }

        // Wrapper kompatibilitas jika fungsi hanya ada di public/pg_catalog.
        $destination->statement(<<<'SQL'
DO $compat$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_proc p
        JOIN pg_namespace n ON n.oid = p.pronamespace
        WHERE n.nspname = 'extensions' AND p.proname = 'gen_random_bytes'
    ) AND EXISTS (
        SELECT 1
        FROM pg_proc p
        JOIN pg_namespace n ON n.oid = p.pronamespace
        WHERE n.nspname = 'public' AND p.proname = 'gen_random_bytes'
    ) THEN
        EXECUTE 'CREATE OR REPLACE FUNCTION extensions.gen_random_bytes(integer)
                 RETURNS bytea
                 LANGUAGE sql
                 AS $f$ SELECT public.gen_random_bytes($1) $f$';
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_proc p
        JOIN pg_namespace n ON n.oid = p.pronamespace
        WHERE n.nspname = 'extensions' AND p.proname = 'gen_random_uuid'
    ) AND EXISTS (
        SELECT 1
        FROM pg_proc p
        JOIN pg_namespace n ON n.oid = p.pronamespace
        WHERE n.nspname IN ('public', 'pg_catalog') AND p.proname = 'gen_random_uuid'
    ) THEN
        EXECUTE 'CREATE OR REPLACE FUNCTION extensions.gen_random_uuid()
                 RETURNS uuid
                 LANGUAGE sql
                 AS $f$ SELECT gen_random_uuid() $f$';
    END IF;
END
$compat$;
SQL);

        try {
            $destination->statement('GRANT USAGE ON SCHEMA extensions TO PUBLIC');
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * Grant schema public ke role yang ada saja.
     * Role Supabase (anon/authenticated/service_role) tidak selalu ada di Postgres lokal.
     */
    private function grantPublicSchemaPrivileges(Connection $connection): void
    {
        $candidates = ['postgres', 'PUBLIC', 'anon', 'authenticated', 'service_role'];

        foreach ($candidates as $role) {
            if ($role !== 'PUBLIC') {
                $exists = $connection->selectOne(
                    'SELECT 1 AS ok FROM pg_roles WHERE rolname = ? LIMIT 1',
                    [$role]
                );
                if (! $exists) {
                    continue;
                }
            }

            $quoted = $role === 'PUBLIC' ? 'PUBLIC' : '"'.str_replace('"', '""', $role).'"';
            $connection->statement("GRANT ALL ON SCHEMA public TO {$quoted}");
        }

        // Pastikan owner koneksi saat ini juga punya akses.
        try {
            $connection->statement('GRANT ALL ON SCHEMA public TO CURRENT_USER');
        } catch (\Throwable) {
            // ignore
        }
    }

    private function writeStandardMigrationDump(Connection $source, array $tableNames, string $sqlFile, string $token, string $userName, string $mode, \Illuminate\Support\Carbon $startedAt, float $startedTs, string $sourceLabel, string $destinationLabel, int $totalTables): void
    {
        $handle = fopen($sqlFile, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Tidak bisa membuat file dump migration.');
        }

        $timestamp = $startedAt->format('Ymd_His');
        $baseMeta = [
            'operation' => 'migration',
            'user' => $userName,
            'migration_mode' => $mode,
            'migration_mode_label' => $mode === 'structure' ? 'DB Structure Only' : 'Full Migration (Structure + data)',
            'source_label' => $sourceLabel,
            'destination_label' => $destinationLabel,
            'migration_date' => $startedAt->format('d/m/Y H:i:s'),
            'total_tables' => $totalTables,

            'source_tables' => $tableNames,
            'source_tables_text' => implode(', ', $tableNames),
            'stage' => 'writing_dump',
            'stage_label' => 'Menulis dump SQL standar',
            'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
        ];

        fwrite($handle, "-- PostgreSQL Migration Dump\n");
        fwrite($handle, "-- Generated : {$timestamp}\n");
        fwrite($handle, "-- App : " . config('app.name') . "\n\n");
        fwrite($handle, "SET session_replication_role = replica;\nBEGIN;\n\n");

        $sequenceDefs = $this->collectSequenceDefinitions($source);
        if (!empty($sequenceDefs)) {
            fwrite($handle, "-- SEQUENCES\n");
            foreach ($sequenceDefs as $sequenceDef) {
                fwrite($handle, $sequenceDef . "\n");
            }
            fwrite($handle, "\n");
        }

        $total = max(count($tableNames), 1);
        foreach ($tableNames as $index => $table) {
            $processed = $index + 1;
            $pct = 12 + (int) ((($processed) / $total) * 28);

            $this->setProgress($token, min($pct, 40), 'Menyusun struktur tabel ' . $table . ' (' . $processed . '/' . $total . ')...', null, null, array_merge($baseMeta, [
                'processed_tables' => $processed,
                'current_table' => $table,
            ]));

            fwrite($handle, '-- TABLE: ' . $table . "\n");
            fwrite($handle, 'DROP TABLE IF EXISTS ' . $this->quoteIdentifier('public') . '.' . $this->quoteIdentifier($table) . ' CASCADE;' . "\n");
            fwrite($handle, $this->buildCreateTableSql($source, $table) . "\n\n");

            if ($mode === 'full') {
                $this->appendTableDataStatements($source, $handle, $table, $token, $userName, $mode, $startedAt, $startedTs, $sourceLabel, $destinationLabel, $processed, $total);
            }
        }

        if ($mode === 'full') {
            fwrite($handle, "-- SEQUENCE RESETS\n");
            foreach ($this->collectSequenceResetStatements($source, $tableNames) as $sequenceResetSql) {
                fwrite($handle, $sequenceResetSql . "\n");
            }
            fwrite($handle, "\n");
        }

        $fkRows = $source->select("SELECT tc.constraint_name, tc.table_name, kcu.column_name, ccu.table_name AS foreign_table_name, ccu.column_name AS foreign_column_name, rc.update_rule, rc.delete_rule, kcu.ordinal_position FROM information_schema.table_constraints tc JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name AND tc.constraint_schema = kcu.constraint_schema JOIN information_schema.constraint_column_usage ccu ON ccu.constraint_name = tc.constraint_name AND ccu.constraint_schema = tc.constraint_schema JOIN information_schema.referential_constraints rc ON rc.constraint_name = tc.constraint_name AND rc.constraint_schema = tc.constraint_schema WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = 'public' ORDER BY tc.table_name, tc.constraint_name, kcu.ordinal_position");
        $foreignKeys = $this->groupForeignKeys($fkRows, $tableNames);

        if (!empty($foreignKeys)) {
            fwrite($handle, "-- FOREIGN KEYS\n");
            foreach ($foreignKeys as $fk) {
                fwrite($handle, $this->buildForeignKeySql($fk) . "\n");
            }
            fwrite($handle, "\n");
        }

        $indexesByTable = $this->collectIndexesByTable($source, $tableNames);
        if (!empty($indexesByTable)) {
            fwrite($handle, "-- INDEXES\n");
            foreach ($tableNames as $table) {
                foreach ($indexesByTable[$table] ?? [] as $indexSql) {
                    fwrite($handle, $indexSql . "\n");
                }
            }
            fwrite($handle, "\n");
        }

        $triggerFunctionDefs = $this->collectTriggerFunctionDefinitions($source, $tableNames);
        if (!empty($triggerFunctionDefs)) {
            fwrite($handle, "-- TRIGGER FUNCTIONS\n");
            foreach ($triggerFunctionDefs as $triggerFunctionDef) {
                fwrite($handle, $triggerFunctionDef . "\n\n");
            }
        }

        $triggersByTable = $this->collectTriggersByTable($source, $tableNames);
        if (!empty($triggersByTable)) {
            fwrite($handle, "-- TRIGGERS\n");
            foreach ($tableNames as $table) {
                foreach ($triggersByTable[$table] ?? [] as $triggerSql) {
                    fwrite($handle, $triggerSql . "\n");
                }
            }
            fwrite($handle, "\n");
        }

        fwrite($handle, "COMMIT;\nSET session_replication_role = DEFAULT;\n");
        fclose($handle);
    }

    private function executeStandardMigrationDump(Connection $destination, string $sqlFile, string $token, string $userName, string $mode, \Illuminate\Support\Carbon $startedAt, float $startedTs, string $sourceLabel, string $destinationLabel, int $totalTables): void
    {
        $totalBytes = max((int) (@filesize($sqlFile) ?: 0), 1);
        $touchedTables = [];

        $this->setProgress($token, 60, 'Menjalankan dump SQL standar ke destination...', null, null, [
            'operation' => 'migration',
            'user' => $userName,
            'migration_mode' => $mode,
            'migration_mode_label' => $mode === 'structure' ? 'DB Structure Only' : 'Full Migration (Structure + data)',
            'source_label' => $sourceLabel,
            'destination_label' => $destinationLabel,
            'migration_date' => $startedAt->format('d/m/Y H:i:s'),
            'stage' => 'applying_dump',
            'stage_label' => 'Menerapkan SQL ke destination',
            'total_tables' => $totalTables,
            'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
        ]);

        $destination->statement('SET session_replication_role = replica;');
        $destination->beginTransaction();

        try {
            $handle = fopen($sqlFile, 'r');
            if ($handle === false) {
                throw new \RuntimeException('Tidak bisa membuka dump migration.');
            }

            $lastPct = 60;
            $appliedStatements = 0;

            foreach ($this->streamSqlStatements($handle) as $chunk) {
                $stmt = $this->stripLeadingSqlComments((string) $chunk['sql']);
                if ($stmt === '') {
                    continue;
                }

                $upper = strtoupper($stmt);
                if (in_array($upper, ['BEGIN', 'COMMIT'], true)) {
                    continue;
                }
                if (str_starts_with($upper, 'SET SESSION_REPLICATION_ROLE')) {
                    continue;
                }

                $destination->statement($stmt);
                $appliedStatements++;

                $currentAction = $this->summarizeSqlStatement($stmt);
                $detectedTable = $this->extractTableNameFromStatement($stmt);
                if ($detectedTable !== null) {
                    $touchedTables[$detectedTable] = true;
                }

                $processedTables = count($touchedTables);
                $tableTransferPct = (int) round(($processedTables / max($totalTables, 1)) * 100);

                $pct = 60 + (int) ((((int) $chunk['bytes']) / $totalBytes) * 38);
                if ($pct > $lastPct || ($appliedStatements % 25) === 0) {
                    $lastPct = max($lastPct, $pct);
                    $this->setProgress($token, min($pct, 98), 'Menerapkan SQL ke destination: ' . $currentAction . ' (stmt #' . $appliedStatements . ')...', null, null, [
                        'operation' => 'migration',
                        'user' => $userName,
                        'migration_mode' => $mode,
                        'migration_mode_label' => $mode === 'structure' ? 'DB Structure Only' : 'Full Migration (Structure + data)',
                        'source_label' => $sourceLabel,
                        'destination_label' => $destinationLabel,
                        'migration_date' => $startedAt->format('d/m/Y H:i:s'),
                        'stage' => 'applying_dump',
                        'stage_label' => 'Menerapkan SQL ke destination',
                        'total_tables' => $totalTables,
                        'processed_tables' => $processedTables,
                        'table_transfer_pct' => $tableTransferPct,
                        'applied_statements' => $appliedStatements,
                        'current_action' => $currentAction,
                        'current_table' => $detectedTable,
                        'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
                    ]);
                }
            }

            fclose($handle);
            $destination->commit();
        } catch (\Throwable $e) {
            $destination->rollBack();
            throw $e;
        } finally {
            $destination->statement('SET session_replication_role = DEFAULT;');
        }
    }

    /**
     * Stream SQL statements from a dump file while respecting single-quote strings,
     * dollar-quoted blocks (e.g. $function$ ... $function$), and line comments.
     * This prevents splitting trigger/function bodies that contain ';' or newlines.
     *
     * @param resource $handle
     * @return \Generator<array{sql:string,bytes:int}>
     */
    private function streamSqlStatements($handle): \Generator
    {
        $buffer = '';
        $inString = false;
        $dollarTag = null;
        $inLineComment = false;
        $bytes = 0;

        while (($line = fgets($handle)) !== false) {
            $bytes += strlen($line);
            $len = strlen($line);
            $i = 0;

            while ($i < $len) {
                $ch = $line[$i];

                if ($inLineComment) {
                    $buffer .= $ch;
                    if ($ch === "\n") {
                        $inLineComment = false;
                    }
                    $i++;
                    continue;
                }

                if ($dollarTag !== null) {
                    if ($ch === '$' && substr($line, $i, strlen($dollarTag)) === $dollarTag) {
                        $buffer .= $dollarTag;
                        $i += strlen($dollarTag);
                        $dollarTag = null;
                        continue;
                    }
                    $buffer .= $ch;
                    $i++;
                    continue;
                }

                if ($inString) {
                    $buffer .= $ch;
                    if ($ch === "'") {
                        if ($i + 1 < $len && $line[$i + 1] === "'") {
                            $buffer .= "'";
                            $i += 2;
                            continue;
                        }
                        $inString = false;
                    }
                    $i++;
                    continue;
                }

                if ($ch === '-' && $i + 1 < $len && $line[$i + 1] === '-') {
                    $inLineComment = true;
                    $buffer .= '--';
                    $i += 2;
                    continue;
                }

                if ($ch === '$' && preg_match('/^\$[A-Za-z0-9_]*\$/', substr($line, $i), $m) === 1) {
                    $dollarTag = $m[0];
                    $buffer .= $dollarTag;
                    $i += strlen($dollarTag);
                    continue;
                }

                if ($ch === "'") {
                    $inString = true;
                    $buffer .= $ch;
                    $i++;
                    continue;
                }

                if ($ch === ';') {
                    $stmt = trim($buffer);
                    $buffer = '';
                    $i++;
                    if ($stmt !== '') {
                        yield ['sql' => $stmt, 'bytes' => $bytes];
                    }
                    continue;
                }

                $buffer .= $ch;
                $i++;
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            yield ['sql' => $tail, 'bytes' => $bytes];
        }
    }

    private function stripLeadingSqlComments(string $stmt): string
    {
        $stmt = ltrim($stmt);

        while (str_starts_with($stmt, '--')) {
            $newlinePos = strpos($stmt, "\n");
            if ($newlinePos === false) {
                return '';
            }
            $stmt = ltrim(substr($stmt, $newlinePos + 1));
        }

        return $stmt;
    }

    private function summarizeSqlStatement(string $stmt): string
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $stmt) ?? ''));

        if (str_starts_with($normalized, 'INSERT INTO')) {
            return 'Insert data';
        }
        if (str_starts_with($normalized, 'CREATE TABLE')) {
            return 'Create table';
        }
        if (str_starts_with($normalized, 'ALTER TABLE')) {
            return 'Alter table';
        }
        if (str_starts_with($normalized, 'CREATE INDEX') || str_starts_with($normalized, 'CREATE UNIQUE INDEX')) {
            return 'Create index';
        }
        if (str_starts_with($normalized, 'CREATE OR REPLACE FUNCTION')) {
            return 'Create trigger function';
        }
        if (str_starts_with($normalized, 'CREATE TRIGGER')) {
            return 'Create trigger';
        }
        if (str_starts_with($normalized, 'DROP TABLE')) {
            return 'Drop table';
        }

        return 'Executing SQL';
    }

    private function extractTableNameFromStatement(string $stmt): ?string
    {
        $patterns = [
            '/^\s*INSERT\s+INTO\s+"?public"?\."?([a-zA-Z0-9_]+)"?/i',
            '/^\s*CREATE\s+TABLE\s+"?public"?\."?([a-zA-Z0-9_]+)"?/i',
            '/^\s*DROP\s+TABLE\s+(IF\s+EXISTS\s+)?"?public"?\."?([a-zA-Z0-9_]+)"?/i',
            '/^\s*ALTER\s+TABLE\s+"?public"?\."?([a-zA-Z0-9_]+)"?/i',
        ];

        foreach ($patterns as $idx => $pattern) {
            if (preg_match($pattern, $stmt, $matches) === 1) {
                if ($idx === 2 && isset($matches[2])) {
                    return (string) $matches[2];
                }
                if (isset($matches[1])) {
                    return (string) $matches[1];
                }
            }
        }

        return null;
    }

    private function appendTableDataStatements(Connection $source, $handle, string $table, string $token, string $userName, string $mode, \Illuminate\Support\Carbon $startedAt, float $startedTs, string $sourceLabel, string $destinationLabel, int $processed, int $totalTables): void
    {
        $columns = $source->select("SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name=? ORDER BY ordinal_position", [$table]);
        $columnNames = collect($columns)->pluck('column_name')->map(fn ($name) => (string) $name)->values()->all();

        if (empty($columnNames)) {
            return;
        }

        $colList = collect($columnNames)->map(fn ($column) => $this->quoteIdentifier($column))->implode(', ');
        $firstCol = $columnNames[0] ?? null;
        $query = $source->table($table);
        if ($firstCol) {
            $query->orderBy($firstCol);
        }

        $tableTotalRows = (int) $source->table($table)->count();
        $tableProcessedRows = 0;

        $this->setProgress($token, 40 + (int) ((($processed) / max($totalTables, 1)) * 20), 'Menyusun data tabel ' . $table . ' (' . $processed . '/' . $totalTables . ')...', null, null, [
            'operation' => 'migration',
            'user' => $userName,
            'migration_mode' => $mode,
            'migration_mode_label' => 'Full Migration (Structure + data)',
            'source_label' => $sourceLabel,
            'destination_label' => $destinationLabel,
            'migration_date' => $startedAt->format('d/m/Y H:i:s'),
            'total_tables' => $totalTables,
            'processed_tables' => $processed,
            'table_transfer_pct' => (int) round(($processed / max($totalTables, 1)) * 100),
            'current_table' => $table,
            'table_rows_processed' => $tableProcessedRows,
            'table_rows_total' => $tableTotalRows,
            'stage' => 'writing_data',
            'stage_label' => 'Menulis data SQL',
            'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
        ]);

        $query->chunk(500, function ($rows) use ($handle, $table, $colList, $token, $userName, $mode, $sourceLabel, $destinationLabel, $startedAt, $startedTs, $processed, $totalTables, $tableTotalRows, &$tableProcessedRows) {
            foreach ($rows as $row) {
                $values = collect((array) $row)->map(fn ($value) => $this->formatSqlValue($value))->implode(', ');
                fwrite($handle, 'INSERT INTO ' . $this->quoteIdentifier('public') . '.' . $this->quoteIdentifier($table) . ' (' . $colList . ') VALUES (' . $values . ");\n");
            }

            $tableProcessedRows += count($rows);
            $tableRowPct = $tableTotalRows > 0
                ? (int) round(($tableProcessedRows / $tableTotalRows) * 100)
                : 100;
            $globalBasePct = 40 + (int) ((($processed - 1) / max($totalTables, 1)) * 20);
            $globalCurrentPct = min(60, $globalBasePct + (int) round(($tableRowPct / 100) * (20 / max($totalTables, 1))));

            $this->setProgress($token, max(40, $globalCurrentPct), 'Transfer data tabel ' . $table . ' (' . $tableRowPct . '%)...', null, null, [
                'operation' => 'migration',
                'user' => $userName,
                'migration_mode' => $mode,
                'migration_mode_label' => 'Full Migration (Structure + data)',
                'source_label' => $sourceLabel,
                'destination_label' => $destinationLabel,
                'migration_date' => $startedAt->format('d/m/Y H:i:s'),
                'total_tables' => $totalTables,
                'processed_tables' => $processed,
                'table_transfer_pct' => (int) round(($processed / max($totalTables, 1)) * 100),
                'current_table' => $table,
                'table_rows_processed' => $tableProcessedRows,
                'table_rows_total' => $tableTotalRows,
                'stage' => 'writing_data',
                'stage_label' => 'Transfer data per tabel',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]);
        });

        fwrite($handle, "\n");
    }

    private function collectSequenceDefinitions(Connection $source): array
    {
        $identityBackedSequenceRows = $source->select("SELECT DISTINCT regexp_replace(col.column_default, '^nextval\\(''([^'']+)''::regclass\\)$', '\\1') AS sequence_name FROM information_schema.columns col WHERE col.table_schema = 'public' AND col.column_default LIKE 'nextval(%'");

        $identityBackedSequenceNames = collect($identityBackedSequenceRows)
            ->pluck('sequence_name')
            ->filter()
            ->map(function ($sequenceName) {
                $parts = explode('.', (string) $sequenceName);

                return end($parts) ?: (string) $sequenceName;
            })
            ->values()
            ->all();

        $sequenceRows = $source->select("SELECT c.relname AS sequence_name, s.seqstart AS start_value, s.seqincrement AS increment, s.seqmax AS max_value, s.seqmin AS min_value, s.seqcache AS cache_size, s.seqcycle AS cycle FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace LEFT JOIN pg_sequence s ON s.seqrelid = c.oid WHERE n.nspname = 'public' AND c.relkind = 'S' ORDER BY c.relname");

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

            $sequenceDefs[] = 'CREATE SEQUENCE IF NOT EXISTS ' . $this->quoteIdentifier('public') . '.' . $this->quoteIdentifier($seqName)
                . " START WITH {$startVal} INCREMENT BY {$incBy}"
                . " MINVALUE {$minVal} MAXVALUE {$maxVal}"
                . " CACHE {$cache} {$cycled};";
        }

        return $sequenceDefs;
    }

    private function collectSequenceResetStatements(Connection $source, array $tableNames): array
    {
        $intTypes = ['integer', 'bigint', 'smallint', 'int4', 'int8', 'serial', 'bigserial'];
        $statements = [];

        foreach ($tableNames as $table) {
            $quotedTable = $this->quoteIdentifier($table);
            $idCol = $source->select("SELECT data_type FROM information_schema.columns WHERE table_schema='public' AND table_name=? AND column_name='id'", [$table]);
            if (!$idCol || !in_array(strtolower((string) $idCol[0]->data_type), $intTypes, true)) {
                continue;
            }

            $seq = $source->select("SELECT pg_get_serial_sequence('{$quotedTable}', 'id') AS seq");
            if (!$seq || empty($seq[0]->seq)) {
                continue;
            }

            $statements[] = "SELECT setval(pg_get_serial_sequence('{$quotedTable}', 'id'), COALESCE((SELECT MAX(id) FROM {$quotedTable}), 0) + 1, false);";
        }

        return $statements;
    }

    private function collectIndexesByTable(Connection $source, array $tableNames): array
    {
        $indexRows = $source->select("SELECT tablename, indexname, indexdef FROM pg_indexes WHERE schemaname = 'public' ORDER BY tablename, indexname");
        $tableSet = array_flip($tableNames);

        $indexesByTable = [];
        foreach ($indexRows as $indexRow) {
            $tableName = (string) $indexRow->tablename;
            if (!isset($tableSet[$tableName])) {
                continue;
            }

            $indexName = (string) $indexRow->indexname;
            if ($indexName === $tableName . '_pkey') {
                continue;
            }

            $indexDef = (string) $indexRow->indexdef;
            $indexDef = preg_replace('/^CREATE UNIQUE INDEX\s+/i', 'CREATE UNIQUE INDEX IF NOT EXISTS ', $indexDef) ?? $indexDef;
            $indexDef = preg_replace('/^CREATE INDEX\s+/i', 'CREATE INDEX IF NOT EXISTS ', $indexDef) ?? $indexDef;

            $indexesByTable[$tableName][] = rtrim($indexDef, ';') . ';';
        }

        return $indexesByTable;
    }

    private function collectTriggerFunctionDefinitions(Connection $source, array $tableNames): array
    {
        $triggerRows = $source->select("SELECT DISTINCT p.oid, p.proname AS function_name, pg_get_functiondef(p.oid) AS function_def FROM pg_trigger t JOIN pg_class c ON c.oid = t.tgrelid JOIN pg_namespace n ON n.oid = c.relnamespace JOIN pg_proc p ON p.oid = t.tgfoid WHERE n.nspname = 'public' AND NOT t.tgisinternal ORDER BY p.proname");

        $tableSet = array_flip($tableNames);
        $definitions = [];
        foreach ($triggerRows as $triggerRow) {
            $definitions[] = rtrim((string) $triggerRow->function_def, ';') . ';';
        }

        return $definitions;
    }

    private function collectTriggersByTable(Connection $source, array $tableNames): array
    {
        $triggerRows = $source->select("SELECT c.relname AS table_name, t.tgname AS trigger_name, pg_get_triggerdef(t.oid, true) AS trigger_def FROM pg_trigger t JOIN pg_class c ON c.oid = t.tgrelid JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' AND NOT t.tgisinternal ORDER BY c.relname, t.tgname");
        $tableSet = array_flip($tableNames);

        $triggersByTable = [];
        foreach ($triggerRows as $triggerRow) {
            $tableName = (string) $triggerRow->table_name;
            if (!isset($tableSet[$tableName])) {
                continue;
            }

            $triggersByTable[$tableName][] = rtrim((string) $triggerRow->trigger_def, ';') . ';';
        }

        return $triggersByTable;
    }

    private function groupForeignKeys(array $fkRows, array $tableNames): array
    {
        $tableSet = array_flip($tableNames);
        $foreignKeys = [];

        foreach ($fkRows as $row) {
            $tableName = (string) $row->table_name;
            $foreignTableName = (string) $row->foreign_table_name;
            if (!isset($tableSet[$tableName]) || !isset($tableSet[$foreignTableName])) {
                continue;
            }

            $key = $row->table_name . '|' . $row->constraint_name;
            if (!isset($foreignKeys[$key])) {
                $foreignKeys[$key] = [
                    'table_name' => $tableName,
                    'constraint_name' => (string) $row->constraint_name,
                    'foreign_table_name' => $foreignTableName,
                    'update_rule' => (string) $row->update_rule,
                    'delete_rule' => (string) $row->delete_rule,
                    'columns' => [],
                    'foreign_columns' => [],
                ];
            }

            $foreignKeys[$key]['columns'][] = (string) $row->column_name;
            $foreignKeys[$key]['foreign_columns'][] = (string) $row->foreign_column_name;
        }

        return array_values($foreignKeys);
    }

    private function buildForeignKeySql(array $fk): string
    {
        $localCols = collect($fk['columns'])->map(fn ($name) => $this->quoteIdentifier((string) $name))->implode(', ');
        $foreignCols = collect($fk['foreign_columns'])->map(fn ($name) => $this->quoteIdentifier((string) $name))->implode(', ');

        return 'ALTER TABLE ' . $this->quoteIdentifier('public') . '.' . $this->quoteIdentifier((string) $fk['table_name'])
            . ' ADD CONSTRAINT ' . $this->quoteIdentifier((string) $fk['constraint_name'])
            . ' FOREIGN KEY (' . $localCols . ') REFERENCES '
            . $this->quoteIdentifier('public') . '.' . $this->quoteIdentifier((string) $fk['foreign_table_name'])
            . ' (' . $foreignCols . ')' . $this->buildForeignKeyRuleSql((string) $fk['update_rule'], 'UPDATE') . $this->buildForeignKeyRuleSql((string) $fk['delete_rule'], 'DELETE') . ';';
    }

    private function formatSqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return "'" . $value->format('Y-m-d H:i:s') . "'";
        }
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }

    private function createTables(Connection $source, Connection $destination, array $tableNames, string $token, string $userName, string $mode, \Illuminate\Support\Carbon $startedAt, float $startedTs): void
    {
        $total = max(count($tableNames), 1);
        foreach ($tableNames as $index => $table) {
            $pct = 10 + (int) ((($index + 1) / $total) * 35);
            $this->setProgress($token, min($pct, 45), 'Membuat struktur tabel ' . $table . ' (' . ($index + 1) . '/' . $total . ')...', null, null, [
                'operation' => 'migration',
                'user' => $userName,
                'migration_mode' => $mode,
                'migration_mode_label' => $mode === 'structure' ? 'DB Structure Only' : 'Full Migration (Structure + data)',
                'source_label' => $this->formatConnectionLabelFromConnection($source),
                'destination_label' => $this->formatConnectionLabelFromConnection($destination),
                'migration_date' => $startedAt->format('d/m/Y H:i:s'),
                'total_tables' => $total,
                'processed_tables' => $index + 1,
                'current_table' => $table,
                'stage' => 'creating_tables',
                'stage_label' => 'Membuat struktur',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]);

            $destination->statement($this->buildCreateTableSql($source, $table));
        }
    }

    private function createForeignKeys(Connection $source, Connection $destination, array $tableNames, string $token, string $userName, string $mode, \Illuminate\Support\Carbon $startedAt, float $startedTs): void
    {
        $fkRows = $source->select("SELECT tc.constraint_name, tc.table_name, kcu.column_name, ccu.table_name AS foreign_table_name, ccu.column_name AS foreign_column_name, rc.update_rule, rc.delete_rule, kcu.ordinal_position FROM information_schema.table_constraints tc JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name AND tc.constraint_schema = kcu.constraint_schema JOIN information_schema.constraint_column_usage ccu ON ccu.constraint_name = tc.constraint_name AND ccu.constraint_schema = tc.constraint_schema JOIN information_schema.referential_constraints rc ON rc.constraint_name = tc.constraint_name AND rc.constraint_schema = tc.constraint_schema WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = 'public' ORDER BY tc.table_name, tc.constraint_name, kcu.ordinal_position");

        $tableSet = array_flip($tableNames);
        $destinationTableSet = array_flip($this->getTableNamesFromConnection($destination));

        $foreignKeys = [];
        foreach ($fkRows as $row) {
            $tableName = (string) $row->table_name;
            $foreignTableName = (string) $row->foreign_table_name;

            // Hanya proses FK untuk tabel yang memang dimigrasikan dan sudah ada di destination.
            if (!isset($tableSet[$tableName]) || !isset($tableSet[$foreignTableName])) {
                continue;
            }
            if (!isset($destinationTableSet[$tableName]) || !isset($destinationTableSet[$foreignTableName])) {
                continue;
            }

            $key = $row->table_name . '|' . $row->constraint_name;
            if (!isset($foreignKeys[$key])) {
                $foreignKeys[$key] = [
                    'table_name' => $tableName,
                    'constraint_name' => (string) $row->constraint_name,
                    'foreign_table_name' => $foreignTableName,
                    'update_rule' => (string) $row->update_rule,
                    'delete_rule' => (string) $row->delete_rule,
                    'columns' => [],
                    'foreign_columns' => [],
                ];
            }

            $foreignKeys[$key]['columns'][] = (string) $row->column_name;
            $foreignKeys[$key]['foreign_columns'][] = (string) $row->foreign_column_name;
        }

        $total = max(count($foreignKeys), 1);
        foreach (array_values($foreignKeys) as $index => $fk) {
            $pct = 45 + (int) ((($index + 1) / $total) * 15);
            $this->setProgress($token, min($pct, 60), 'Menambahkan foreign key ' . $fk['constraint_name'] . ' (' . ($index + 1) . '/' . $total . ')...', null, null, [
                'operation' => 'migration',
                'user' => $userName,
                'migration_mode' => $mode,
                'migration_mode_label' => $mode === 'structure' ? 'DB Structure Only' : 'Full Migration (Structure + data)',
                'source_label' => $this->formatConnectionLabelFromConnection($source),
                'destination_label' => $this->formatConnectionLabelFromConnection($destination),
                'migration_date' => $startedAt->format('d/m/Y H:i:s'),
                'stage' => 'creating_foreign_keys',
                'stage_label' => 'Menambahkan foreign key',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]);

            $sql = 'ALTER TABLE ' . $this->quoteIdentifier('public') . '.' . $this->quoteIdentifier((string) $fk['table_name'])
                . ' ADD CONSTRAINT ' . $this->quoteIdentifier((string) $fk['constraint_name'])
                . ' FOREIGN KEY (' . collect($fk['columns'])->map(fn ($name) => $this->quoteIdentifier((string) $name))->implode(', ') . ')'
                . ' REFERENCES ' . $this->quoteIdentifier('public') . '.' . $this->quoteIdentifier((string) $fk['foreign_table_name'])
                . ' (' . collect($fk['foreign_columns'])->map(fn ($name) => $this->quoteIdentifier((string) $name))->implode(', ') . ')'
                . $this->buildForeignKeyRuleSql((string) $fk['update_rule'], 'UPDATE')
                . $this->buildForeignKeyRuleSql((string) $fk['delete_rule'], 'DELETE')
                . ';';

            $destination->statement($sql);
        }
    }

    private function createIndexes(Connection $source, Connection $destination, array $tableNames, string $token, string $userName, string $mode, \Illuminate\Support\Carbon $startedAt, float $startedTs): void
    {
        $indexRows = $source->select("SELECT tablename, indexname, indexdef FROM pg_indexes WHERE schemaname = 'public' ORDER BY tablename, indexname");
        $tableSet = array_flip($tableNames);
        $destinationTableSet = array_flip($this->getTableNamesFromConnection($destination));

        $indexes = [];
        foreach ($indexRows as $indexRow) {
            $tableName = (string) $indexRow->tablename;
            if (!isset($tableSet[$tableName]) || !isset($destinationTableSet[$tableName])) {
                continue;
            }

            $indexName = (string) $indexRow->indexname;
            if ($indexName === $tableName . '_pkey') {
                continue;
            }
            $indexDef = rtrim((string) $indexRow->indexdef, ';') . ';';
            $indexDef = preg_replace('/^CREATE UNIQUE INDEX\s+/i', 'CREATE UNIQUE INDEX IF NOT EXISTS ', $indexDef) ?? $indexDef;
            $indexDef = preg_replace('/^CREATE INDEX\s+/i', 'CREATE INDEX IF NOT EXISTS ', $indexDef) ?? $indexDef;
            $indexes[] = $indexDef;
        }

        $total = max(count($indexes), 1);
        foreach ($indexes as $index => $indexSql) {
            $pct = 60 + (int) ((($index + 1) / $total) * 10);
            $this->setProgress($token, min($pct, 70), 'Membuat index dan constraint tambahan (' . ($index + 1) . '/' . $total . ')...', null, null, [
                'operation' => 'migration',
                'user' => $userName,
                'migration_mode' => $mode,
                'migration_mode_label' => $mode === 'structure' ? 'DB Structure Only' : 'Full Migration (Structure + data)',
                'source_label' => $this->formatConnectionLabelFromConnection($source),
                'destination_label' => $this->formatConnectionLabelFromConnection($destination),
                'migration_date' => $startedAt->format('d/m/Y H:i:s'),
                'stage' => 'creating_indexes',
                'stage_label' => 'Membuat index',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]);

            $destination->statement($indexSql);
        }
    }

    private function copyTableData(Connection $source, Connection $destination, array $tableNames, string $token, string $userName, \Illuminate\Support\Carbon $startedAt, float $startedTs): void
    {
        $total = max(count($tableNames), 1);
        foreach ($tableNames as $index => $table) {
            $this->setProgress($token, 70 + (int) ((($index + 1) / $total) * 24), 'Menyalin data tabel ' . $table . ' (' . ($index + 1) . '/' . $total . ')...', null, null, [
                'operation' => 'migration',
                'user' => $userName,
                'migration_mode' => 'full',
                'migration_mode_label' => 'Full Migration (Structure + data)',
                'source_label' => $this->formatConnectionLabelFromConnection($source),
                'destination_label' => $this->formatConnectionLabelFromConnection($destination),
                'migration_date' => $startedAt->format('d/m/Y H:i:s'),
                'total_tables' => $total,
                'processed_tables' => $index + 1,
                'current_table' => $table,
                'stage' => 'copying_data',
                'stage_label' => 'Menyalin data',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]);

            $this->copySingleTableData($source, $destination, $table);
        }
    }

    private function copySingleTableData(Connection $source, Connection $destination, string $table): void
    {
        $pkColumn = $this->getPrimaryKeyColumn($source, $table);
        $query = $source->table($table);

        if ($pkColumn) {
            $query->orderBy($pkColumn);
        }

        $buffer = [];
        $query->chunk(500, function ($rows) use ($destination, $table, &$buffer) {
            foreach ($rows as $row) {
                $buffer[] = (array) $row;
                if (count($buffer) >= 250) {
                    $destination->table($table)->insert($buffer);
                    $buffer = [];
                }
            }
        });

        if (!empty($buffer)) {
            $destination->table($table)->insert($buffer);
        }
    }

    private function resetTableSequences(Connection $source, Connection $destination, array $tableNames, string $token, string $userName, \Illuminate\Support\Carbon $startedAt, float $startedTs): void
    {
        $intTypes = ['integer', 'bigint', 'smallint', 'int4', 'int8', 'serial', 'bigserial'];
        $total = max(count($tableNames), 1);

        foreach ($tableNames as $index => $table) {
            $pct = 94 + (int) ((($index + 1) / $total) * 4);
            $this->setProgress($token, min($pct, 98), 'Menyesuaikan sequence tabel ' . $table . ' (' . ($index + 1) . '/' . $total . ')...', null, null, [
                'operation' => 'migration',
                'user' => $userName,
                'migration_mode' => 'full',
                'migration_mode_label' => 'Full Migration (Structure + data)',
                'source_label' => $this->formatConnectionLabelFromConnection($source),
                'destination_label' => $this->formatConnectionLabelFromConnection($destination),
                'migration_date' => $startedAt->format('d/m/Y H:i:s'),
                'total_tables' => $total,
                'processed_tables' => $index + 1,
                'current_table' => $table,
                'stage' => 'reset_sequence',
                'stage_label' => 'Reset sequence',
                'duration_label' => $this->formatDuration(microtime(true) - $startedTs),
            ]);

            $idCol = $source->select(
                "SELECT data_type FROM information_schema.columns WHERE table_schema='public' AND table_name=? AND column_name='id'",
                [$table]
            );

            if (!$idCol || !in_array(strtolower((string) $idCol[0]->data_type), $intTypes, true)) {
                continue;
            }

            $seq = $source->select("SELECT pg_get_serial_sequence('\"" . $table . "\"', 'id') AS seq");
            if (!$seq || empty($seq[0]->seq)) {
                continue;
            }

            $destination->statement(
                "SELECT setval(pg_get_serial_sequence('\"{$table}\"', 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 0) + 1, false);"
            );
        }
    }

    private function getPrimaryKeyColumn(Connection $source, string $table): ?string
    {
        $rows = $source->select("SELECT a.attname AS column_name FROM pg_index i JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) WHERE i.indrelid = to_regclass(?) AND i.indisprimary ORDER BY a.attnum", ["public.{$table}"]);

        return $rows[0]->column_name ?? null;
    }

    private function getTableNamesFromConnection(Connection $connection): array
    {
        $rows = $connection->select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'");

        return collect($rows)
            ->pluck('table_name')
            ->map(fn ($name) => (string) $name)
            ->values()
            ->all();
    }

    private function buildCreateTableSql(Connection $source, string $table): string
    {
        $columns = $source->select("SELECT col.column_name, col.data_type, col.udt_name, col.character_maximum_length, col.numeric_precision, col.numeric_scale, col.is_nullable, col.column_default, col.is_identity, col.identity_generation FROM information_schema.columns col WHERE col.table_schema = 'public' AND col.table_name = ? ORDER BY col.ordinal_position", [$table]);

        $columnLines = [];
        foreach ($columns as $column) {
            $columnName = $this->quoteIdentifier((string) $column->column_name);
            $typeSql = $this->buildColumnTypeSql($column);
            $nullableSql = strtoupper((string) $column->is_nullable) === 'NO' ? ' NOT NULL' : '';
            $defaultSql = $this->buildColumnDefaultSql($column);
            $columnLines[] = "    {$columnName} {$typeSql}{$defaultSql}{$nullableSql}";
        }

        $pkColumns = $source->select("SELECT a.attname AS column_name FROM pg_index i JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) WHERE i.indrelid = to_regclass(?) AND i.indisprimary ORDER BY a.attnum", ["public.{$table}"]);

        if (!empty($pkColumns)) {
            $pkList = collect($pkColumns)->pluck('column_name')->map(fn ($name) => $this->quoteIdentifier((string) $name))->implode(', ');
            $columnLines[] = "    PRIMARY KEY ({$pkList})";
        }

        return 'CREATE TABLE ' . $this->quoteIdentifier('public') . '.' . $this->quoteIdentifier($table) . " (\n" . implode(",\n", $columnLines) . "\n);";
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
        if ($udtName === '') {
            return '';
        }

        $typeRow = DB::selectOne(
            "SELECT et.typname FROM pg_type pt JOIN pg_type et ON pt.typelem = et.oid WHERE pt.typname = ? LIMIT 1",
            [$udtName]
        );

        if ($typeRow && !empty($typeRow->typname)) {
            return strtolower((string) $typeRow->typname);
        }

        return '';
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

    private function quoteIdentifier(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    private function formatConnectionLabelFromConnection(Connection $connection): string
    {
        $config = $connection->getConfig();

        return $this->formatConnectionLabel([
            'driver' => $config['driver'] ?? 'pgsql',
            'host' => $config['host'] ?? '-',
            'database' => $config['database'] ?? '-',
        ]);
    }

    private function formatDuration(float $seconds): string
    {
        $seconds = max(0, (int) round($seconds));
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
}
