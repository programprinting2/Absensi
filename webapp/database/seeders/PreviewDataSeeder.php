<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Employee;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Data dummy khusus untuk preview UI di SQLite lokal.
 * Tidak dipakai saat sudah connect ke Supabase (schema-of-record ada di supabase/migrations/).
 */
class PreviewDataSeeder extends Seeder
{
    public function run(): void
    {
        $device = Device::create([
            'device_code' => 'DEV-001',
            'name' => 'Pintu Utama',
            'location' => 'Kantor Pusat',
            'is_active' => true,
        ]);

        WorkSchedule::create([
            'name' => 'Jadwal Default',
            'clock_in_time' => '08:00',
            'clock_out_time' => '17:00',
            'break_duration_minutes' => 60,
            'is_active' => true,
        ]);

        $employees = collect([
            ['employee_code' => 1, 'full_name' => 'Andi Saputra'],
            ['employee_code' => 2, 'full_name' => 'Budi Santoso'],
            ['employee_code' => 3, 'full_name' => 'Citra Lestari'],
        ])->map(function (array $data) {
            $salt = bin2hex(random_bytes(8));
            $employee = new Employee($data + [
                'is_active' => true,
                'pin_salt' => $salt,
                'pin_hash' => hash_hmac('sha256', '123456', $salt),
            ]);
            $employee->save();

            return $employee;
        });

        foreach ($employees as $employee) {
            foreach ([2, 1, 0] as $daysAgo) {
                $date = Carbon::today()->subDays($daysAgo);

                AttendanceLog::create([
                    'device_id' => $device->id,
                    'employee_id' => $employee->id,
                    'attendance_type' => 'clock_in',
                    'method' => 'fingerprint',
                    'event_time' => $date->copy()->setTime(7, 45),
                    'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
                ]);

                AttendanceLog::create([
                    'device_id' => $device->id,
                    'employee_id' => $employee->id,
                    'attendance_type' => 'clock_out',
                    'method' => 'fingerprint',
                    'event_time' => $date->copy()->setTime(17, 5),
                    'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
                ]);
            }
        }
    }
}
