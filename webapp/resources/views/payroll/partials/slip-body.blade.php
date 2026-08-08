@php
    $compact = $compact ?? false;
    $brand = $brand ?? config('app.name');
@endphp
<div class="slip-sheet">
    <div class="header">
        <h1>{{ $brand }}</h1>
        <p>SLIP GAJI KARYAWAN</p>
    </div>

    <table class="info-grid">
        <tr>
            <td class="info-label" style="padding: 1px 0;">Nama</td>
            <td class="info-value" style="padding: 1px 0;">{{ $entry->employee->full_name }}</td>
        </tr>
        <tr>
            <td class="info-label" style="padding: 1px 0;">ID</td>
            <td class="info-value" style="padding: 1px 0;">{{ $entry->employee->employee_code }}</td>
        </tr>
        <tr>
            <td class="info-label" style="padding: 1px 0;">Periode</td>
            <td class="info-value" style="padding: 1px 0;">
                {{ $entry->period->label }}
                @unless ($compact)
                    ({{ $entry->period->period_start->format('d/m/Y') }} - {{ $entry->period->period_end->format('d/m/Y') }})
                @endunless
            </td>
        </tr>
    </table>

    <table class="details">
        <thead>
            <tr>
                <th>Komponen</th>
                <th style="text-align: right">Jumlah</th>
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
                <td class="amount net-salary">{{ number_format($entry->net_salary, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    @if ($entry->notes)
        <p style="margin: 4px 0;"><strong>Catatan:</strong> {{ $entry->notes }}</p>
    @endif

    <div class="footer">
        <p>{{ $brand }}</p>
    </div>
</div>
