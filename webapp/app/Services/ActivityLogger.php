<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ActivityLogger
{
    public static function normal(
        string $description,
        ?string $action = null,
        array $context = [],
        ?Request $request = null,
        ?User $user = null,
    ): void {
        self::log(ActivityLog::LEVEL_NORMAL, $description, $action, $context, $request, $user);
    }

    public static function medium(
        string $description,
        ?string $action = null,
        array $context = [],
        ?Request $request = null,
        ?User $user = null,
    ): void {
        self::log(ActivityLog::LEVEL_MEDIUM, $description, $action, $context, $request, $user);
    }

    public static function warning(
        string $description,
        ?string $action = null,
        array $context = [],
        ?Request $request = null,
        ?User $user = null,
    ): void {
        self::log(ActivityLog::LEVEL_WARNING, $description, $action, $context, $request, $user);
    }

    public static function log(
        string $level,
        string $description,
        ?string $action = null,
        array $context = [],
        ?Request $request = null,
        ?User $user = null,
    ): void {
        try {
            $request ??= request();
            $user ??= Auth::user();

            ActivityLog::query()->create([
                'level' => in_array($level, [
                    ActivityLog::LEVEL_NORMAL,
                    ActivityLog::LEVEL_MEDIUM,
                    ActivityLog::LEVEL_WARNING,
                ], true) ? $level : ActivityLog::LEVEL_NORMAL,
                'action' => $action ? mb_substr($action, 0, 120) : null,
                'description' => mb_substr(trim($description), 0, 2000),
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'ip_address' => $request?->ip(),
                'method' => $request?->method(),
                'url' => $request ? mb_substr($request->fullUrl(), 0, 500) : null,
                'context' => $context !== [] ? $context : null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Jangan ganggu alur utama jika logging gagal.
        }
    }

    public static function clear(?string $level = null): int
    {
        $query = ActivityLog::query();

        if ($level && in_array($level, [
            ActivityLog::LEVEL_NORMAL,
            ActivityLog::LEVEL_MEDIUM,
            ActivityLog::LEVEL_WARNING,
        ], true)) {
            $query->where('level', $level);
        }

        return $query->delete();
    }

    public static function inferLevelFromMethod(string $method): string
    {
        return match (strtoupper($method)) {
            'DELETE' => ActivityLog::LEVEL_WARNING,
            'PUT', 'PATCH' => ActivityLog::LEVEL_MEDIUM,
            default => ActivityLog::LEVEL_NORMAL,
        };
    }

    public static function describeRequest(Request $request): string
    {
        $route = $request->route()?->getName();

        if ($route) {
            return self::humanizeRoute($route, $request->method());
        }

        $path = '/'.ltrim($request->path(), '/');

        return match (strtoupper($request->method())) {
            'POST' => "Mengirim data ke {$path}",
            'PUT', 'PATCH' => "Memperbarui data di {$path}",
            'DELETE' => "Menghapus data di {$path}",
            default => "Mengakses {$path}",
        };
    }

    /**
     * @param  list<array{component: string, method: string}>  $calls
     */
    public static function describeLivewireCalls(array $calls): string
    {
        $parts = [];
        foreach ($calls as $call) {
            $parts[] = self::humanizeLivewireCall(
                (string) ($call['component'] ?? ''),
                (string) ($call['method'] ?? ''),
            );
        }

        $parts = array_values(array_unique(array_filter($parts)));

        return $parts !== [] ? implode('; ', $parts) : 'Melakukan aksi di aplikasi';
    }

    public static function shouldSkipLivewireMethod(string $method): bool
    {
        $method = trim($method);
        if ($method === '' || str_starts_with($method, '$')) {
            return true;
        }

        if (in_array($method, [
            'login', 'logout', 'clearLogs', 'mount', 'with',
            'updatingSearch', 'updatingLevel',
        ], true)) {
            return true;
        }

        // Aksi UI saja (buka/tutup form, mulai edit, navigasi) — tidak perlu dicatat.
        if (preg_match('/^(open|close|start|toggle|set|goTo|previous|next|shift|stop|resetFilters)/i', $method)) {
            return true;
        }

        return (bool) preg_match('/^cancel(Edit|Form|Modal|EditCell|EditAmount)/i', $method);
    }

    public static function humanizeLivewireCall(string $component, string $method): string
    {
        $area = self::humanizeComponent($component);

        $action = match ($method) {
            'saveCellTime' => 'Menyimpan jam absensi',
            'deleteRow' => 'Menghapus baris absensi',
            'resetToday' => 'Mengosongkan absensi hari ini',
            'createDummy' => 'Membuat data absensi contoh',
            'clearDummy' => 'Menghapus data absensi contoh',
            'saveReasons' => 'Menyimpan alasan absensi',
            'approve' => 'Menyetujui cuti',
            'reject' => 'Menolak cuti',
            'cancel' => 'Membatalkan cuti',
            'save' => 'Menyimpan data',
            'deleteEmployee' => 'Menghapus karyawan',
            'resetPassword' => 'Mereset kata sandi',
            'enrollFingerprint' => 'Mendaftarkan sidik jari',
            'deleteFingerprint' => 'Menghapus sidik jari',
            'cancelBon' => 'Membatalkan cash bon',
            'createPeriod' => 'Membuat periode penggajian',
            'generate' => 'Menghitung penggajian',
            'finalize' => 'Mengunci / finalisasi penggajian',
            'unfinalize' => 'Membuka kunci penggajian',
            'deletePeriod' => 'Menghapus periode penggajian',
            'saveDetailAmount' => 'Mengubah nominal komponen gaji',
            'saveAdjustment' => 'Menyimpan penyesuaian gaji',
            'recalculate' => 'Menghitung ulang gaji',
            'addComponent' => 'Menambah komponen gaji',
            'deleteComponent' => 'Menghapus komponen gaji',
            'updatePassword' => 'Mengubah kata sandi',
            'updateProfileInformation', 'update' => 'Memperbarui profil',
            'deleteUser' => 'Menghapus akun',
            'register' => 'Mendaftar akun baru',
            'sendPasswordResetLink' => 'Meminta reset kata sandi',
            'confirmPassword' => 'Mengonfirmasi kata sandi',
            'sendVerification' => 'Mengirim verifikasi email',
            default => null,
        };

        if ($action !== null) {
            return $area ? "{$action} ({$area})" : $action;
        }

        $pretty = self::methodToSentence($method);

        return $area ? "{$pretty} di {$area}" : $pretty;
    }

    public static function humanizeComponent(string $component): string
    {
        $component = strtolower(trim($component));

        return match (true) {
            str_contains($component, 'employee.leaves') || str_contains($component, 'pages.leaves') => 'Cuti',
            str_contains($component, 'reports.attendance') => 'Laporan Absensi',
            str_contains($component, 'attendance') && str_contains($component, 'employee') => 'Dashboard Saya',
            str_contains($component, 'pages.attendance') => 'Absensi Harian',
            str_contains($component, 'employees') => 'Data Karyawan',
            str_contains($component, 'cash-bons') || str_contains($component, 'cash_bons') => 'Cash Bon',
            str_contains($component, 'payroll.entry') => 'Detail Gaji',
            str_contains($component, 'payroll.show') || str_contains($component, 'period-entries') => 'Periode Gaji',
            str_contains($component, 'payroll') => 'Penggajian',
            str_contains($component, 'employee.dashboard') => 'Dashboard Saya',
            str_contains($component, 'dashboard') => 'Dashboard',
            str_contains($component, 'activity-logs') => 'Log Aktivitas',
            str_contains($component, 'profile') => 'Profil',
            str_contains($component, 'auth') => 'Autentikasi',
            default => '',
        };
    }

    public static function humanizeRoute(string $route, string $method = 'POST'): string
    {
        $known = [
            'settings.company.update' => 'Memperbarui identitas usaha',
            'settings.pph21.update' => 'Memperbarui pengaturan PPh 21',
            'settings.slip.update' => 'Memperbarui pengaturan cetak slip',
            'settings.roles.store' => 'Menambah role akses',
            'settings.roles.update' => 'Memperbarui role akses',
            'settings.roles.destroy' => 'Menghapus role akses',
            'settings.users.store' => 'Menambah pengguna',
            'settings.users.update' => 'Memperbarui pengguna',
            'settings.users.destroy' => 'Menghapus pengguna',
            'settings.devices.update' => 'Memperbarui perangkat',
            'settings.devices.wifi.start' => 'Mengatur WiFi perangkat',
            'settings.parameters.store' => 'Menambah parameter',
            'settings.parameters.update' => 'Memperbarui parameter',
            'settings.parameters.destroy' => 'Menghapus parameter',
            'settings.parameters.details.store' => 'Menambah detail parameter',
            'settings.parameters.details.update' => 'Memperbarui detail parameter',
            'settings.parameters.details.destroy' => 'Menghapus detail parameter',
            'work-schedule.store' => 'Menambah jadwal jam kerja',
            'work-schedule.update' => 'Memperbarui jadwal jam kerja',
            'work-schedule.activate' => 'Mengaktifkan jadwal jam kerja',
            'work-schedule.destroy' => 'Menghapus jadwal jam kerja',
            'employees.store' => 'Menambah karyawan',
            'employees.update' => 'Memperbarui karyawan',
            'employees.destroy' => 'Menghapus karyawan',
            'employees.portal.update' => 'Memperbarui akses portal karyawan',
            'employees.salary.update' => 'Memperbarui gaji karyawan',
            'employees.enroll-fingerprint' => 'Mendaftarkan sidik jari',
            'employees.fingerprint-templates.destroy' => 'Menghapus sidik jari',
            'employees.cash-bons.store' => 'Membuat cash bon',
            'employees.cash-bons.destroy' => 'Menghapus cash bon',
            'payroll.settings.update' => 'Memperbarui pengaturan penggajian',
            'payroll.allowance-types.store' => 'Menambah jenis tunjangan',
            'payroll.allowance-types.update' => 'Memperbarui jenis tunjangan',
            'payroll.allowance-types.destroy' => 'Menghapus jenis tunjangan',
            'payroll.deduction-types.store' => 'Menambah jenis potongan',
            'payroll.deduction-types.update' => 'Memperbarui jenis potongan',
            'payroll.deduction-types.destroy' => 'Menghapus jenis potongan',
            'tools.database.backup.start' => 'Memulai backup database',
            'tools.database.backup.run' => 'Menjalankan backup database',
            'tools.database.restore.prepare' => 'Menyiapkan restore database',
            'tools.database.restore.run' => 'Menjalankan restore database',
            'tools.database.tables.clear' => 'Mengosongkan tabel database',
            'tools.database.migration.start' => 'Memulai migrasi database',
            'tools.database.migration.run' => 'Menjalankan migrasi database',
            'tools.google-drive.upload' => 'Mengunggah ke Google Drive',
            'tools.google-drive.delete' => 'Menghapus file di Google Drive',
        ];

        if (isset($known[$route])) {
            return $known[$route];
        }

        $label = str_replace(['.', '-', '_'], ' ', $route);
        $label = trim(preg_replace('/\s+/', ' ', $label) ?? $label);

        return match (strtoupper($method)) {
            'DELETE' => 'Menghapus: '.$label,
            'PUT', 'PATCH' => 'Memperbarui: '.$label,
            'POST' => 'Menyimpan: '.$label,
            default => 'Aksi: '.$label,
        };
    }

    private static function methodToSentence(string $method): string
    {
        $spaced = preg_replace('/([a-z])([A-Z])/', '$1 $2', $method) ?? $method;
        $spaced = strtolower(str_replace(['_', '-'], ' ', $spaced));

        return 'Melakukan '.trim($spaced);
    }
}
