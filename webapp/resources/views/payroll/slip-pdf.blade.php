<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Slip Gaji - {{ $entry->employee->full_name }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #333; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 4px 0 0; color: #666; font-size: 11px; }
        .info-grid { display: table; width: 100%; margin-bottom: 20px; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; width: 140px; padding: 3px 0; color: #666; }
        .info-value { display: table-cell; padding: 3px 0; font-weight: bold; }
        table.details { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.details th { background: #f3f4f6; text-align: left; padding: 8px 10px; font-size: 11px; text-transform: uppercase; color: #666; border-bottom: 1px solid #ddd; }
        table.details td { padding: 6px 10px; border-bottom: 1px solid #eee; }
        table.details td.amount { text-align: right; font-family: monospace; }
        .total-row { font-weight: bold; background: #f9fafb; }
        .total-row td { border-top: 2px solid #333; padding-top: 10px; }
        .net-salary { font-size: 16px; }
        .section-title { font-weight: bold; color: #333; padding-top: 10px; }
        .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #999; }
        .add { color: #16a34a; }
        .sub { color: #dc2626; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
        <p>SLIP GAJI KARYAWAN</p>
    </div>

    <div class="info-grid">
        <div class="info-row">
            <span class="info-label">Nama Karyawan</span>
            <span class="info-value">{{ $entry->employee->full_name }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">ID Karyawan</span>
            <span class="info-value">{{ $entry->employee->employee_code }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Periode</span>
            <span class="info-value">{{ $entry->period->label }} ({{ $entry->period->period_start->format('d/m/Y') }} - {{ $entry->period->period_end->format('d/m/Y') }})</span>
        </div>
    </div>

    <table class="details">
        <thead>
            <tr>
                <th>Komponen</th>
                <th style="text-align: right">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Gaji Pokok</td>
                <td class="amount">{{ number_format($entry->base_salary, 0, ',', '.') }}</td>
            </tr>

            @foreach ($entry->details->where('category', 'allowance') as $detail)
                <tr>
                    <td>{{ $detail->label }}</td>
                    <td class="amount add">+{{ number_format($detail->amount, 0, ',', '.') }}</td>
                </tr>
            @endforeach

            @foreach ($entry->details->where('category', 'overtime') as $detail)
                <tr>
                    <td>{{ $detail->label }}</td>
                    <td class="amount add">+{{ number_format($detail->amount, 0, ',', '.') }}</td>
                </tr>
            @endforeach

            @foreach ($entry->details->where('category', 'deduction') as $detail)
                <tr>
                    <td>{{ $detail->label }}</td>
                    <td class="amount sub">-{{ number_format($detail->amount, 0, ',', '.') }}</td>
                </tr>
            @endforeach

            @foreach ($entry->details->where('category', 'cash_bon') as $detail)
                <tr>
                    <td>{{ $detail->label }}</td>
                    <td class="amount sub">-{{ number_format($detail->amount, 0, ',', '.') }}</td>
                </tr>
            @endforeach

            @foreach ($entry->details->where('category', 'penalty') as $detail)
                <tr>
                    <td>{{ $detail->label }}</td>
                    <td class="amount sub">-{{ number_format($detail->amount, 0, ',', '.') }}</td>
                </tr>
            @endforeach

            @foreach ($entry->details->where('category', 'tax') as $detail)
                <tr>
                    <td>{{ $detail->label }}</td>
                    <td class="amount sub">-{{ number_format($detail->amount, 0, ',', '.') }}</td>
                </tr>
            @endforeach

            <tr class="total-row">
                <td class="net-salary">GAJI BERSIH</td>
                <td class="amount net-salary">Rp {{ number_format($entry->net_salary, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    @if ($entry->notes)
        <p><strong>Catatan:</strong> {{ $entry->notes }}</p>
    @endif

    <div class="footer">
        <p>Dokumen ini digenerate secara otomatis oleh sistem {{ config('app.name') }}.</p>
    </div>
</body>
</html>
