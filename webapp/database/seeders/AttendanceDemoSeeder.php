<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Seeder simulasi/preview: menambahkan karyawan contoh dengan pola absensi
 * acak (tepat waktu, terlambat, istirahat lebih, pulang cepat, tidak masuk)
 * supaya halaman dashboard & laporan bisa dilihat dengan data yang bervariasi
 * dan realistis — bukan selalu orang yang sama yang terlambat/absen.
 *
 * Aman dijalankan berkali-kali — karyawan dummy dicari-atau-dibuat by nama
 * (tidak pernah duplikat), log yang sudah ada untuk tanggal+jenis yang sama
 * tidak ditimpa, dan tidak menyentuh karyawan/log yang bukan bagian dari
 * skenario ini. Untuk hari ini, event yang jamnya belum lewat waktu sekarang
 * sengaja tidak diisi (mis. belum jam 12 siang berarti belum ada istirahat).
 */
class AttendanceDemoSeeder extends Seeder
{
    /** Peluang (%) seorang karyawan tidak masuk pada satu hari kerja. */
    private const ABSENT_CHANCE = 12;

    /** Peluang (%) datang terlambat (setelah jam masuk jadwal). */
    private const LATE_CHANCE = 30;

    /** Peluang (%) istirahat lebih lama dari jatah. */
    private const OVER_BREAK_CHANCE = 15;

    /** Peluang (%) pulang lebih awal dari jadwal. */
    private const EARLY_OUT_CHANCE = 10;

    public function run(): void
    {
        $device = Device::where('is_active', true)->first()
            ?? Device::create([
                'device_code' => 'DEV-001',
                'name' => 'Pintu Utama',
                'location' => 'Kantor Pusat',
                'is_active' => true,
            ]);

        // Aplikasi mengonfigurasi timezone server sebagai UTC, tapi jam kerja
        // & jam yang ditampilkan ke user memakai WIB. Ambil wall-clock WIB
        // lalu perlakukan sebagai "now" default (UTC) supaya sebanding
        // dengan event_time yang juga disimpan sebagai wall-clock WIB.
        $jakartaNow = Carbon::now('Asia/Jakarta');
        $now = Carbon::create($jakartaNow->year, $jakartaNow->month, $jakartaNow->day, $jakartaNow->hour, $jakartaNow->minute, $jakartaNow->second);
        $today = $now->copy()->startOfDay();

        $names = [
            'Dedi Kurniawan', 'Eka Wijaya', 'Fitri Handayani', 'Galih Pratama',
            'Indra Gunawan', 'Joko Susanto', 'Kartika Sari', 'Lina Marlina',
            'Made Wirawan', 'Nurul Aini', 'Oscar Pradana', 'Putri Ayu Lestari',
            'Rudi Hartono', 'Siti Rahmawati',
        ];

        // 1 minggu kemarin (7 hari) + hari ini. Minggu dianggap libur.
        $workDays = collect(range(7, 0))
            ->map(fn (int $daysAgo) => $today->copy()->subDays($daysAgo))
            ->reject(fn (Carbon $date) => $date->isSunday());

        foreach ($names as $fullName) {
            $employee = Employee::firstOrCreate(
                ['full_name' => $fullName],
                [
                    'employee_code' => (int) (Employee::max('employee_code') ?? 0) + 1,
                    'is_active' => true,
                    'pin_salt' => bin2hex(random_bytes(8)),
                ]
            );

            // Backdate created_at ke awal periode simulasi supaya laporan
            // tidak mengira karyawan ini "belum ada" pada hari-hari yang
            // sengaja dibuat "tidak masuk" (tanpa log sama sekali).
            if ($employee->wasRecentlyCreated) {
                $employee->forceFill(['created_at' => $workDays->first()])->save();
            }

            foreach ($workDays as $date) {
                $this->seedDay($device, $employee, $date, $today, $now);
            }
        }
    }

    private function seedDay(Device $device, Employee $employee, Carbon $date, Carbon $today, Carbon $now): void
    {
        $isToday = $date->isSameDay($today);

        // Sudah ada log untuk hari ini? Jangan re-roll, biar tidak menimpa
        // hasil acak yang sudah tersimpan.
        if (AttendanceLog::where('employee_id', $employee->id)->whereDate('event_time', $date)->exists()) {
            return;
        }

        if ($this->chance(self::ABSENT_CHANCE)) {
            return; // tidak masuk — sengaja tidak membuat log sama sekali
        }

        $isLate = $this->chance(self::LATE_CHANCE);
        $clockIn = $isLate
            ? [8, random_int(47, 90)] // 08:47 s/d ~09:30 (menit dibiarkan overflow, dirapikan di jitter)
            : $this->jitter(7, 55, -10, 8);

        [$inHour, $inMinute] = $this->normalizeTime(...$clockIn);

        $isOverBreak = $this->chance(self::OVER_BREAK_CHANCE);
        $breakStart = [12, random_int(0, 5)];
        $breakEnd = $isOverBreak ? [13, random_int(20, 45)] : [13, random_int(0, 5)];

        $isEarlyOut = $this->chance(self::EARLY_OUT_CHANCE);
        $clockOut = $isEarlyOut
            ? [16, random_int(15, 45)]
            : $this->jitter(17, 5, -5, 15);

        [$outHour, $outMinute] = $this->normalizeTime(...$clockOut);

        $events = [
            'clock_in' => [$inHour, $inMinute],
            'break_start' => $breakStart,
            'break_end' => $breakEnd,
            'clock_out' => [$outHour, $outMinute],
        ];

        foreach ($events as $type => [$hour, $minute]) {
            $eventTime = $date->copy()->setTime($hour, $minute);

            // Jangan catat event yang jamnya belum lewat waktu sekarang.
            if ($isToday && $eventTime->greaterThan($now)) {
                continue;
            }

            $this->logAttendance($device, $employee, $type, $eventTime);
        }
    }

    private function chance(int $percent): bool
    {
        return random_int(1, 100) <= $percent;
    }

    /**
     * Variasi menit dalam rentang [$min, $max] dari jam:menit dasar.
     */
    private function jitter(int $hour, int $minute, int $min, int $max): array
    {
        return $this->normalizeTime($hour, $minute + random_int($min, $max));
    }

    /**
     * Rapikan menit yang bisa melebihi 59 / kurang dari 0 jadi jam:menit valid.
     */
    private function normalizeTime(int $hour, int $minute): array
    {
        $totalMinutes = $hour * 60 + $minute;
        $totalMinutes = max(0, min(23 * 60 + 59, $totalMinutes));

        return [intdiv($totalMinutes, 60), $totalMinutes % 60];
    }

    private function logAttendance(Device $device, Employee $employee, string $type, Carbon $eventTime): void
    {
        AttendanceLog::create([
            'device_id' => $device->id,
            'employee_id' => $employee->id,
            'attendance_type' => $type,
            'method' => 'fingerprint',
            'event_time' => $eventTime,
            'client_uuid' => (string) Str::uuid(),
        ]);
    }
}
