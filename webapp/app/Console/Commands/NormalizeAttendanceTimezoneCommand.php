<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Perbaiki log yang tersimpan sebagai wall-clock WIB berlabel UTC (+00),
 * menjadi UTC sejati. Skip log yang sudah terlihat sebagai UTC benar
 * (jam masuk pagi tersimpan sekitar 00:00–05:59 UTC untuk zona WIB).
 */
class NormalizeAttendanceTimezoneCommand extends Command
{
    protected $signature = 'attendance:normalize-utc
                            {--timezone=Asia/Jakarta : Timezone wall-clock yang dipakai data lama}
                            {--dry-run : Hanya hitung, tidak menulis}';

    protected $description = 'Normalisasi attendance_logs ke UTC sejati dari wall-clock lama';

    public function handle(): int
    {
        $sourceTz = (string) $this->option('timezone');
        $dryRun = (bool) $this->option('dry-run');
        $fixed = 0;
        $skipped = 0;

        AttendanceLog::query()->orderBy('event_time')->chunkById(200, function ($logs) use ($sourceTz, $dryRun, &$fixed, &$skipped) {
            foreach ($logs as $log) {
                /** @var AttendanceLog $log */
                $utc = $log->event_time->copy()->utc();
                $hour = (int) $utc->format('H');

                $looksLikeWall = match ($log->attendance_type) {
                    'clock_in' => $hour >= 6 && $hour <= 12,
                    'break_start', 'break_end' => $hour >= 10 && $hour <= 16,
                    'clock_out' => $hour >= 15 && $hour <= 23,
                    default => $hour >= 6 && $hour <= 20,
                };

                if (! $looksLikeWall) {
                    $skipped++;

                    continue;
                }

                $wall = Carbon::create(
                    (int) $utc->format('Y'),
                    (int) $utc->format('m'),
                    (int) $utc->format('d'),
                    (int) $utc->format('H'),
                    (int) $utc->format('i'),
                    (int) $utc->format('s'),
                    $sourceTz,
                );

                if (! $dryRun) {
                    $log->event_time = $wall->utc();
                    $log->save();
                }

                $fixed++;
            }
        });

        $this->info(($dryRun ? '[dry-run] ' : '')."Normalized={$fixed}, skipped={$skipped}");

        return self::SUCCESS;
    }
}
