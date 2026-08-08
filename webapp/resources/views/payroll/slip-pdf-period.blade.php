@php
    /** @var \App\Support\PaySlipPrintConfig $print */
    $typo = $print->typography();
    $font = $print->pdfFontFamily();
    $fit = $print->fitToWidth;
    $brand = $brand ?? config('app.name');
    $pack = $print->shouldPackPerPage();
    $perPage = $print->slipsPerPage();
    $cols = $print->packColumns();
    // Font sedikit lebih kecil saat banyak slip per halaman
    if ($pack) {
        $typo['body'] = max(7, round($typo['body'] * 0.85, 1));
        $typo['h1'] = max(9, round($typo['h1'] * 0.85, 1));
        $typo['sub'] = max(6.5, round($typo['sub'] * 0.85, 1));
        $typo['th'] = max(6, round($typo['th'] * 0.85, 1));
        $typo['net'] = max(8, round($typo['net'] * 0.85, 1));
        $typo['footer'] = max(5.5, round($typo['footer'] * 0.85, 1));
    }
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Slip Gaji - {{ $period->label }}</title>
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
        table.pack-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.pack-grid td.pack-cell {
            vertical-align: top;
            width: {{ $cols > 1 ? '50%' : '100%' }};
            padding: 2.5mm;
            border: 1px solid #ccc;
        }
        .pack-stack { margin-bottom: 4mm; padding-bottom: 3mm; border-bottom: 1px dashed #bbb; page-break-inside: avoid; }
    </style>
</head>
<body>
    @if ($pack)
        @foreach ($entries->chunk($perPage) as $pageEntries)
            @if ($cols > 1)
                <table class="pack-grid">
                    @foreach ($pageEntries->chunk($cols) as $row)
                        <tr>
                            @foreach ($row as $entry)
                                <td class="pack-cell">
                                    @include('payroll.partials.slip-body', ['entry' => $entry, 'compact' => true, 'brand' => $brand])
                                </td>
                            @endforeach
                            @for ($i = $row->count(); $i < $cols; $i++)
                                <td class="pack-cell"></td>
                            @endfor
                        </tr>
                    @endforeach
                </table>
            @else
                @foreach ($pageEntries as $entry)
                    <div class="pack-stack">
                        @include('payroll.partials.slip-body', ['entry' => $entry, 'compact' => true, 'brand' => $brand])
                    </div>
                @endforeach
            @endif
            @unless ($loop->last)
                <div class="page-break"></div>
            @endunless
        @endforeach
    @else
        @foreach ($entries as $entry)
            @include('payroll.partials.slip-body', ['entry' => $entry, 'compact' => $print->isCompact(), 'brand' => $brand])
            @unless ($loop->last)
                <div class="page-break"></div>
            @endunless
        @endforeach
    @endif
</body>
</html>
