<?php

use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Services\PayrollCalculationService;
use App\Support\Toast;
use Livewire\Volt\Component;

new class extends Component
{
    public PayrollPeriod $period;

    /** @var list<string> */
    public array $selected = [];

    public bool $showHistoryModal = false;

    public ?string $historyEntryId = null;

    public ?string $editingEntryId = null;

    public ?float $adjustBaseSalary = null;

    public ?float $adjustOvertimeHours = null;

    public ?string $adjustNotes = null;

    public string $newSide = 'credit';

    public string $newLabel = '';

    public string $newAmount = '';

    public ?string $editingDetailId = null;

    public string $editDetailAmount = '';

    public function mount(PayrollPeriod $period): void
    {
        $this->period = $period;
    }

    public function toggleSelectAll(): void
    {
        $ids = $this->period->entries()->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->selected = count(array_unique($this->selected)) === count($ids)
            ? []
            : $ids;
    }

    public function openHistory(string $entryId): void
    {
        $this->historyEntryId = $entryId;
        $this->showHistoryModal = true;
    }

    public function closeHistory(): void
    {
        $this->showHistoryModal = false;
        $this->historyEntryId = null;
    }

    public function openEdit(string $entryId): void
    {
        if (! $this->period->isReview()) {
            return;
        }

        $entry = $this->findPeriodEntry($entryId);
        $this->editingEntryId = $entry->id;
        $this->syncAdjustFields($entry);
        $this->resetValidation();
        $this->newSide = 'credit';
        $this->newLabel = '';
        $this->newAmount = '';
        $this->cancelEditAmount();
    }

    public function closeEdit(): void
    {
        $this->stopEditing();
    }

    public function stopEditing(): void
    {
        $this->editingEntryId = null;
        $this->adjustBaseSalary = null;
        $this->adjustOvertimeHours = null;
        $this->adjustNotes = null;
        $this->newSide = 'credit';
        $this->newLabel = '';
        $this->newAmount = '';
        $this->cancelEditAmount();
        $this->resetValidation();
    }

    public function startEditAmount(string $detailId, $current = null): void
    {
        if (! $this->editingEntryId || ! $this->period->isReview()) {
            return;
        }

        $this->editingDetailId = $detailId;
        $this->editDetailAmount = (string) (int) round((float) $current);
        $this->resetValidation();
    }

    public function cancelEditAmount(): void
    {
        $this->editingDetailId = null;
        $this->editDetailAmount = '';
    }

    public function saveDetailAmount(PayrollCalculationService $service): void
    {
        if (! $this->editingEntryId || ! $this->editingDetailId || ! $this->period->isReview()) {
            return;
        }

        $data = $this->validate([
            'editDetailAmount' => ['required', 'numeric', 'min:1'],
        ], [], [
            'editDetailAmount' => 'nominal',
        ]);

        try {
            $entry = $service->updateDetailAmount(
                $this->findPeriodEntry($this->editingEntryId),
                $this->editingDetailId,
                (float) $data['editDetailAmount'],
            );
            $this->syncAdjustFields($entry);
            $this->cancelEditAmount();
            Toast::success('Nominal komponen diperbarui.', $this);
        } catch (\Throwable $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function recalculate(PayrollCalculationService $service): void
    {
        if (! $this->editingEntryId || ! $this->period->isReview()) {
            return;
        }

        $entry = $service->recalculateEntry($this->findPeriodEntry($this->editingEntryId));
        $this->period = $entry->period()->first() ?? $this->period->fresh();
        $this->stopEditing();
        Toast::success('Gaji dihitung ulang dari data master + absensi.', $this);
    }

    public function saveAdjustment(PayrollCalculationService $service): void
    {
        if (! $this->editingEntryId || ! $this->period->isReview()) {
            return;
        }

        $entry = $service->applyAdjustment($this->findPeriodEntry($this->editingEntryId), [
            'base_salary' => $this->adjustBaseSalary,
            'overtime_hours' => $this->adjustOvertimeHours,
            'notes' => $this->adjustNotes,
        ]);

        $this->syncAdjustFields($entry);
        Toast::success('Penyesuaian disimpan dan total dihitung ulang.', $this);
    }

    public function addComponent(PayrollCalculationService $service): void
    {
        if (! $this->editingEntryId || ! $this->period->isReview()) {
            return;
        }

        $data = $this->validate([
            'newSide' => ['required', 'in:credit,debit'],
            'newLabel' => ['required', 'string', 'max:100'],
            'newAmount' => ['required', 'numeric', 'min:1'],
        ], [], [
            'newSide' => 'jenis',
            'newLabel' => 'nama komponen',
            'newAmount' => 'nominal',
        ]);

        try {
            $entry = $service->addManualDetail(
                $this->findPeriodEntry($this->editingEntryId),
                $data['newSide'],
                $data['newLabel'],
                (float) $data['newAmount'],
            );
            $this->syncAdjustFields($entry);
            $this->newLabel = '';
            $this->newAmount = '';
            Toast::success('Komponen ditambahkan.', $this);
        } catch (\Throwable $e) {
            $this->addError('newLabel', $e->getMessage());
        }
    }

    public function deleteComponent(string $detailId, PayrollCalculationService $service): void
    {
        if (! $this->editingEntryId || ! $this->period->isReview()) {
            return;
        }

        try {
            $entry = $service->deleteDetail($this->findPeriodEntry($this->editingEntryId), $detailId);
            $this->syncAdjustFields($entry);
            Toast::success('Komponen dihapus.', $this);
        } catch (\Throwable $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function with(): array
    {
        $entries = $this->period->entries()
            ->with(['employee', 'details'])
            ->orderBy('net_salary', 'desc')
            ->get();

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

        $historyEntry = null;
        $salaryHistory = collect();

        if ($this->showHistoryModal && $this->historyEntryId) {
            $historyEntry = $entries->firstWhere('id', $this->historyEntryId)
                ?? PayrollEntry::query()->with('employee')->find($this->historyEntryId);

            if ($historyEntry) {
                $salaryHistory = PayrollEntry::query()
                    ->with('period')
                    ->where('employee_id', $historyEntry->employee_id)
                    ->whereHas('period')
                    ->get()
                    ->sortByDesc(fn (PayrollEntry $row) => optional($row->period)->period_start?->format('Y-m-d') ?? '')
                    ->values();
            }
        }

        $allSelected = $entries->isNotEmpty()
            && collect($this->selected)->unique()->count() === $entries->count();

        $editingEntry = null;
        if ($this->editingEntryId) {
            $editingEntry = $entries->firstWhere('id', $this->editingEntryId)
                ?? PayrollEntry::query()->with(['employee', 'details'])->find($this->editingEntryId);
            if ($editingEntry) {
                $editingEntry->loadMissing(['employee', 'details']);
            }
        }

        return compact('entries', 'totals', 'historyEntry', 'salaryHistory', 'allSelected', 'editingEntry');
    }

    private function findPeriodEntry(string $entryId): PayrollEntry
    {
        return PayrollEntry::query()
            ->where('payroll_period_id', $this->period->id)
            ->whereKey($entryId)
            ->firstOrFail();
    }

    private function syncAdjustFields(PayrollEntry $entry): void
    {
        $this->adjustBaseSalary = (float) $entry->base_salary;
        $this->adjustOvertimeHours = (float) $entry->overtime_hours;
        $this->adjustNotes = $entry->notes;
    }
}; ?>

@php
    $debitCategories = ['deduction', 'penalty', 'tax', 'cash_bon'];
    $categoryLabels = [
        'allowance' => 'Tunjangan (Kredit)',
        'overtime' => 'Lembur (Kredit)',
        'deduction' => 'Potongan (Debet)',
        'cash_bon' => 'Cash Bon (Debet)',
        'penalty' => 'Denda (Debet)',
        'tax' => 'Pajak (Debet)',
    ];
@endphp

<div>
    <div class="space-y-3">
            <div class="bg-white shadow-sm rounded-lg border border-gray-100 px-4 py-2.5 flex items-center gap-3 shrink-0">
                <input
                    type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    @checked($allSelected)
                    wire:click.prevent="toggleSelectAll"
                >
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Karyawan</span>
                <span class="text-xs text-gray-400 ml-auto">Total {{ $entries->count() }} · Gaji bersih Rp {{ number_format($totals['net_salary'], 0, ',', '.') }}</span>
            </div>

            <div class="space-y-2" x-data="{ open: {} }">
                @forelse ($entries as $entry)
                    @php
                        $eid = $entry->id;
                        $totalDenda = $entry->late_penalty + $entry->absent_penalty + $entry->early_out_penalty
                            + ($entry->short_work_penalty ?? 0) + ($entry->over_break_penalty ?? 0);
                        $details = $entry->details->groupBy('category');
                    @endphp
                    <div wire:key="payroll-accordion-{{ $eid }}" class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-100">
                        <div class="flex items-stretch">
                            <div class="flex items-center pl-4 pr-1" @click.stop>
                                <input
                                    type="checkbox"
                                    value="{{ $eid }}"
                                    wire:model="selected"
                                    class="js-slip-entry rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                >
                            </div>
                            <button
                                type="button"
                                class="flex-1 min-w-0 text-left hover:bg-gray-50 transition"
                                @click="open['{{ $eid }}'] = !open['{{ $eid }}']"
                                :aria-expanded="!!open['{{ $eid }}']"
                            >
                                <div class="flex items-center justify-between gap-3 px-3 pt-3 pb-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <svg class="h-4 w-4 text-gray-500 shrink-0 transition-transform" :class="open['{{ $eid }}'] ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="text-sm font-semibold text-gray-900 truncate">
                                            {{ $entry->employee->full_name }}
                                            @if ($entry->is_adjusted)
                                                <span class="text-xs text-orange-500" title="Sudah di-adjust">*</span>
                                            @endif
                                        </span>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900 tabular-nums shrink-0">
                                        Rp {{ number_format($entry->net_salary, 0, ',', '.') }}
                                    </span>
                                </div>
                                <div class="mx-3 mb-3 border border-gray-100 rounded-lg overflow-hidden bg-gray-50/60">
                                    <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-7 divide-x divide-y xl:divide-y-0 divide-gray-100">
                                        <div class="px-3 py-2 min-w-0">
                                            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Pokok</p>
                                            <p class="mt-0.5 text-sm font-semibold tabular-nums text-gray-900">{{ number_format($entry->base_salary, 0, ',', '.') }}</p>
                                        </div>
                                        <div class="px-3 py-2 min-w-0">
                                            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Tunjangan</p>
                                            <p class="mt-0.5 text-sm font-semibold tabular-nums text-green-700">+{{ number_format($entry->total_allowances, 0, ',', '.') }}</p>
                                        </div>
                                        <div class="px-3 py-2 min-w-0">
                                            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Lembur</p>
                                            <p class="mt-0.5 text-sm font-semibold tabular-nums text-emerald-700">
                                                +{{ number_format($entry->overtime_amount, 0, ',', '.') }}
                                                <span class="font-normal text-gray-500">· {{ number_format((float) $entry->overtime_hours, 1) }} jam</span>
                                            </p>
                                        </div>
                                        <div class="px-3 py-2 min-w-0">
                                            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Potongan</p>
                                            <p class="mt-0.5 text-sm font-semibold tabular-nums {{ $entry->total_deductions > 0 ? 'text-red-700' : 'text-gray-800' }}">-{{ number_format($entry->total_deductions, 0, ',', '.') }}</p>
                                        </div>
                                        <div class="px-3 py-2 min-w-0">
                                            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Denda</p>
                                            <p class="mt-0.5 text-sm font-semibold tabular-nums {{ $totalDenda > 0 ? 'text-red-700' : 'text-gray-800' }}">-{{ number_format($totalDenda, 0, ',', '.') }}</p>
                                        </div>
                                        <div class="px-3 py-2 min-w-0">
                                            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Telat</p>
                                            <p class="mt-0.5 text-sm font-semibold tabular-nums {{ $entry->late_count > 0 ? 'text-amber-800' : 'text-gray-800' }}">{{ $entry->late_count }}</p>
                                        </div>
                                        <div class="px-3 py-2 min-w-0">
                                            <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Absen</p>
                                            <p class="mt-0.5 text-sm font-semibold tabular-nums {{ $entry->absent_days > 0 ? 'text-red-700' : 'text-gray-800' }}">{{ $entry->absent_days }}</p>
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </div>

                        <div x-show="open['{{ $eid }}']" x-cloak class="border-t border-gray-100">
                            <div class="p-4 space-y-4 bg-gray-50/60">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button
                                        type="button"
                                        wire:click="openHistory('{{ $eid }}')"
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium border bg-white text-gray-700 border-gray-300 hover:bg-gray-50"
                                    >
                                        History
                                    </button>
                                    @if (! $period->isDraft())
                                        @include('payroll.partials.print-paper-menu', [
                                            'baseUrl' => route('payroll.slip', [$period, $entry]),
                                            'label' => 'Print slip',
                                            'iconOnly' => true,
                                            'btnClass' => 'inline-flex items-center justify-center w-9 h-9 rounded-lg border bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                                        ])
                                    @endif
                                    @if ($period->isReview())
                                        <button
                                            type="button"
                                            wire:click="openEdit('{{ $eid }}')"
                                            class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium border bg-white text-indigo-700 border-indigo-200 hover:bg-indigo-50"
                                        >
                                            Edit
                                        </button>
                                    @endif
                                </div>

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <div class="bg-white rounded-lg border border-gray-100 overflow-hidden">
                                        <div class="px-4 py-3 border-b border-gray-100">
                                            <h4 class="text-sm font-semibold text-gray-800">Ringkasan Absensi</h4>
                                        </div>
                                        <div class="grid grid-cols-2 sm:grid-cols-3 divide-x divide-y divide-gray-100">
                                            <div class="px-3 py-2 min-w-0">
                                                <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Terlambat</p>
                                                <p class="mt-0.5 text-sm font-semibold tabular-nums text-red-700">{{ $entry->late_count }}</p>
                                            </div>
                                            <div class="px-3 py-2 min-w-0">
                                                <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Tidak masuk</p>
                                                <p class="mt-0.5 text-sm font-semibold tabular-nums text-gray-800">{{ $entry->absent_days }}</p>
                                            </div>
                                            <div class="px-3 py-2 min-w-0">
                                                <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Pulang cepat</p>
                                                <p class="mt-0.5 text-sm font-semibold tabular-nums text-red-700">{{ $entry->early_out_count }}</p>
                                            </div>
                                            <div class="px-3 py-2 min-w-0">
                                                <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Over break</p>
                                                <p class="mt-0.5 text-sm font-semibold tabular-nums text-amber-800">{{ $entry->over_break_count ?? 0 }}</p>
                                            </div>
                                            <div class="px-3 py-2 min-w-0">
                                                <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Jam kurang</p>
                                                <p class="mt-0.5 text-sm font-semibold tabular-nums text-red-700">{{ number_format((float) ($entry->short_work_hours ?? 0), 1) }}</p>
                                            </div>
                                            <div class="px-3 py-2 min-w-0">
                                                <p class="text-[10px] font-medium uppercase tracking-wider text-gray-500">Jam lembur</p>
                                                <p class="mt-0.5 text-sm font-semibold tabular-nums text-emerald-700">{{ number_format((float) ($entry->overtime_hours ?? 0), 1) }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-white rounded-lg border border-gray-100 p-4">
                                        <h4 class="text-sm font-semibold text-gray-900 mb-3">Ringkasan Gaji</h4>
                                        <div class="space-y-2 text-sm">
                                            <div class="flex justify-between gap-3">
                                                <span class="text-gray-500">Gaji Pokok</span>
                                                <span class="font-medium tabular-nums">Rp {{ number_format($entry->base_salary, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="flex justify-between gap-3">
                                                <span class="text-gray-500">Total Tunjangan</span>
                                                <span class="font-medium text-green-600 tabular-nums">+Rp {{ number_format($entry->total_allowances, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="flex justify-between gap-3">
                                                <span class="text-gray-500">Lembur ({{ $entry->overtime_hours }} jam)</span>
                                                <span class="font-medium text-green-600 tabular-nums">+Rp {{ number_format($entry->overtime_amount, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="flex justify-between gap-3">
                                                <span class="text-gray-500">Total Potongan</span>
                                                <span class="font-medium text-red-600 tabular-nums">-Rp {{ number_format($entry->total_deductions, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="flex justify-between gap-3">
                                                <span class="text-gray-500">Total Denda</span>
                                                <span class="font-medium text-red-600 tabular-nums">-Rp {{ number_format($totalDenda, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="flex justify-between gap-3">
                                                <span class="text-gray-500">PPh 21</span>
                                                <span class="font-medium text-red-600 tabular-nums">-Rp {{ number_format($entry->pph21_amount, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="flex justify-between gap-3 pt-2 border-t border-gray-100">
                                                <span class="font-semibold text-gray-900">Gaji Bersih</span>
                                                <span class="font-semibold text-gray-900 tabular-nums">Rp {{ number_format($entry->net_salary, 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white rounded-lg border border-gray-100 p-4">
                                    <h4 class="text-sm font-semibold text-gray-900 mb-3">Rincian Komponen</h4>
                                    @if ($entry->details->isEmpty())
                                        <p class="text-sm text-gray-500">Belum ada komponen rincian.</p>
                                    @else
                                        <div class="space-y-3">
                                            @foreach ($categoryLabels as $cat => $catLabel)
                                                @continue(! isset($details[$cat]) || $details[$cat]->isEmpty())
                                                <div>
                                                    <h5 class="text-xs font-medium text-gray-500 uppercase mb-1">{{ $catLabel }}</h5>
                                                    @foreach ($details[$cat] as $detail)
                                                        <div class="flex items-center justify-between gap-3 py-1 text-sm border-b border-gray-50 last:border-0">
                                                            <span class="text-gray-700 min-w-0 truncate">{{ $detail->label }}</span>
                                                            <span @class([
                                                                'tabular-nums font-medium shrink-0',
                                                                'text-red-600' => in_array($cat, $debitCategories, true),
                                                                'text-green-600' => ! in_array($cat, $debitCategories, true),
                                                            ])>
                                                                {{ in_array($cat, $debitCategories, true) ? '-' : '+' }}Rp {{ number_format($detail->amount, 0, ',', '.') }}
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                @if ($entry->notes)
                                    <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
                                        <strong>Catatan:</strong> {{ $entry->notes }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-white shadow-sm rounded-lg px-6 py-12 text-center text-sm text-gray-500">
                        Belum ada data gaji pada periode ini.
                    </div>
                @endforelse
            </div>
    </div>

    @if ($editingEntryId && $editingEntry)
        @php
            $editDetails = $editingEntry->details->groupBy('category');
            $editTotalDenda = $editingEntry->late_penalty + $editingEntry->absent_penalty + $editingEntry->early_out_penalty
                + ($editingEntry->short_work_penalty ?? 0) + ($editingEntry->over_break_penalty ?? 0);
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600/50 p-4"
             @keydown.escape.window="$wire.closeEdit()">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden" @click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Edit Gaji</h3>
                        <p class="text-sm text-gray-500 mt-0.5">{{ $editingEntry->employee->full_name }} · {{ $period->label }}</p>
                    </div>
                    <button type="button" wire:click="closeEdit" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm bg-gray-50 rounded-lg border border-gray-100 p-3">
                        <div>
                            <div class="text-xs text-gray-500">Gaji Bersih</div>
                            <div class="font-semibold tabular-nums">Rp {{ number_format($editingEntry->net_salary, 0, ',', '.') }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Total Denda</div>
                            <div class="font-semibold text-red-600 tabular-nums">-Rp {{ number_format($editTotalDenda, 0, ',', '.') }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">PPh 21</div>
                            <div class="font-semibold text-red-600 tabular-nums">-Rp {{ number_format($editingEntry->pph21_amount, 0, ',', '.') }}</div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 mb-2">Rincian Komponen</h4>
                        @if ($editingEntry->details->isEmpty())
                            <p class="text-sm text-gray-500">Belum ada komponen rincian.</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($categoryLabels as $cat => $catLabel)
                                    @continue(! isset($editDetails[$cat]) || $editDetails[$cat]->isEmpty())
                                    <div>
                                        <h5 class="text-xs font-medium text-gray-500 uppercase mb-1">{{ $catLabel }}</h5>
                                        @foreach ($editDetails[$cat] as $detail)
                                            <div class="flex items-center justify-between gap-3 py-1.5 text-sm border-b border-gray-50 last:border-0">
                                                <span class="text-gray-700 min-w-0 truncate">{{ $detail->label }}</span>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    @if ($editingDetailId === $detail->id)
                                                        <div class="flex items-center gap-1">
                                                            <span @class(['text-xs', 'text-red-600' => in_array($cat, $debitCategories, true), 'text-green-600' => ! in_array($cat, $debitCategories, true)])>
                                                                {{ in_array($cat, $debitCategories, true) ? '-' : '+' }}Rp
                                                            </span>
                                                            <input
                                                                type="number"
                                                                min="1"
                                                                step="1"
                                                                wire:model="editDetailAmount"
                                                                wire:keydown.enter.prevent="saveDetailAmount"
                                                                wire:keydown.escape.prevent="cancelEditAmount"
                                                                wire:blur="saveDetailAmount"
                                                                x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                                                                class="w-28 rounded-md border-gray-300 text-sm tabular-nums shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                            />
                                                        </div>
                                                    @else
                                                        <button
                                                            type="button"
                                                            wire:click="startEditAmount('{{ $detail->id }}', '{{ $detail->amount }}')"
                                                            class="inline-flex items-center rounded px-1.5 py-0.5 -mx-1 hover:bg-gray-100"
                                                            title="Klik untuk ubah nominal"
                                                        >
                                                            <span @class([
                                                                'tabular-nums font-medium',
                                                                'text-red-600' => in_array($cat, $debitCategories, true),
                                                                'text-green-600' => ! in_array($cat, $debitCategories, true),
                                                            ])>
                                                                {{ in_array($cat, $debitCategories, true) ? '-' : '+' }}Rp {{ number_format($detail->amount, 0, ',', '.') }}
                                                            </span>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            wire:click="deleteComponent('{{ $detail->id }}')"
                                                            wire:confirm="Hapus komponen {{ $detail->label }}?"
                                                            class="inline-flex items-center justify-center p-1 rounded text-red-500 hover:bg-red-50"
                                                            title="Hapus komponen"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m-7 0h8m-1-3v-1a1 1 0 00-1-1h-4a1 1 0 00-1 1v1" />
                                                            </svg>
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <p class="mt-2 text-xs text-gray-500">Klik nominal untuk mengubah. Enter/blur = simpan, Esc = batal.</p>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <h4 class="text-sm font-semibold text-gray-900 mb-3">Tambah Komponen</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                            <div>
                                <x-input-label for="edit-newSide" value="Jenis" />
                                <select wire:model="newSide" id="edit-newSide" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    <option value="credit">Kredit (+)</option>
                                    <option value="debit">Debet (−)</option>
                                </select>
                                <x-input-error :messages="$errors->get('newSide')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="edit-newLabel" value="Nama Komponen" />
                                <x-text-input wire:model="newLabel" id="edit-newLabel" type="text" class="mt-1 block w-full" placeholder="Contoh: Bonus / Potongan lain" />
                                <x-input-error :messages="$errors->get('newLabel')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="edit-newAmount" value="Nominal (Rp)" />
                                <x-text-input wire:model="newAmount" id="edit-newAmount" type="number" min="1" step="1" class="mt-1 block w-full" placeholder="50000" />
                                <x-input-error :messages="$errors->get('newAmount')" class="mt-1" />
                            </div>
                        </div>
                        <div class="mt-3 flex justify-end">
                            <x-primary-button type="button" wire:click="addComponent">Tambah</x-primary-button>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <h4 class="text-sm font-semibold text-gray-900 mb-3">Adjustment</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="edit-adjustBaseSalary" value="Gaji Pokok Override" />
                                <x-currency-input id="edit-adjustBaseSalary" wire="adjustBaseSalary" :value="$adjustBaseSalary" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label for="edit-adjustOvertimeHours" value="Jam Lembur Override" />
                                <x-text-input wire:model="adjustOvertimeHours" id="edit-adjustOvertimeHours" type="number" step="0.5" class="mt-1 block w-full" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="edit-adjustNotes" value="Catatan" />
                                <textarea wire:model="adjustNotes" id="edit-adjustNotes" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-t border-gray-100 shrink-0 bg-gray-50">
                    <button type="button" wire:click="recalculate" wire:confirm="Reset & hitung ulang dari absensi & master? Komponen manual bisa hilang." class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Reset
                    </button>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" wire:click="closeEdit" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Tutup
                        </button>
                        <x-primary-button type="button" wire:click="saveAdjustment">Simpan Adjustment</x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showHistoryModal && $historyEntry)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600/50 p-4"
             @keydown.escape.window="$wire.closeHistory()">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden" @click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">History Gaji</h3>
                        <p class="text-sm text-gray-500 mt-0.5">{{ $historyEntry->employee->full_name }}</p>
                    </div>
                    <button type="button" wire:click="closeHistory" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex-1 overflow-auto px-6 py-4">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Periode</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gaji Pokok</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tunjangan</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Potongan</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gaji Bersih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($salaryHistory as $history)
                                @php
                                    $historyPeriod = $history->period;
                                    $isCurrent = $history->id === $historyEntry->id;
                                @endphp
                                <tr @class(['bg-indigo-50/40' => $isCurrent])>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 whitespace-nowrap">
                                        {{ $historyPeriod?->label ?? '—' }}
                                        @if ($isCurrent)
                                            <span class="ml-1 text-[10px] font-semibold uppercase text-indigo-600">saat ini</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">
                                        @if ($historyPeriod)
                                            {{ $historyPeriod->period_start->format('d/m/Y') }} — {{ $historyPeriod->period_end->format('d/m/Y') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if ($historyPeriod?->isDraft())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Draft</span>
                                        @elseif ($historyPeriod?->isReview())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Review</span>
                                        @elseif ($historyPeriod?->isFinalized())
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Final</span>
                                        @else
                                            <span class="text-sm text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 text-right tabular-nums whitespace-nowrap">{{ number_format($history->base_salary, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-sm text-green-600 text-right tabular-nums whitespace-nowrap">+{{ number_format($history->total_allowances + $history->overtime_amount, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-sm text-red-600 text-right tabular-nums whitespace-nowrap">
                                        -{{ number_format(
                                            $history->total_deductions
                                            + $history->late_penalty
                                            + $history->absent_penalty
                                            + $history->early_out_penalty
                                            + ($history->short_work_penalty ?? 0)
                                            + ($history->over_break_penalty ?? 0)
                                            + $history->pph21_amount,
                                            0, ',', '.'
                                        ) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right tabular-nums whitespace-nowrap">{{ number_format($history->net_salary, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">Belum ada history gaji untuk karyawan ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end px-6 py-4 border-t border-gray-100 shrink-0 bg-gray-50">
                    <button type="button" wire:click="closeHistory" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
