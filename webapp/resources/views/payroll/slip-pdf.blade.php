@php
    /** @var \App\Support\PaySlipPrintConfig $print */
    $typo = $print->typography();
    $font = $print->pdfFontFamily();
    $fit = $print->fitToWidth;
    $brand = $brand ?? config('app.name');
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Slip Gaji - {{ $entry->employee->full_name }}</title>
    <style>
        body {
            font-family: {{ $font }}, Helvetica, sans-serif;
            font-size: {{ $typo['body'] }}pt;
            color: #111;
            margin: {{ $print->bodyMarginCss() }};
        }
        .header { text-align: center; margin-bottom: 2mm; border-bottom: 1px solid #111; padding-bottom: 1.5mm; }
        .header h1 { margin: 0; font-size: {{ $typo['h1'] }}pt; font-family: {{ $font }}, Helvetica, sans-serif; }
        .header p { margin: 1px 0 0; color: #444; font-size: {{ $typo['sub'] }}pt; }
        .info-grid { width: 100%; margin-bottom: 2mm; border-collapse: collapse; }
        .info-label { color: #555; {{ $fit ? 'width: 32%;' : '' }} }
        .info-value { font-weight: bold; }
        table.details { width: 100%; border-collapse: collapse; margin-bottom: 2mm; }
        table.details th {
            background: #f3f4f6;
            text-align: left;
            padding: {{ $typo['thPad'] }};
            font-size: {{ $typo['th'] }}pt;
            text-transform: uppercase;
            color: #555;
            border-bottom: 1px solid #ccc;
        }
        table.details td { padding: {{ $typo['pad'] }}; border-bottom: 1px solid #eee; }
        table.details td.amount { text-align: right; white-space: nowrap; {{ $fit ? 'width: 38%;' : '' }} }
        .total-row { font-weight: bold; background: #f9fafb; }
        .total-row td { border-top: 1px solid #111; padding-top: 3px; }
        .net-salary { font-size: {{ $typo['net'] }}pt; }
        .footer { margin-top: 1.5mm; text-align: center; font-size: {{ $typo['footer'] }}pt; color: #666; }
        .footer p { margin: 0; }
        .add { color: #15803d; }
        .sub { color: #b91c1c; }
        .page-break { page-break-after: always; }
        .slip-sheet { page-break-inside: avoid; }
    </style>
</head>
<body>
    @include('payroll.partials.slip-body', ['entry' => $entry, 'compact' => $print->isCompact(), 'brand' => $brand])
</body>
</html>
