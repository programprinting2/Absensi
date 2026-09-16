<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DatabaseInfoController extends Controller
{
    public function index()
    {
        \App\Support\MenuRegistry::syncNewMenusToRoles();

        $connection = $this->resolveActiveConnectionInfo();

        // Tables with row count
        $tables = DB::select("
            SELECT
                t.table_name,
                COALESCE(s.n_live_tup, 0) AS row_count
            FROM information_schema.tables t
            LEFT JOIN pg_stat_user_tables s ON s.relname = t.table_name
            WHERE t.table_schema = 'public'
              AND t.table_type = 'BASE TABLE'
            ORDER BY t.table_name
        ");

        // Table sizes
        $sizes = DB::select("
            SELECT
                table_name,
                pg_size_pretty(pg_total_relation_size(quote_ident(table_name))) AS size,
                pg_total_relation_size(quote_ident(table_name)) AS raw_size
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_type = 'BASE TABLE'
            ORDER BY raw_size DESC
        ");

        $sizeMap = collect($sizes)->keyBy('table_name');

        // Merge row count and size
        $tableData = collect($tables)->map(function ($t) use ($sizeMap) {
            return [
                'name'      => $t->table_name,
                'row_count' => number_format($t->row_count),
                'size'      => $sizeMap[$t->table_name]->size ?? '-',
                'raw_size'  => $sizeMap[$t->table_name]->raw_size ?? 0,
            ];
        })->sortByDesc('raw_size')->values();

        // Total DB size
        $totalSize = DB::selectOne("
            SELECT pg_size_pretty(SUM(pg_total_relation_size(quote_ident(table_name)))) AS total
            FROM information_schema.tables
            WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
        ");

        // Migration history (destination bisa tidak punya tabel migrations)
        $migrations = Schema::hasTable('migrations')
            ? DB::select("SELECT * FROM migrations ORDER BY batch DESC, id DESC")
            : [];

        $backupHistory = app(\App\Services\DatabaseBackupService::class)->listBackupHistory();
        $backupCount = count($backupHistory);
        $backupSchedule = app(\App\Services\DatabaseScheduledBackupService::class)->getStatus();

        return view('tools.database', compact(
            'connection', 'tableData', 'totalSize', 'migrations', 'backupHistory', 'backupCount', 'backupSchedule'
        ));
    }

    // POST /tools/database/tables/clear
    // Mengosongkan ISI (record) tabel terpilih tanpa menghapus struktur tabel.
    public function clearTables(Request $request)
    {
        $data = $request->validate([
            'tables' => 'required|array|min:1',
            'tables.*' => 'required|string|max:255',
        ]);

        // Validasi terhadap daftar tabel asli untuk mencegah SQL injection lewat nama tabel.
        $existingTables = collect(DB::select("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
        "))->pluck('table_name')->all();

        $selected = array_values(array_intersect($data['tables'], $existingTables));

        if (empty($selected)) {
            return response()->json([
                'success' => false,
                'error' => 'Tidak ada tabel valid yang dipilih.',
            ], 422);
        }

        $cleared = [];

        try {
            $this->tryEnableReplicationRole();
            DB::beginTransaction();

            foreach ($selected as $table) {
                $quoted = '"' . str_replace('"', '""', $table) . '"';
                DB::statement("DELETE FROM {$quoted};");

                // Reset sequence/identity kolom id (best-effort) agar mulai dari 1 lagi.
                $seqRow = DB::selectOne("SELECT pg_get_serial_sequence(?, 'id') AS seq", ["public.{$table}"]);
                if ($seqRow && !empty($seqRow->seq)) {
                    DB::statement('SELECT setval(?, 1, false)', [$seqRow->seq]);
                }

                $cleared[] = $table;
            }

            DB::commit();
            $this->tryDisableReplicationRole();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->tryDisableReplicationRole();

            return response()->json([
                'success' => false,
                'error' => 'Gagal membersihkan tabel: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'cleared' => $cleared,
            'message' => count($cleared) . ' tabel berhasil dibersihkan.',
        ]);
    }

    public function testSourceConnection(Request $request)
    {
        $payload = $request->validate([
            'driver' => 'nullable|in:mysql,pgsql',
            'host' => 'required|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'database' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
        ]);

        $driver = $payload['driver'] ?? 'mysql';
        $connectionName = 'migration_source_test';

        $config = $this->buildTestConnectionConfig($driver, $payload);

        try {
            Config::set("database.connections.{$connectionName}", $config);
            DB::purge($connectionName);

            $conn = DB::connection($connectionName);
            $conn->getPdo();

            if ($driver === 'pgsql') {
                $tableRow = $conn->selectOne("\n                    SELECT COUNT(*) AS aggregate\n                    FROM information_schema.tables\n                    WHERE table_schema = 'public'\n                      AND table_type = 'BASE TABLE'\n                ");
                $sizeRow = $conn->selectOne("SELECT pg_database_size(current_database()) AS size_bytes");
                $recordRow = $conn->selectOne("\n                    SELECT COALESCE(SUM(n_live_tup), 0) AS total_records\n                    FROM pg_stat_user_tables\n                ");
            } else {
                $tableRow = $conn->selectOne("\n                    SELECT COUNT(*) AS aggregate\n                    FROM information_schema.tables\n                    WHERE table_schema = ?\n                ", [$payload['database']]);
                $sizeRow = $conn->selectOne("\n                    SELECT COALESCE(SUM(data_length + index_length), 0) AS size_bytes\n                    FROM information_schema.tables\n                    WHERE table_schema = ?\n                ", [$payload['database']]);
                $recordRow = $conn->selectOne("\n                    SELECT COALESCE(SUM(table_rows), 0) AS total_records\n                    FROM information_schema.tables\n                    WHERE table_schema = ?\n                ", [$payload['database']]);
            }

            $tableCount = (int) ($tableRow->aggregate ?? 0);
            $sizeBytes = (int) ($sizeRow->size_bytes ?? 0);
            $totalRecords = (int) ($recordRow->total_records ?? 0);
            $tableList = $this->fetchTableList($conn, $driver, $payload['database']);

            return response()->json([
                'connected' => true,
                'driver' => strtoupper($driver),
                'database' => $payload['database'],
                'tables' => $tableCount,
                'total_records' => $totalRecords,
                'size_bytes' => $sizeBytes,
                'size_human' => $this->formatBytes($sizeBytes),
                'table_list' => $tableList,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'connected' => false,
                'error' => $e->getMessage(),
            ], 422);
        } finally {
            DB::disconnect($connectionName);
            DB::purge($connectionName);
            Config::set("database.connections.{$connectionName}", null);
        }
    }

    public function saveSourceConfig(Request $request)
    {
        $payload = $this->validateServerConfigPayload($request);

        try {
            $this->updateEnvValues([
                'MIGRATION_SOURCE_DB_DRIVER' => $payload['driver'] ?? 'mysql',
                'MIGRATION_SOURCE_DB_HOST' => $payload['host'] ?? '',
                'MIGRATION_SOURCE_DB_PORT' => (string) ($payload['port'] ?? ''),
                'MIGRATION_SOURCE_DB_DATABASE' => $payload['database'] ?? '',
                'MIGRATION_SOURCE_DB_USERNAME' => $payload['username'] ?? '',
                'MIGRATION_SOURCE_DB_PASSWORD' => $payload['password'] ?? '',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Konfigurasi Source Server berhasil disimpan ke .env.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Gagal menyimpan konfigurasi source: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function loadSourceConfig()
    {
        // Source Server Fetch membaca koneksi database aktif dari .env (DB_* / DB_URL).
        return response()->json([
            'success' => true,
            'source_label' => 'DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD',
            'config' => $this->activeConnectionConfig(),
        ]);
    }

    /**
     * Ambil konfigurasi koneksi database yang sedang aktif dipakai aplikasi.
     * Jika DB_URL diset, nilainya diurai untuk mengisi host/port/database/user/password.
     */
    private function activeConnectionConfig(): array
    {
        $default = config('database.default');
        $conn = config("database.connections.{$default}", []);

        $driver = (string) ($conn['driver'] ?? 'pgsql');
        $host = (string) ($conn['host'] ?? '');
        $port = $conn['port'] !== null && $conn['port'] !== '' ? (string) $conn['port'] : '';
        $database = (string) ($conn['database'] ?? '');
        $username = (string) ($conn['username'] ?? '');
        $password = (string) ($conn['password'] ?? '');

        // DB_URL menimpa host/port/dll bila ada.
        if (!empty($conn['url'])) {
            $parsed = parse_url((string) $conn['url']);
            if (is_array($parsed)) {
                $host = $parsed['host'] ?? $host;
                $port = isset($parsed['port']) ? (string) $parsed['port'] : $port;
                $username = isset($parsed['user']) ? rawurldecode($parsed['user']) : $username;
                $password = isset($parsed['pass']) ? rawurldecode($parsed['pass']) : $password;
                if (!empty($parsed['path'])) {
                    $database = ltrim($parsed['path'], '/') ?: $database;
                }
            }
        }

        // Normalisasi nama driver ke nilai yang dipakai form (mysql|pgsql).
        $driver = str_contains(strtolower($driver), 'mysql') ? 'mysql' : 'pgsql';
        if ($port === '') {
            $port = $driver === 'pgsql' ? '5432' : '3306';
        }

        return [
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ];
    }

    public function testDestinationConnection(Request $request)
    {
        $payload = $request->validate([
            'driver' => 'nullable|in:mysql,pgsql',
            'host' => 'required|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'database' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
        ]);

        $driver = $payload['driver'] ?? 'mysql';
        $connectionName = 'migration_destination_test';

        $config = $this->buildTestConnectionConfig($driver, $payload);

        try {
            Config::set("database.connections.{$connectionName}", $config);
            DB::purge($connectionName);

            $conn = DB::connection($connectionName);
            $conn->getPdo();

            if ($driver === 'pgsql') {
                $tableRow = $conn->selectOne("\n                    SELECT COUNT(*) AS aggregate\n                    FROM information_schema.tables\n                    WHERE table_schema = 'public'\n                      AND table_type = 'BASE TABLE'\n                ");
                $sizeRow = $conn->selectOne("SELECT pg_database_size(current_database()) AS size_bytes");
                $recordRow = $conn->selectOne("\n                    SELECT COALESCE(SUM(n_live_tup), 0) AS total_records\n                    FROM pg_stat_user_tables\n                ");
            } else {
                $tableRow = $conn->selectOne("\n                    SELECT COUNT(*) AS aggregate\n                    FROM information_schema.tables\n                    WHERE table_schema = ?\n                ", [$payload['database']]);
                $sizeRow = $conn->selectOne("\n                    SELECT COALESCE(SUM(data_length + index_length), 0) AS size_bytes\n                    FROM information_schema.tables\n                    WHERE table_schema = ?\n                ", [$payload['database']]);
                $recordRow = $conn->selectOne("\n                    SELECT COALESCE(SUM(table_rows), 0) AS total_records\n                    FROM information_schema.tables\n                    WHERE table_schema = ?\n                ", [$payload['database']]);
            }

            $tableCount = (int) ($tableRow->aggregate ?? 0);
            $sizeBytes = (int) ($sizeRow->size_bytes ?? 0);
            $totalRecords = (int) ($recordRow->total_records ?? 0);
            $isEmpty = $tableCount === 0;

            return response()->json([
                'connected' => true,
                'driver' => strtoupper($driver),
                'database' => $payload['database'],
                'tables' => $tableCount,
                'total_records' => $totalRecords,
                'size_bytes' => $sizeBytes,
                'size_human' => $this->formatBytes($sizeBytes),
                'database_empty' => $isEmpty,
                'database_status_label' => $isEmpty ? 'Database Empty' : 'Database sudah berisi data',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'connected' => false,
                'error' => $e->getMessage(),
            ], 422);
        } finally {
            DB::disconnect($connectionName);
            DB::purge($connectionName);
            Config::set("database.connections.{$connectionName}", null);
        }
    }

    public function clearDestinationData(Request $request)
    {
        $payload = $request->validate([
            'driver' => 'nullable|in:mysql,pgsql',
            'host' => 'required|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'database' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
            'confirmation' => 'required|string',
        ]);

        if (($payload['confirmation'] ?? '') !== 'CLEAR DATA') {
            return response()->json([
                'success' => false,
                'error' => 'Konfirmasi tidak valid. Ketik CLEAR DATA untuk melanjutkan.',
            ], 422);
        }

        $driver = $payload['driver'] ?? 'mysql';
        if ($driver !== 'pgsql') {
            return response()->json([
                'success' => false,
                'error' => 'Pembersihan schema otomatis saat ini hanya didukung untuk PostgreSQL.',
            ], 422);
        }

        $connectionName = 'migration_destination_clear';
        $config = $this->buildTestConnectionConfig($driver, $payload);

        try {
            Config::set("database.connections.{$connectionName}", $config);
            DB::purge($connectionName);

            $conn = DB::connection($connectionName);
            $conn->getPdo();

            $conn->statement('DROP SCHEMA public CASCADE;');
            $conn->statement('CREATE SCHEMA public;');
            $conn->statement('GRANT ALL ON SCHEMA public TO postgres;');
            $conn->statement('GRANT ALL ON SCHEMA public TO anon, authenticated, service_role;');

            return response()->json([
                'success' => true,
                'message' => 'Pembersihan data destination berhasil dilakukan.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        } finally {
            DB::disconnect($connectionName);
            DB::purge($connectionName);
            Config::set("database.connections.{$connectionName}", null);
        }
    }

    public function saveDestinationConfig(Request $request)
    {
        $payload = $this->validateServerConfigPayload($request);

        try {
            $this->updateEnvValues([
                'MIGRATION_DESTINATION_DB_DRIVER' => $payload['driver'] ?? 'mysql',
                'MIGRATION_DESTINATION_DB_HOST' => $payload['host'] ?? '',
                'MIGRATION_DESTINATION_DB_PORT' => (string) ($payload['port'] ?? ''),
                'MIGRATION_DESTINATION_DB_DATABASE' => $payload['database'] ?? '',
                'MIGRATION_DESTINATION_DB_USERNAME' => $payload['username'] ?? '',
                'MIGRATION_DESTINATION_DB_PASSWORD' => $payload['password'] ?? '',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Konfigurasi Destination Server berhasil disimpan ke .env.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Gagal menyimpan konfigurasi destination: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function loadDestinationConfig()
    {
        return response()->json([
            'success' => true,
            'source_label' => 'MIGRATION_DESTINATION_DB_*',
            'config' => [
                'driver' => $this->readEnvValue('MIGRATION_DESTINATION_DB_DRIVER', 'pgsql'),
                'host' => $this->readEnvValue('MIGRATION_DESTINATION_DB_HOST', ''),
                'port' => $this->readEnvValue('MIGRATION_DESTINATION_DB_PORT', ''),
                'database' => $this->readEnvValue('MIGRATION_DESTINATION_DB_DATABASE', ''),
                'username' => $this->readEnvValue('MIGRATION_DESTINATION_DB_USERNAME', ''),
                'password' => $this->readEnvValue('MIGRATION_DESTINATION_DB_PASSWORD', ''),
            ],
        ]);
    }

    public function startMigration(Request $request)
    {
        $payload = $request->validate([
            'mode' => 'required|in:full,structure',
            'confirmation' => 'required|string',
            'source.driver' => 'required|in:pgsql',
            'source.host' => 'required|string|max:255',
            'source.port' => 'nullable|integer|min:1|max:65535',
            'source.database' => 'required|string|max:255',
            'source.username' => 'required|string|max:255',
            'source.password' => 'nullable|string',
            'destination.driver' => 'required|in:pgsql',
            'destination.host' => 'required|string|max:255',
            'destination.port' => 'nullable|integer|min:1|max:65535',
            'destination.database' => 'required|string|max:255',
            'destination.username' => 'required|string|max:255',
            'destination.password' => 'nullable|string',
        ]);

        if (($payload['confirmation'] ?? '') !== 'MIGRATION') {
            return response()->json([
                'success' => false,
                'error' => 'Konfirmasi tidak valid. Ketik MIGRATION untuk melanjutkan.',
            ], 422);
        }

        $token = 'mrg_' . Str::uuid()->toString();
        $user = Auth::user();
        $userName = $user?->systemLabel() ?? 'System';
        $mode = $payload['mode'];

        session()->save();

        try {
            $migrationService = app(\App\Services\DatabaseMigrationService::class);
            $migrationService->queueMigration($token, $userName, $mode);

            return response()->json([
                'token' => $token,
                'started' => true,
                'mode' => $mode,
                'message' => 'Migration dimulai di server tujuan.',
            ]);
        } catch (\Throwable $e) {
            app(\App\Services\DatabaseMigrationService::class)->failQueueing($token, $userName, $mode, $e);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function runMigrationNow(Request $request)
    {
        $payload = $request->validate([
            'token' => 'required|string|max:120',
            'mode' => 'required|in:full,structure',
            'confirmation' => 'required|string',
            'source.driver' => 'required|in:pgsql',
            'source.host' => 'required|string|max:255',
            'source.port' => 'nullable|integer|min:1|max:65535',
            'source.database' => 'required|string|max:255',
            'source.username' => 'required|string|max:255',
            'source.password' => 'nullable|string',
            'destination.driver' => 'required|in:pgsql',
            'destination.host' => 'required|string|max:255',
            'destination.port' => 'nullable|integer|min:1|max:65535',
            'destination.database' => 'required|string|max:255',
            'destination.username' => 'required|string|max:255',
            'destination.password' => 'nullable|string',
        ]);

        if (($payload['confirmation'] ?? '') !== 'MIGRATION') {
            return response()->json([
                'success' => false,
                'error' => 'Konfirmasi tidak valid. Ketik MIGRATION untuk melanjutkan.',
            ], 422);
        }

        $token = (string) $payload['token'];
        $user = Auth::user();
        $userName = $user?->systemLabel() ?? 'System';
        $mode = (string) $payload['mode'];

        // Lepas lock session agar request polling progress tetap bisa jalan paralel.
        session()->save();

        @set_time_limit(0);

        try {
            app(\App\Services\DatabaseMigrationService::class)->runMigration(
                $token,
                $userName,
                $payload['source'],
                $payload['destination'],
                $mode
            );

            return response()->json([
                'success' => true,
                'token' => $token,
            ]);
        } catch (\Throwable $e) {
            $message = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8');

            return response()->json([
                'success' => false,
                'error' => $message !== '' ? $message : 'Migration gagal.',
            ], 500);
        }
    }

    public function previewSwitchServer()
    {
        $destination = [
            'driver' => $this->readEnvValue('MIGRATION_DESTINATION_DB_DRIVER', 'pgsql'),
            'host' => $this->readEnvValue('MIGRATION_DESTINATION_DB_HOST', ''),
            'port' => $this->readEnvValue('MIGRATION_DESTINATION_DB_PORT', '5432'),
            'database' => $this->readEnvValue('MIGRATION_DESTINATION_DB_DATABASE', 'postgres'),
            'username' => $this->readEnvValue('MIGRATION_DESTINATION_DB_USERNAME', ''),
            'password' => $this->readEnvValue('MIGRATION_DESTINATION_DB_PASSWORD', ''),
        ];

        if ($destination['host'] === '' || $destination['username'] === '' || $destination['database'] === '') {
            return response()->json([
                'success' => false,
                'error' => 'Konfigurasi destination belum lengkap di .env (MIGRATION_DESTINATION_DB_*).',
            ], 422);
        }

        $destinationDbUrl = $this->buildPgsqlUrl(
            $destination['username'],
            $destination['password'],
            $destination['host'],
            (int) $destination['port'],
            $destination['database']
        );

        return response()->json([
            'success' => true,
            'preview' => [
                'current' => [
                    'db_connection' => $this->readEnvValue('DB_CONNECTION', 'pgsql'),
                    'db_url' => $this->readEnvValue('DB_URL', ''),
                    'supabase_url' => $this->readEnvValue('SUPABASE_URL', ''),
                    'supabase_bucket' => $this->readEnvValue('SUPABASE_BUCKET', ''),
                ],
                'destination' => [
                    'db_url' => $destinationDbUrl,
                    'host' => $destination['host'],
                    'database' => $destination['database'],
                    'port' => (int) $destination['port'],
                    'username' => $destination['username'],
                ],
            ],
            'notes' => [
                'Nilai aktif akan dibackup ke SWITCH_BACKUP_* sebelum switch.',
                'MIGRATION_SOURCE_DB_* dan MIGRATION_DESTINATION_DB_* tidak dihapus.',
                'Setelah switch, jalankan php artisan config:clear agar perubahan .env terbaca.',
            ],
        ]);
    }

    public function executeSwitchServer(Request $request)
    {
        $payload = $request->validate([
            'confirmation' => 'required|string',
        ]);

        if (($payload['confirmation'] ?? '') !== 'SWITCH') {
            return response()->json([
                'success' => false,
                'error' => 'Konfirmasi tidak valid. Ketik SWITCH untuk melanjutkan.',
            ], 422);
        }

        $destinationDriver = $this->readEnvValue('MIGRATION_DESTINATION_DB_DRIVER', 'pgsql');
        $destinationHost = $this->readEnvValue('MIGRATION_DESTINATION_DB_HOST', '');
        $destinationPort = $this->readEnvValue('MIGRATION_DESTINATION_DB_PORT', '5432');
        $destinationDatabase = $this->readEnvValue('MIGRATION_DESTINATION_DB_DATABASE', 'postgres');
        $destinationUsername = $this->readEnvValue('MIGRATION_DESTINATION_DB_USERNAME', '');
        $destinationPassword = $this->readEnvValue('MIGRATION_DESTINATION_DB_PASSWORD', '');

        if ($destinationHost === '' || $destinationUsername === '' || $destinationDatabase === '') {
            return response()->json([
                'success' => false,
                'error' => 'Konfigurasi destination belum lengkap di .env (MIGRATION_DESTINATION_DB_*).',
            ], 422);
        }

        if ($destinationDriver !== 'pgsql') {
            return response()->json([
                'success' => false,
                'error' => 'Switch server saat ini hanya mendukung destination PostgreSQL.',
            ], 422);
        }

        $destinationDbUrl = $this->buildPgsqlUrl(
            $destinationUsername,
            $destinationPassword,
            $destinationHost,
            (int) $destinationPort,
            $destinationDatabase
        );

        $switchedAt = now()->format('Y-m-d H:i:s');

        try {
            $this->updateEnvValues([
                'SWITCH_BACKUP_DB_CONNECTION' => $this->readEnvValue('DB_CONNECTION', 'pgsql'),
                'SWITCH_BACKUP_DB_URL' => $this->readEnvValue('DB_URL', ''),
                'SWITCH_BACKUP_DB_PASSWORD' => $this->readEnvValue('DB_PASSWORD', ''),
                'SWITCH_BACKUP_SUPABASE_URL' => $this->readEnvValue('SUPABASE_URL', ''),
                'SWITCH_BACKUP_SUPABASE_ENDPOINT' => $this->readEnvValue('SUPABASE_ENDPOINT', ''),
                'SWITCH_BACKUP_SUPABASE_KEY' => $this->readEnvValue('SUPABASE_KEY', ''),
                'SWITCH_BACKUP_SUPABASE_SECRET' => $this->readEnvValue('SUPABASE_SECRET', ''),
                'SWITCH_BACKUP_SUPABASE_BUCKET' => $this->readEnvValue('SUPABASE_BUCKET', ''),
                'SWITCH_BACKUP_AT' => $switchedAt,
                'DB_CONNECTION' => 'pgsql',
                'DB_URL' => $destinationDbUrl,
                'DB_PASSWORD' => $destinationPassword,
                'ACTIVE_DB_TARGET' => 'destination',
                'ACTIVE_DB_SWITCHED_AT' => $switchedAt,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Switch server berhasil disiapkan. Nilai aktif sudah dipindah ke destination dan backup disimpan di SWITCH_BACKUP_*.',
                'next_steps' => [
                    'Jalankan php artisan config:clear',
                    'Validasi fitur utama aplikasi setelah switch',
                    'Jika perlu update Supabase key/secret ke project destination, lakukan sebelum go-live penuh',
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Gagal melakukan switch server: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function previewRollbackSwitchServer()
    {
        $backupDbUrl = $this->readEnvValue('SWITCH_BACKUP_DB_URL', '');
        if ($backupDbUrl === '') {
            return response()->json([
                'success' => false,
                'error' => 'Backup switch tidak ditemukan. SWITCH_BACKUP_DB_URL masih kosong.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'preview' => [
                'current' => [
                    'db_connection' => $this->readEnvValue('DB_CONNECTION', 'pgsql'),
                    'db_url' => $this->readEnvValue('DB_URL', ''),
                    'supabase_url' => $this->readEnvValue('SUPABASE_URL', ''),
                    'supabase_bucket' => $this->readEnvValue('SUPABASE_BUCKET', ''),
                ],
                'backup' => [
                    'db_connection' => $this->readEnvValue('SWITCH_BACKUP_DB_CONNECTION', 'pgsql'),
                    'db_url' => $backupDbUrl,
                    'supabase_url' => $this->readEnvValue('SWITCH_BACKUP_SUPABASE_URL', ''),
                    'supabase_bucket' => $this->readEnvValue('SWITCH_BACKUP_SUPABASE_BUCKET', ''),
                    'saved_at' => $this->readEnvValue('SWITCH_BACKUP_AT', ''),
                ],
            ],
            'notes' => [
                'Rollback akan mengembalikan nilai aktif DB_* dan SUPABASE_* dari SWITCH_BACKUP_*.',
                'MIGRATION_SOURCE_DB_* dan MIGRATION_DESTINATION_DB_* tidak diubah.',
                'Setelah rollback, jalankan php artisan config:clear agar perubahan .env terbaca.',
            ],
        ]);
    }

    public function executeRollbackSwitchServer(Request $request)
    {
        $payload = $request->validate([
            'confirmation' => 'required|string',
        ]);

        if (($payload['confirmation'] ?? '') !== 'ROLLBACK') {
            return response()->json([
                'success' => false,
                'error' => 'Konfirmasi tidak valid. Ketik ROLLBACK untuk melanjutkan.',
            ], 422);
        }

        $backupDbUrl = $this->readEnvValue('SWITCH_BACKUP_DB_URL', '');
        if ($backupDbUrl === '') {
            return response()->json([
                'success' => false,
                'error' => 'Rollback tidak bisa dijalankan karena backup switch belum tersedia.',
            ], 422);
        }

        $rolledBackAt = now()->format('Y-m-d H:i:s');

        try {
            $this->updateEnvValues([
                'DB_CONNECTION' => $this->readEnvValue('SWITCH_BACKUP_DB_CONNECTION', 'pgsql'),
                'DB_URL' => $backupDbUrl,
                'DB_PASSWORD' => $this->readEnvValue('SWITCH_BACKUP_DB_PASSWORD', ''),
                'SUPABASE_URL' => $this->readEnvValue('SWITCH_BACKUP_SUPABASE_URL', ''),
                'SUPABASE_ENDPOINT' => $this->readEnvValue('SWITCH_BACKUP_SUPABASE_ENDPOINT', ''),
                'SUPABASE_KEY' => $this->readEnvValue('SWITCH_BACKUP_SUPABASE_KEY', ''),
                'SUPABASE_SECRET' => $this->readEnvValue('SWITCH_BACKUP_SUPABASE_SECRET', ''),
                'SUPABASE_BUCKET' => $this->readEnvValue('SWITCH_BACKUP_SUPABASE_BUCKET', ''),
                'ACTIVE_DB_TARGET' => 'source-rollback',
                'ACTIVE_DB_ROLLEDBACK_AT' => $rolledBackAt,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rollback switch berhasil. Nilai aktif sudah dikembalikan dari SWITCH_BACKUP_*.',
                'next_steps' => [
                    'Jalankan php artisan config:clear',
                    'Validasi fitur utama aplikasi setelah rollback',
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Gagal melakukan rollback switch: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function validateServerConfigPayload(Request $request): array
    {
        return $request->validate([
            'driver' => 'nullable|in:mysql,pgsql',
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'database' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string',
        ]);
    }

    /**
     * Bangun konfigurasi koneksi PDO sementara untuk Test/Clear, sesuai driver.
     * PostgreSQL (local maupun online) memakai client_encoding 'utf8'; MySQL memakai 'utf8mb4'.
     * Memakai 'utf8mb4' pada PostgreSQL menyebabkan FATAL SQLSTATE[08006] invalid value
     * for parameter "client_encoding" pada koneksi langsung (mis. Postgres local).
     */
    private function buildTestConnectionConfig(string $driver, array $payload): array
    {
        $base = [
            'driver' => $driver,
            'host' => $payload['host'],
            'port' => $payload['port'] ?? ($driver === 'pgsql' ? 5432 : 3306),
            'database' => $payload['database'],
            'username' => $payload['username'],
            'password' => $payload['password'] ?? '',
            'prefix' => '',
            'prefix_indexes' => true,
        ];

        if ($driver === 'pgsql') {
            return array_merge($base, [
                'charset' => 'utf8',
                'search_path' => 'public',
                'sslmode' => 'prefer',
                // PgBouncer transaction pooler (Supabase port 6543) tidak mendukung
                // prepared statement server-side, jadi emulasikan di sisi client.
                'options' => extension_loaded('pdo_pgsql') ? array_filter([
                    \PDO::ATTR_EMULATE_PREPARES => true,
                ]) : [],
            ]);
        }

        return array_merge($base, [
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => false,
            'engine' => null,
        ]);
    }

    private function buildPgsqlUrl(string $username, string $password, string $host, int $port, string $database): string
    {
        return 'postgresql://'
            . rawurlencode($username)
            . ':'
            . rawurlencode($password)
            . '@'
            . $host
            . ':'
            . max($port, 1)
            . '/'
            . $database;
    }

    private function updateEnvValues(array $entries): void
    {
        $envPath = base_path('.env');
        if (!is_file($envPath)) {
            throw new \RuntimeException('.env file tidak ditemukan.');
        }

        $content = file_get_contents($envPath);
        if ($content === false) {
            throw new \RuntimeException('Tidak bisa membaca file .env.');
        }

        foreach ($entries as $key => $value) {
            $escapedKey = preg_quote($key, '/');
            $line = $key . '=' . $this->formatEnvValue((string) $value);

            if (preg_match('/^' . $escapedKey . '=.*$/m', $content)) {
                $content = preg_replace('/^' . $escapedKey . '=.*$/m', $line, $content);
            } else {
                $content = rtrim($content, "\r\n") . PHP_EOL . $line . PHP_EOL;
            }
        }

        if (file_put_contents($envPath, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Tidak bisa menulis ke file .env.');
        }
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (preg_match('/[\s#"\'=]/', $value)) {
            return '"' . addcslashes($value, '"') . '"';
        }

        return $value;
    }

    private function readEnvValue(string $key, string $default = ''): string
    {
        $envPath = base_path('.env');
        if (!is_file($envPath)) {
            return $default;
        }

        $content = file_get_contents($envPath);
        if ($content === false) {
            return $default;
        }

        $escapedKey = preg_quote($key, '/');
        if (!preg_match('/^' . $escapedKey . '=(.*)$/m', $content, $matches)) {
            return $default;
        }

        $raw = trim($matches[1]);
        if ($raw === '""') {
            return '';
        }

        if (str_starts_with($raw, '"') && str_ends_with($raw, '"')) {
            return stripcslashes(substr($raw, 1, -1));
        }

        if (str_starts_with($raw, "'") && str_ends_with($raw, "'")) {
            return substr($raw, 1, -1);
        }

        return $raw;
    }

    private function fetchTableList($conn, string $driver, ?string $database = null): array
    {
        if ($driver === 'pgsql') {
            $rows = $conn->select("
                SELECT t.table_name, COALESCE(s.n_live_tup, 0) AS row_count
                FROM information_schema.tables t
                LEFT JOIN pg_stat_user_tables s ON s.relname = t.table_name
                WHERE t.table_schema = 'public' AND t.table_type = 'BASE TABLE'
                ORDER BY t.table_name
            ");
        } else {
            $rows = $conn->select("
                SELECT table_name, COALESCE(table_rows, 0) AS row_count
                FROM information_schema.tables
                WHERE table_schema = ? AND table_type = 'BASE TABLE'
                ORDER BY table_name
            ", [$database]);
        }

        return collect($rows)->map(fn ($row) => [
            'name' => $row->table_name,
            'row_count' => (int) $row->row_count,
        ])->values()->all();
    }

    private function tryEnableReplicationRole(): void
    {
        try {
            DB::statement('SET session_replication_role = replica;');
        } catch (\Throwable $e) {
            if (!str_contains($e->getMessage(), 'session_replication_role') && !str_contains($e->getMessage(), '42501')) {
                throw $e;
            }
        }
    }

    private function tryDisableReplicationRole(): void
    {
        try {
            DB::statement('SET session_replication_role = DEFAULT;');
        } catch (\Throwable $e) {
            // Abaikan jika enable sebelumnya gagal.
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1) . ' MB';
        }

        return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }

    private function resolveActiveConnectionInfo(): array
    {
        $connectionName = (string) config('database.default', 'pgsql');
        $dbConfig = config("database.connections.{$connectionName}", []);

        $driver = strtolower((string) ($dbConfig['driver'] ?? 'pgsql'));
        $driverLabel = match ($driver) {
            'pgsql' => 'PostgreSQL',
            'mysql' => 'MySQL',
            'sqlite' => 'SQLite',
            'sqlsrv' => 'SQL Server',
            default => strtoupper($driver),
        };

        $host = (string) ($dbConfig['host'] ?? '');
        $port = (string) ($dbConfig['port'] ?? '');
        $database = (string) ($dbConfig['database'] ?? '');
        $username = (string) ($dbConfig['username'] ?? '');
        $dbUrl = (string) ($dbConfig['url'] ?? config('database.connections.pgsql.url', ''));

        if ($dbUrl !== '') {
            $parsed = parse_url($dbUrl);
            if (!empty($parsed['host'])) {
                $host = $parsed['host'];
            }
            if (!empty($parsed['port'])) {
                $port = (string) $parsed['port'];
            }
            if (!empty($parsed['path'])) {
                $database = ltrim($parsed['path'], '/');
            }
            if (!empty($parsed['user'])) {
                $username = $parsed['user'];
            }
        }

        $hostDisplay = $host !== '' ? $host . ($port !== '' ? ':' . $port : '') : '-';

        $region = (string) env('SUPABASE_REGION', '');
        if ($region === '' && preg_match('/aws-\d+-([^.]+)\.pooler\.supabase\.com/', $host, $m)) {
            $region = $m[1];
        }
        if ($region === '') {
            $region = '-';
        }

        $supabaseUrl = (string) env('SUPABASE_URL', '');
        if ($supabaseUrl === '') {
            $supabaseUrl = '-';
        }

        return [
            'driver'   => $driverLabel,
            'host'     => $hostDisplay,
            'region'   => $region,
            'url'      => $supabaseUrl,
            'database' => $database !== '' ? $database : '-',
            'username' => $username !== '' ? $username : '-',
        ];
    }
}
