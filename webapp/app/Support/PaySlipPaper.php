<?php

namespace App\Support;

use Barryvdh\DomPDF\PDF;

/**
 * Ukuran kertas slip gaji.
 * thermal_15x10 = 150×100 mm (15×10 cm) — label thermal umum.
 * thermal_80 = gulungan struk 80 mm (lebar tetap, tinggi panjang).
 */
class PaySlipPaper
{
    public const DEFAULT = 'thermal_15x10';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'thermal_15x10' => 'Thermal 15×10 cm — printer label/thermal saja',
            'thermal_80' => 'Thermal 80 mm — printer struk saja',
            'a5' => 'A5 — printer laser/inkjet',
            'a4' => 'A4 — printer laser/inkjet (HP, dll)',
        ];
    }

    public static function isValid(string $paper): bool
    {
        return array_key_exists($paper, self::options());
    }

    public static function normalize(?string $paper): string
    {
        $paper = $paper ?: self::DEFAULT;

        return self::isValid($paper) ? $paper : self::DEFAULT;
    }

    /** mm → point DomPDF (1 inch = 25.4 mm = 72 pt). */
    public static function mm(float $mm): float
    {
        return $mm * 72 / 25.4;
    }

    /** Nilai CSS @page size (fallback HTML print). */
    public static function cssPageSize(string $paper): string
    {
        return match (self::normalize($paper)) {
            'thermal_15x10' => '150mm 100mm',
            'thermal_80' => '80mm 297mm',
            'a5' => 'A5',
            default => 'A4',
        };
    }

    /** Lebar preview on-screen (mm). */
    public static function previewWidthMm(string $paper): int
    {
        return match (self::normalize($paper)) {
            'thermal_15x10' => 150,
            'thermal_80' => 80,
            'a5' => 148,
            default => 210,
        };
    }

    public static function apply(PDF $pdf, string $paper): PDF
    {
        $paper = self::normalize($paper);

        return match ($paper) {
            // Label 15×10 cm
            'thermal_15x10' => $pdf->setPaper([0, 0, self::mm(150), self::mm(100)], 'portrait'),
            // Struk 80 mm: lebar 80, tinggi A4 agar 1 slip muat penuh (bukan sempit di pojok)
            'thermal_80' => $pdf->setPaper([0, 0, self::mm(80), self::mm(297)], 'portrait'),
            'a5' => $pdf->setPaper('a5', 'portrait'),
            default => $pdf->setPaper('a4', 'portrait'),
        };
    }

    public static function isCompact(string $paper): bool
    {
        return in_array(self::normalize($paper), ['thermal_15x10', 'thermal_80'], true);
    }
}
