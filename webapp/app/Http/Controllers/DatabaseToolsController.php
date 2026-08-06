<?php

namespace App\Http\Controllers;

use App\Services\DatabaseToolsService;
use Illuminate\Http\Request;

class DatabaseToolsController extends Controller
{
    public function __construct(private DatabaseToolsService $tools) {}

    public function index()
    {
        $connection = $this->tools->connectionInfo();
        $stats = $this->tools->tableStats();
        $migrations = $this->tools->migrations();
        $migrationStatus = $this->tools->migrationStatus();

        return view('tools.database', [
            'connection' => $connection,
            'tables' => $stats['tables'],
            'tableCount' => $stats['table_count'],
            'totalSize' => $stats['total_size'],
            'migrations' => $migrations,
            'migrationStatus' => $migrationStatus,
        ]);
    }

    public function backup(Request $request)
    {
        $data = $request->validate([
            'scope' => ['nullable', 'in:full,structure'],
        ]);

        return $this->tools->downloadBackup($data['scope'] ?? 'full');
    }

    public function restore(Request $request)
    {
        $request->validate([
            'backup_file' => ['required', 'file', 'max:51200'],
        ]);

        $file = $request->file('backup_file');
        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, ['sql', 'txt'], true)) {
            return back()
                ->with('error', 'Upload file .sql hasil backup.')
                ->with('tools_tab', 'backup');
        }

        $sql = file_get_contents($file->getRealPath()) ?: '';
        $result = $this->tools->restoreFromSql($sql);

        return back()
            ->with($result['success'] ? 'status' : 'error', $result['message'])
            ->with('tools_tab', 'backup');
    }

    public function clearTables(Request $request)
    {
        $data = $request->validate([
            'tables' => ['required', 'array', 'min:1'],
            'tables.*' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->tools->clearTables($data['tables']);

        return back()
            ->with($result['success'] ? 'status' : 'error', $result['message'])
            ->with('tools_tab', 'backup');
    }

    public function migrate(Request $request)
    {
        $request->validate([
            'confirm' => ['accepted'],
        ]);

        try {
            $output = $this->tools->runMigrations();

            return back()
                ->with('status', 'Migrasi dijalankan.')
                ->with('migrate_output', $output)
                ->with('tools_tab', 'migrate');
        } catch (\Throwable $e) {
            return back()
                ->with('error', 'Migrasi gagal: '.$e->getMessage())
                ->with('tools_tab', 'migrate');
        }
    }
}
