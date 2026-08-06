<?php

use App\Models\PayrollPeriod;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public PayrollPeriod $period;

    public function mount(PayrollPeriod $period): void
    {
        $this->period = $period;
    }

    public function with(): array
    {
        $entries = $this->period->entries()->with('employee')->orderBy('net_salary', 'desc')->get();
        $totals = [
            'base_salary' => $entries->sum('base_salary'),
            'total_allowances' => $entries->sum('total_allowances'),
            'total_deductions' => $entries->sum('total_deductions'),
            'overtime_amount' => $entries->sum('overtime_amount'),
            'late_penalty' => $entries->sum('late_penalty'),
            'absent_penalty' => $entries->sum('absent_penalty'),
            'early_out_penalty' => $entries->sum('early_out_penalty'),
            'short_work_penalty' => $entries->sum('short_work_penalty'),
            'over_break_penalty' => $entries->sum('over_break_penalty'),
            'pph21_amount' => $entries->sum('pph21_amount'),
            'net_salary' => $entries->sum('net_salary'),
        ];
        $totals['total_penalties'] = $totals['late_penalty']
            + $totals['absent_penalty']
            + $totals['early_out_penalty']
            + $totals['short_work_penalty']
            + $totals['over_break_penalty'];

        return compact('entries', 'totals');
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('payroll.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali</a>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $period->label }}</h2>
                <p class="text-sm text-gray-500">{{ $period->period_start->format('d/m/Y') }} — {{ $period->period_end->format('d/m/Y') }}</p>
            </div>
            <div>
                @if ($period->isDraft())
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-700">Draft</span>
                @elseif ($period->isReview())
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-700">Review</span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-700">Final</span>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-4">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden flex-1 flex flex-col min-h-0">
                <div class="overflow-auto flex-1">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Karyawan</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gaji Pokok</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tunjangan</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Lembur</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Potongan</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Denda</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">PPh21</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gaji Bersih</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($entries as $entry)
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ $entry->employee->full_name }}
                                    @if ($entry->is_adjusted)
                                        <span class="text-xs text-orange-500 ml-1" title="Sudah di-adjust">*</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-right tabular-nums">{{ number_format($entry->base_salary, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm text-green-600 text-right tabular-nums">+{{ number_format($entry->total_allowances, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm text-green-600 text-right tabular-nums">+{{ number_format($entry->overtime_amount, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm text-red-600 text-right tabular-nums">-{{ number_format($entry->total_deductions, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm text-red-600 text-right tabular-nums">-{{ number_format($entry->late_penalty + $entry->absent_penalty + $entry->early_out_penalty + ($entry->short_work_penalty ?? 0) + ($entry->over_break_penalty ?? 0), 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm text-red-600 text-right tabular-nums">-{{ number_format($entry->pph21_amount, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right tabular-nums">{{ number_format($entry->net_salary, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-sm space-x-2">
                                    <a href="{{ route('payroll.entry', [$period, $entry]) }}" class="text-blue-600 hover:text-blue-800">Detail</a>
                                    @if (! $period->isDraft())
                                        <a href="{{ route('payroll.slip', [$period, $entry]) }}" class="text-gray-600 hover:text-gray-800">Slip</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 font-semibold">
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">Total ({{ $entries->count() }} karyawan)</td>
                            <td class="px-4 py-3 text-sm text-gray-900 text-right tabular-nums">{{ number_format($totals['base_salary'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-green-600 text-right tabular-nums">+{{ number_format($totals['total_allowances'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-green-600 text-right tabular-nums">+{{ number_format($totals['overtime_amount'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-red-600 text-right tabular-nums">-{{ number_format($totals['total_deductions'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-red-600 text-right tabular-nums">-{{ number_format($totals['total_penalties'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-red-600 text-right tabular-nums">-{{ number_format($totals['pph21_amount'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 text-right tabular-nums">{{ number_format($totals['net_salary'], 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
