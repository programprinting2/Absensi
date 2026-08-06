<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseToolsService
{
    /**
     * @return array{driver: string, host: string, port: string|int|null, database: string, username: string, url: string}
     */
    public function connectionInfo(): array
    {
        $cfg = config('database.connections.'.config('database.default'), []);

        return [
            'driver' => strtoupper((string) ($cfg['driver'] ?? 'pgsql')),
            'host' => (string) ($cfg['host'] ?? '-'),
            'port' => $cfg['port'] ?? '-',
            'database' => (string) ($cfg['database'] ?? '-'),
            'username' => (string) ($cfg['username'] ?? '-'),
            'url' => (string) (env('SUPABASE_URL') ?: env('APP_URL') ?: '-'),
        ];
    }

    /**
     * @return array{tables: list<array{name: string, row_count: int, row_count_fmt: string, size: string, raw_size: int}>, total_size: string, table_count: int}
     */
    public function tableStats(): array
    {
        $driver = config('database.default');
        $connectionDriver = config("database.connections.{$driver}.driver");

        if ($connectionDriver === 'pgsql') {
            return $this->postgresTableStats();
        }

        return $this->genericTableStats();
    }

    /**
     * @return list<object{migration: string, batch: int}>
     */
    public function migrations(): array
    {
        if (! Schema::hasTable('migrations')) {
            return [];
        }

        return DB::table('migrations')
            ->orderByDesc('batch')
            ->orderByDesc('id')
            ->get(['migration', 'batch'])
            ->all();
    }

    /**
     * @return array{ran: list<string>, pending: list<string>}
     */
    public function migrationStatus(): array
    {
        Artisan::call('migrate:status');
        $output = Artisan::output();

        $ran = [];
        $pending = [];

        foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
            if (preg_match('/\bPending\b/i', $line) && preg_match('/(\d{4}_\d{2}_\d{2}_\d{6}_\S+)/', $line, $m)) {
                $pending[] = $m[1];
            } elseif (preg_match('/\bRan\b/i', $line) && preg_match('/(\d{4}_\d{2}_\d{2}_\d{6}_\S+)/', $line, $m)) {
                $ran[] = $m[1];
            }
        }

        return compact('ran', 'pending');
    }

    public function runMigrations(): string
    {
        Artisan::call('migrate', ['--force' => true]);

        return trim(Artisan::output()) ?: 'Migrasi selesai.';
    }

    public function downloadBackup(string $scope = 'full'): StreamedResponse
    {
        $scope = in_array($scope, ['full', 'structure'], true) ? $scope : 'full';
        $filename = 'backup_'.now()->format('Ymd_His').'_'.$scope.'.sql';
        $tables = $this->publicTables();

        return response()->streamDownload(function () use ($tables, $scope) {
            echo "-- Absensi Online DB Backup\n";
            echo '-- Generated: '.now()->toDateTimeString()."\n";
            echo '-- Scope: '.$scope."\n\n";
            echo "SET session_replication_role = replica;\n\n";

            foreach ($tables as $table) {
                $quoted = $this->quoteIdent($table);
                echo "-- ----------------------------\n";
                echo "-- Table: {$table}\n";
                echo "-- ----------------------------\n";

                if ($scope === 'structure') {
                    echo "-- Structure-only: gunakan migrasi Laravel untuk recreate schema.\n";
                    echo "-- DROP TABLE IF EXISTS {$quoted} CASCADE;\n\n";

                    continue;
                }

                echo "DELETE FROM {$quoted};\n";

                $columns = Schema::getColumnListing($table);
                if ($columns === []) {
                    echo "\n";

                    continue;
                }

                $colList = implode(', ', array_map(fn ($c) => $this->quoteIdent($c), $columns));
                $rows = DB::table($table)->orderBy($columns[0])->cursor();

                foreach ($rows as $row) {
                    $values = [];
                    foreach ($columns as $col) {
                        $values[] = $this->sqlLiteral(data_get($row, $col));
                    }
                    echo "INSERT INTO {$quoted} ({$colList}) VALUES (".implode(', ', $values).");\n";
                }

                echo "\n";
            }

            echo "SET session_replication_role = DEFAULT;\n";
        }, $filename, [
            'Content-Type' => 'application/sql; charset=UTF-8',
        ]);
    }

    /**
     * @return array{success: bool, message: string, statements: int}
     */
    public function restoreFromSql(string $sql): array
    {
        $sql = trim($sql);
        if ($sql === '') {
            return ['success' => false, 'message' => 'File SQL kosong.', 'statements' => 0];
        }

        // Pecah kasar per statement; cukup untuk dump INSERT/DELETE sederhana.
        $parts = preg_split('/;\s*\n/', $sql) ?: [];
        $statements = 0;

        DB::beginTransaction();

        try {
            DB::statement('SET session_replication_role = replica');

            foreach ($parts as $part) {
                $statement = trim($part);
                if ($statement === '' || str_starts_with($statement, '--')) {
                    continue;
                }

                // Abaikan komentar-only blocks.
                $withoutComments = trim(preg_replace('/^--.*$/m', '', $statement) ?? '');
                if ($withoutComments === '') {
                    continue;
                }

                DB::unprepared($withoutComments);
                $statements++;
            }

            DB::statement('SET session_replication_role = DEFAULT');
            DB::commit();

            return [
                'success' => true,
                'message' => "Restore selesai ({$statements} statement).",
                'statements' => $statements,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            try {
                DB::statement('SET session_replication_role = DEFAULT');
            } catch (\Throwable) {
                // ignore
            }

            return [
                'success' => false,
                'message' => 'Restore gagal: '.$e->getMessage(),
                'statements' => $statements,
            ];
        }
    }

    /**
     * @param  list<string>  $tables
     * @return array{success: bool, message: string, cleared: list<string>}
     */
    public function clearTables(array $tables): array
    {
        $existing = $this->publicTables();
        $selected = array_values(array_intersect($tables, $existing));

        if ($selected === []) {
            return ['success' => false, 'message' => 'Tidak ada tabel valid.', 'cleared' => []];
        }

        $cleared = [];

        DB::beginTransaction();

        try {
            DB::statement('SET session_replication_role = replica');

            foreach ($selected as $table) {
                DB::statement('DELETE FROM '.$this->quoteIdent($table));
                $cleared[] = $table;
            }

            DB::statement('SET session_replication_role = DEFAULT');
            DB::commit();

            return [
                'success' => true,
                'message' => count($cleared).' tabel dikosongkan.',
                'cleared' => $cleared,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            try {
                DB::statement('SET session_replication_role = DEFAULT');
            } catch (\Throwable) {
                // ignore
            }

            return [
                'success' => false,
                'message' => 'Gagal clear tabel: '.$e->getMessage(),
                'cleared' => $cleared,
            ];
        }
    }

    /**
     * @return list<string>
     */
    public function publicTables(): array
    {
        $driver = config('database.connections.'.config('database.default').'.driver');

        if ($driver === 'pgsql') {
            return collect(DB::select("
                SELECT table_name
                FROM information_schema.tables
                WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
                ORDER BY table_name
            "))->pluck('table_name')->map(fn ($n) => (string) $n)->all();
        }

        return Schema::getTableListing();
    }

    /**
     * @return array{tables: list<array{name: string, row_count: int, row_count_fmt: string, size: string, raw_size: int}>, total_size: string, table_count: int}
     */
    private function postgresTableStats(): array
    {
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

        $sizes = DB::select("
            SELECT
                table_name,
                pg_size_pretty(pg_total_relation_size(quote_ident(table_name))) AS size,
                pg_total_relation_size(quote_ident(table_name)) AS raw_size
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_type = 'BASE TABLE'
        ");

        $sizeMap = collect($sizes)->keyBy('table_name');

        $tableData = collect($tables)->map(function ($t) use ($sizeMap) {
            $raw = (int) ($sizeMap[$t->table_name]->raw_size ?? 0);

            return [
                'name' => (string) $t->table_name,
                'row_count' => (int) $t->row_count,
                'row_count_fmt' => number_format((int) $t->row_count),
                'size' => (string) ($sizeMap[$t->table_name]->size ?? '-'),
                'raw_size' => $raw,
            ];
        })->sortByDesc('raw_size')->values()->all();

        $total = DB::selectOne("
            SELECT pg_size_pretty(COALESCE(SUM(pg_total_relation_size(quote_ident(table_name))), 0)) AS total
            FROM information_schema.tables
            WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
        ");

        return [
            'tables' => $tableData,
            'total_size' => (string) ($total->total ?? '-'),
            'table_count' => count($tableData),
        ];
    }

    /**
     * @return array{tables: list<array{name: string, row_count: int, row_count_fmt: string, size: string, raw_size: int}>, total_size: string, table_count: int}
     */
    private function genericTableStats(): array
    {
        $names = Schema::getTableListing();
        $tables = [];

        foreach ($names as $name) {
            $count = (int) DB::table($name)->count();
            $tables[] = [
                'name' => $name,
                'row_count' => $count,
                'row_count_fmt' => number_format($count),
                'size' => '-',
                'raw_size' => $count,
            ];
        }

        usort($tables, fn ($a, $b) => $b['raw_size'] <=> $a['raw_size']);

        return [
            'tables' => $tables,
            'total_size' => '-',
            'table_count' => count($tables),
        ];
    }

    private function quoteIdent(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }

    private function sqlLiteral(mixed $value): string
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

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return "'".str_replace("'", "''", (string) $value)."'";
    }
}
