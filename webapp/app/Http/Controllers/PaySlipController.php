<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Support\PaySlipPrintConfig;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PaySlipController extends Controller
{
    public function previewSample(Request $request): Response
    {
        $print = PaySlipPrintConfig::fromRequest($request);
        $brand = $this->slipBrand();

        // Samakan layout dengan cetak multi-slip (pack A4 = grid)
        if ($print->shouldPackPerPage()) {
            $period = (object) ['label' => 'Preview'];
            $entries = collect([
                $this->sampleEntry('Budi Santoso', '01', 1650000, 50000, 60000),
                $this->sampleEntry('Siti Aminah', '02', 2000000, 50000, 0),
                $this->sampleEntry('Andi Wijaya', '03', 1750000, 0, 100000),
                $this->sampleEntry('Rina Putri', '04', 1900000, 75000, 50000),
            ]);

            return $this->streamPdf(
                view: 'payroll.slip-pdf-period',
                data: [
                    'period' => $period,
                    'entries' => $entries,
                    'print' => $print,
                    'paper' => $print->paper,
                    'compact' => $print->isCompact(),
                    'brand' => $brand,
                ],
                print: $print,
                filename: 'slip-preview.pdf',
                inline: true,
            );
        }

        return $this->streamPdf(
            view: 'payroll.slip-pdf',
            data: [
                'entry' => $this->sampleEntry(),
                'print' => $print,
                'paper' => $print->paper,
                'compact' => $print->isCompact(),
                'brand' => $brand,
            ],
            print: $print,
            filename: 'slip-preview.pdf',
            inline: true,
        );
    }

    public function print(Request $request, PayrollPeriod $period, PayrollEntry $entry): View|Response
    {
        abort_unless($entry->payroll_period_id === $period->id, 404);
        abort_if($period->isDraft(), 403, 'Slip belum tersedia untuk periode Draft.');

        $print = PaySlipPrintConfig::fromRequest($request);
        $entry->load(['employee', 'details', 'period']);

        $data = [
            'entry' => $entry,
            'print' => $print,
            'paper' => $print->paper,
            'compact' => $print->isCompact(),
            'brand' => $this->slipBrand(),
        ];
        $filename = 'slip-gaji-'.$entry->employee->full_name.'-'.$period->label.'-'.$print->paper.'.pdf';
        $query = $print->toQuery(['format' => 'pdf']);

        if ($request->query('format') === 'pdf') {
            return $this->streamPdf(
                view: 'payroll.slip-pdf',
                data: $data,
                print: $print,
                filename: $filename,
                inline: $request->boolean('inline'),
            );
        }

        return view('payroll.slip-print', [
            'period' => $period,
            'paper' => $print->paper,
            'count' => 1,
            'autoPrint' => true,
            'pdfInlineUrl' => route('payroll.slip', [$period, $entry] + $query + ['inline' => 1]),
            'pdfDownloadUrl' => route('payroll.slip', [$period, $entry] + $query),
        ]);
    }

    public function printPeriod(Request $request, PayrollPeriod $period): View|Response
    {
        abort_if($period->isDraft(), 403, 'Slip belum tersedia untuk periode Draft.');

        $print = PaySlipPrintConfig::fromRequest($request);
        $entryIds = $this->selectedEntryIds($request);

        abort_if($entryIds === [], 422, 'Centang karyawan yang mau dicetak terlebih dahulu.');

        $entries = $period->entries()
            ->with(['employee', 'details', 'period'])
            ->whereIn('id', $entryIds)
            ->orderBy('net_salary', 'desc')
            ->get();

        abort_if($entries->isEmpty(), 404, 'Tidak ada data gaji untuk karyawan yang dipilih.');

        $data = [
            'period' => $period,
            'entries' => $entries,
            'print' => $print,
            'paper' => $print->paper,
            'compact' => $print->isCompact(),
            'brand' => $this->slipBrand(),
        ];
        $filename = 'slip-gaji-'.$period->label.'-'.$print->paper.'.pdf';
        $query = $print->toQuery([
            'format' => 'pdf',
            'entries' => implode(',', $entries->pluck('id')->all()),
        ]);

        if ($request->query('format') === 'pdf') {
            return $this->streamPdf(
                view: 'payroll.slip-pdf-period',
                data: $data,
                print: $print,
                filename: $filename,
                inline: $request->boolean('inline'),
            );
        }

        return view('payroll.slip-print', [
            'period' => $period,
            'paper' => $print->paper,
            'count' => $entries->count(),
            'autoPrint' => true,
            'pdfInlineUrl' => route('payroll.slips', [$period] + $query + ['inline' => 1]),
            'pdfDownloadUrl' => route('payroll.slips', [$period] + $query),
        ]);
    }

    /**
     * @return list<string>
     */
    private function selectedEntryIds(Request $request): array
    {
        $raw = $request->query('entries', []);

        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }

        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function slipBrand(): string
    {
        $company = CompanySetting::active();

        return $company->trade_name ?: ($company->company_name ?: (string) config('app.name'));
    }

    /**
     * Data contoh untuk preview DomPDF (sama engine dengan cetak asli).
     */
    private function sampleEntry(
        string $name = 'Budi Santoso',
        string $code = '01',
        float $base = 1650000,
        float $allowance = 50000,
        float $cashBon = 60000,
    ): object {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        $details = collect();
        if ($allowance > 0) {
            $details->push((object) ['category' => 'allowance', 'label' => 'Tunjangan BBM', 'amount' => $allowance]);
        }
        if ($cashBon > 0) {
            $details->push((object) ['category' => 'cash_bon', 'label' => 'Cash Bon cicilan 1/4', 'amount' => $cashBon]);
        }

        return (object) [
            'base_salary' => $base,
            'net_salary' => $base + $allowance - $cashBon,
            'notes' => null,
            'employee' => (object) [
                'full_name' => $name,
                'employee_code' => $code,
            ],
            'period' => (object) [
                'label' => 'Juli 2026',
                'period_start' => $start,
                'period_end' => $end,
            ],
            'details' => $details,
        ];
    }

    private function streamPdf(string $view, array $data, PaySlipPrintConfig $print, string $filename, bool $inline = false): Response
    {
        $pdf = Pdf::loadView($view, $data);
        $pdf->setOption('defaultFont', $print->defaultFontOption());
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', false);
        $print->applyToPdf($pdf);

        return $inline ? $pdf->stream($filename) : $pdf->download($filename);
    }
}
