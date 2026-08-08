<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sumber libur nasional & cuti bersama Indonesia.
 * Preferensi: API publik (di-cache), fallback data lokal jika offline.
 */
class IndonesianHolidays
{
    private const CACHE_TTL_SECONDS = 86400; // 24 jam

    private const API_URL = 'https://api-hari-libur.vercel.app/api';

    /**
     * @return array<string, array{name: string, is_joint_leave: bool}>
     *         keyed by Y-m-d
     */
    public static function forYear(int $year): array
    {
        $cacheKey = "id_holidays_{$year}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($year) {
            $fromApi = self::fetchFromApi($year);

            return $fromApi !== null ? $fromApi : self::fallbackForYear($year);
        });
    }

    /**
     * @param  list<int>  $years
     * @return array<string, array{name: string, is_joint_leave: bool}>
     */
    public static function forYears(array $years): array
    {
        $merged = [];
        foreach (array_unique($years) as $year) {
            $merged += self::forYear((int) $year);
        }

        ksort($merged);

        return $merged;
    }

    /**
     * @return array<string, array{name: string, is_joint_leave: bool}>|null
     */
    private static function fetchFromApi(int $year): ?array
    {
        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get(self::API_URL, ['year' => $year]);

            if (! $response->successful()) {
                return null;
            }

            $payload = $response->json();
            $rows = $payload['data'] ?? null;

            if (! is_array($rows)) {
                return null;
            }

            $out = [];
            foreach ($rows as $row) {
                $date = (string) ($row['date'] ?? '');
                $name = trim((string) ($row['description'] ?? $row['name'] ?? ''));
                if ($date === '' || $name === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    continue;
                }

                $out[$date] = [
                    'name' => $name,
                    'is_joint_leave' => str_contains(mb_strtolower($name), 'cuti bersama'),
                ];
            }

            return $out !== [] ? $out : null;
        } catch (Throwable $e) {
            Log::warning('Gagal memuat libur nasional dari API.', [
                'year' => $year,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fallback offline (libur tetap + beberapa tanggal penting 2025–2027).
     *
     * @return array<string, array{name: string, is_joint_leave: bool}>
     */
    private static function fallbackForYear(int $year): array
    {
        $fixed = [
            sprintf('%04d-01-01', $year) => 'Tahun Baru Masehi',
            sprintf('%04d-05-01', $year) => 'Hari Buruh Internasional',
            sprintf('%04d-06-01', $year) => 'Hari Lahir Pancasila',
            sprintf('%04d-08-17', $year) => 'Hari Kemerdekaan Republik Indonesia',
            sprintf('%04d-12-25', $year) => 'Hari Raya Natal',
        ];

        $extra = match ($year) {
            2025 => [
                '2025-01-27' => 'Isra Mikraj Nabi Muhammad SAW',
                '2025-01-28' => 'Cuti Bersama Isra Mikraj',
                '2025-01-29' => 'Tahun Baru Imlek 2576',
                '2025-03-28' => 'Hari Suci Nyepi',
                '2025-03-29' => 'Cuti Bersama Nyepi',
                '2025-03-31' => 'Hari Raya Idul Fitri 1446 H',
                '2025-04-01' => 'Hari Raya Idul Fitri 1446 H',
                '2025-04-02' => 'Cuti Bersama Idul Fitri',
                '2025-04-03' => 'Cuti Bersama Idul Fitri',
                '2025-04-04' => 'Cuti Bersama Idul Fitri',
                '2025-04-18' => 'Wafat Yesus Kristus',
                '2025-05-12' => 'Hari Raya Waisak',
                '2025-05-13' => 'Cuti Bersama Waisak',
                '2025-05-29' => 'Kenaikan Yesus Kristus',
                '2025-05-30' => 'Cuti Bersama Kenaikan Yesus Kristus',
                '2025-06-06' => 'Hari Raya Idul Adha 1446 H',
                '2025-06-09' => 'Cuti Bersama Idul Adha',
                '2025-06-27' => 'Tahun Baru Islam 1447 H',
                '2025-09-05' => 'Maulid Nabi Muhammad SAW',
            ],
            2026 => [
                '2026-01-16' => "Isra Mi'raj Nabi Muhammad SAW",
                '2026-02-17' => 'Tahun Baru Imlek 2577 Kongzili',
                '2026-03-18' => 'Cuti Bersama Hari Suci Nyepi',
                '2026-03-19' => 'Hari Suci Nyepi Tahun Baru Saka 1948',
                '2026-03-20' => 'Cuti Bersama Hari Raya Idul Fitri',
                '2026-03-21' => 'Hari Raya Idul Fitri 1447 Hijriyah',
                '2026-03-22' => 'Hari Raya Idul Fitri 1447 Hijriyah',
                '2026-03-23' => 'Cuti Bersama Hari Raya Idul Fitri',
                '2026-03-24' => 'Cuti Bersama Hari Raya Idul Fitri',
                '2026-04-03' => 'Wafat Yesus Kristus / Jumat Agung',
                '2026-04-05' => 'Kebangkitan Yesus Kristus (Paskah)',
                '2026-05-14' => 'Kenaikan Yesus Kristus',
                '2026-05-15' => 'Cuti Bersama Kenaikan Yesus Kristus',
                '2026-05-27' => 'Hari Raya Waisak',
                '2026-05-28' => 'Cuti Bersama Waisak',
                '2026-05-31' => 'Cuti Bersama Hari Lahir Pancasila',
                '2026-06-17' => 'Hari Raya Idul Adha 1447 H',
                '2026-06-18' => 'Cuti Bersama Idul Adha',
                '2026-07-07' => 'Tahun Baru Islam 1448 H',
                '2026-08-25' => 'Maulid Nabi Muhammad SAW',
                '2026-12-24' => 'Cuti Bersama Natal',
            ],
            2027 => [
                '2027-02-06' => 'Tahun Baru Imlek',
                '2027-03-08' => 'Hari Suci Nyepi',
                '2027-03-10' => 'Hari Raya Idul Fitri',
                '2027-03-11' => 'Hari Raya Idul Fitri',
                '2027-03-26' => 'Wafat Yesus Kristus',
                '2027-05-03' => 'Kenaikan Yesus Kristus',
                '2027-05-16' => 'Hari Raya Waisak',
                '2027-06-06' => 'Hari Raya Idul Adha',
                '2027-06-26' => 'Tahun Baru Islam',
                '2027-08-15' => 'Maulid Nabi Muhammad SAW',
            ],
            default => [],
        };

        $merged = $fixed + $extra;
        $out = [];
        foreach ($merged as $date => $name) {
            $out[$date] = [
                'name' => $name,
                'is_joint_leave' => str_contains(mb_strtolower($name), 'cuti bersama'),
            ];
        }

        ksort($out);

        return $out;
    }
}
