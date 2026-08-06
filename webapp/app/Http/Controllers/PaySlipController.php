<?php

namespace App\Http\Controllers;

use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use Barryvdh\DomPDF\Facade\Pdf;

class PaySlipController extends Controller
{
    public function download(PayrollPeriod $period, PayrollEntry $entry)
    {
        $entry->load(['employee', 'details', 'period']);

        $pdf = Pdf::loadView('payroll.slip-pdf', compact('entry'))
            ->setPaper('a4', 'portrait');

        $filename = "slip-gaji-{$entry->employee->full_name}-{$period->label}.pdf";

        return $pdf->download($filename);
    }
}
