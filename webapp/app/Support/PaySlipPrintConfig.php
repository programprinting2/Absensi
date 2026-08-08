<?php

namespace App\Support;

use App\Models\PayrollSetting;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Request;

/**
 * Konfigurasi cetak slip gaji (Settings / dialog Print + query override).
 */
class PaySlipPrintConfig
{
    public function __construct(
        public string $paper,
        public float $marginTop,
        public float $marginRight,
        public float $marginBottom,
        public float $marginLeft,
        public bool $fitToWidth,
        public string $font,
        public int $fontScale,
        public ?float $customWidthMm,
        public ?float $customHeightMm,
        public bool $packPerPage = true,
    ) {}

    public static function fromSettings(?PayrollSetting $settings = null, ?string $paperOverride = null): self
    {
        $settings ??= PayrollSetting::active();

        $paper = PaySlipPaper::normalize(
            $paperOverride ?: ($settings->slip_paper ?? PaySlipPaper::DEFAULT)
        );

        // Override paper saja (dropdown lama) → pakai ukuran preset, jangan pakai lebar/tinggi kustom lama
        $useCustomSize = $paperOverride === null || $paperOverride === ($settings->slip_paper ?? null);

        $font = in_array($settings->slip_font ?? 'helvetica', ['times', 'helvetica'], true)
            ? $settings->slip_font
            : 'helvetica';

        $scale = (int) ($settings->slip_font_scale ?? 100);
        $scale = max(70, min(150, $scale ?: 100));

        return new self(
            paper: $paper,
            marginTop: (float) ($settings->slip_margin_top_mm ?? 3),
            marginRight: (float) ($settings->slip_margin_right_mm ?? 3),
            marginBottom: (float) ($settings->slip_margin_bottom_mm ?? 3),
            marginLeft: (float) ($settings->slip_margin_left_mm ?? 3),
            fitToWidth: (bool) ($settings->slip_fit_to_width ?? true),
            font: $font,
            fontScale: $scale,
            customWidthMm: $useCustomSize && $settings->slip_width_mm !== null ? (float) $settings->slip_width_mm : null,
            customHeightMm: $useCustomSize && $settings->slip_height_mm !== null ? (float) $settings->slip_height_mm : null,
            packPerPage: ! PaySlipPaper::isCompact($paper),
        );
    }

    /**
     * Baca setting + seluruh query dari dialog Print.
     */
    public static function fromRequest(Request $request, ?PayrollSetting $settings = null): self
    {
        $settings ??= PayrollSetting::active();

        if (! $request->has('paper') && ! $request->has('font_scale') && ! $request->has('mt')) {
            return self::fromSettings($settings, $request->query('paper'));
        }

        $paper = PaySlipPaper::normalize($request->query('paper', $settings->slip_paper));

        $font = $request->query('font', $settings->slip_font ?? 'helvetica');
        $font = in_array($font, ['times', 'helvetica'], true) ? $font : 'helvetica';

        $scale = (int) $request->query('font_scale', $settings->slip_font_scale ?? 100);
        $scale = max(70, min(150, $scale ?: 100));

        $width = $request->query('width', '__omit__');
        $height = $request->query('height', '__omit__');

        if ($width === '__omit__') {
            $customW = $settings->slip_width_mm !== null ? (float) $settings->slip_width_mm : null;
        } else {
            $customW = ($width === '' || $width === null) ? null : (float) $width;
        }

        if ($height === '__omit__') {
            $customH = $settings->slip_height_mm !== null ? (float) $settings->slip_height_mm : null;
        } else {
            $customH = ($height === '' || $height === null) ? null : (float) $height;
        }

        $config = new self(
            paper: $paper,
            marginTop: (float) $request->query('mt', $settings->slip_margin_top_mm ?? 3),
            marginRight: (float) $request->query('mr', $settings->slip_margin_right_mm ?? 3),
            marginBottom: (float) $request->query('mb', $settings->slip_margin_bottom_mm ?? 3),
            marginLeft: (float) $request->query('ml', $settings->slip_margin_left_mm ?? 3),
            fitToWidth: $request->has('fit')
                ? $request->boolean('fit')
                : (bool) ($settings->slip_fit_to_width ?? true),
            font: $font,
            fontScale: $scale,
            customWidthMm: $customW,
            customHeightMm: $customH,
            packPerPage: $request->has('pack')
                ? $request->boolean('pack')
                : ! PaySlipPaper::isCompact($paper),
        );

        // Thermal selalu 1 slip / halaman
        if (PaySlipPaper::isCompact($config->paper)) {
            $config->packPerPage = false;
        }

        return $config;
    }

