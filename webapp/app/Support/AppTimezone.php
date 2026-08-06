<?php

namespace App\Support;

use App\Models\CompanySetting;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Penyimpanan absensi: UTC sejati.
 * Tampilan: dikonversi ke timezone bisnis (display_timezone di pengaturan).
 */
class AppTimezone
{
    public static function options(): array
    {
        return [
            'Asia/Jakarta' => 'WIB — Asia/Jakarta (UTC+7)',
            'Asia/Makassar' => 'WITA — Asia/Makassar (UTC+8)',
            'Asia/Jayapura' => 'WIT — Asia/Jayapura (UTC+9)',
            'Asia/Singapore' => 'Asia/Singapore (UTC+8)',
            'Asia/Kuala_Lumpur' => 'Asia/Kuala_Lumpur (UTC+8)',
            'Asia/Bangkok' => 'Asia/Bangkok (UTC+7)',
            'Asia/Tokyo' => 'Asia/Tokyo (UTC+9)',
            'UTC' => 'UTC (UTC+0)',
            'Europe/London' => 'Europe/London',
            'America/New_York' => 'America/New_York',
        ];
    }

    public static function display(): string
    {
        return once(function () {
            $fallback = (string) config('app.display_timezone', 'Asia/Jakarta');

            try {
                $tz = CompanySetting::query()->value('display_timezone');
            } catch (Throwable) {
                $tz = null;
            }

            if (! filled($tz) || ! self::isValid($tz)) {
                return self::isValid($fallback) ? $fallback : 'Asia/Jakarta';
            }

            return $tz;
        });
    }

    public static function isValid(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list(), true);
    }

    /** Konversi UTC → timezone tampilan. */
    public static function toDisplay(Carbon $time): Carbon
    {
        return $time->copy()->utc()->setTimezone(self::display());
    }

    /** Wall-clock di timezone tampilan → UTC untuk disimpan. */
    public static function wallToUtc(int $year, int $month, int $day, int $hour, int $minute, int $second = 0): Carbon
    {
        return Carbon::create($year, $month, $day, $hour, $minute, $second, self::display())->utc();
    }

    /**
     * Awal & akhir hari (timezone tampilan) sebagai UTC.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function dayBoundsUtc(Carbon|string $date): array
    {
        $day = $date instanceof Carbon
            ? $date->copy()->timezone(self::display())->startOfDay()
            : Carbon::createFromFormat('Y-m-d', $date, self::display())->startOfDay();

        return [
            $day->copy()->startOfDay()->utc(),
            $day->copy()->endOfDay()->utc(),
        ];
    }

    public static function nowDisplay(): Carbon
    {
        return Carbon::now(self::display());
    }
}