    public function toQuery(array $extra = []): array
    {
        return array_merge([
            'paper' => $this->paper,
            'font' => $this->font,
            'font_scale' => $this->fontScale,
            'mt' => $this->marginTop,
            'mr' => $this->marginRight,
            'mb' => $this->marginBottom,
            'ml' => $this->marginLeft,
            'fit' => $this->fitToWidth ? 1 : 0,
            'pack' => $this->packPerPage ? 1 : 0,
            'width' => $this->customWidthMm ?? '',
            'height' => $this->customHeightMm ?? '',
        ], $extra);
    }

    public function isCompact(): bool
    {
        return $this->pageWidthMm() <= 160 || $this->pageHeightMm() <= 130 || PaySlipPaper::isCompact($this->paper);
    }

    /** Apakah beberapa slip digabung dalam 1 halaman (A4/A5). */
    public function shouldPackPerPage(): bool
    {
        return $this->packPerPage && ! $this->isCompact();
    }

    /** Jumlah slip per halaman saat pack (A4: 4 = 2×2, A5: 2). */
    public function slipsPerPage(): int
    {
        if (! $this->shouldPackPerPage()) {
            return 1;
        }

        return $this->pageHeightMm() >= 280 ? 4 : 2;
    }

    public function packColumns(): int
    {
        return $this->slipsPerPage() >= 4 ? 2 : 1;
    }

    public function pageWidthMm(): float
    {
        if ($this->customWidthMm && $this->customWidthMm > 0) {
            return $this->customWidthMm;
        }

        return (float) PaySlipPaper::previewWidthMm($this->paper);
    }

    public function pageHeightMm(): float
    {
        if ($this->customHeightMm && $this->customHeightMm > 0) {
            return $this->customHeightMm;
        }

        return match ($this->paper) {
            'thermal_15x10' => 100.0,
            'thermal_80' => 120.0,
            'a5' => 210.0,
            default => 297.0,
        };
    }

    public function pdfFontFamily(): string
    {
        return $this->font === 'times' ? 'Times-Roman' : 'Helvetica';
    }

    public function defaultFontOption(): string
    {
        return $this->pdfFontFamily();
    }

    public function bodyMarginCss(): string
    {
        return sprintf(
            '%smm %smm %smm %smm',
            $this->marginTop,
            $this->marginRight,
            $this->marginBottom,
            $this->marginLeft
        );
    }

    public function scale(float $pt): float
    {
        return round($pt * $this->fontScale / 100, 1);
    }

    /**
     * @return array{body: float, h1: float, sub: float, th: float, pad: string, thPad: string, net: float, footer: float}
     */
    public function typography(): array
    {
        $w = $this->pageWidthMm();
        $h = $this->pageHeightMm();
        $is80 = $w <= 90 || $this->paper === 'thermal_80';
        $isShort = $h <= 110;
        $boost = ($this->fitToWidth && $is80 && ! $isShort) ? 1.1 : 1.0;

        if ($isShort) {
            $body = 7.0;
            $h1 = 9.0;
            $sub = 6.5;
            $th = 6.0;
            $net = 8.0;
            $footer = 5.5;
            $pad = '1px 2px';
            $thPad = '2px 2px';
        } elseif ($is80) {
            $body = 9.5;
            $h1 = 12.0;
            $sub = 8.0;
            $th = 7.5;
            $net = 11.0;
            $footer = 7.0;
            $pad = '2px 2px';
            $thPad = '3px 2px';
        } elseif ($this->isCompact()) {
            $body = 8.0;
            $h1 = 10.0;
            $sub = 7.0;
            $th = 7.0;
            $net = 9.0;
            $footer = 6.0;
            $pad = '2px 2px';
            $thPad = '3px 2px';
        } else {
            $body = 11.0;
            $h1 = 16.0;
            $sub = 10.0;
            $th = 9.0;
            $net = 13.0;
            $footer = 9.0;
            $pad = '5px 8px';
            $thPad = '6px 8px';
        }

        return [
            'body' => $this->scale($body * $boost),
            'h1' => $this->scale($h1 * $boost),
            'sub' => $this->scale($sub * $boost),
            'th' => $this->scale($th * $boost),
            'pad' => $pad,
            'thPad' => $thPad,
            'net' => $this->scale($net * $boost),
            'footer' => $this->scale($footer * $boost),
        ];
    }

    public function applyToPdf(PDF $pdf): PDF
    {
        $w = PaySlipPaper::mm($this->pageWidthMm());
        $h = PaySlipPaper::mm($this->pageHeightMm());

        if (in_array($this->paper, ['a4', 'a5'], true) && ! $this->customWidthMm && ! $this->customHeightMm) {
            return $pdf->setPaper($this->paper, 'portrait');
        }

        return $pdf->setPaper([0, 0, $w, $h], 'portrait');
    }
}
