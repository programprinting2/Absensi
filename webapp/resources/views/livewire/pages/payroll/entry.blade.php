<?php

use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Services\PayrollCalculationService;
use App\Support\Toast;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public PayrollPeriod $period;
    public PayrollEntry $entry;

    public bool $editing = false;
    public bool $showHistoryModal = false;

    public ?float $adjustBaseSalary = null;
    public ?float $adjustOvertimeHours = null;
    public ?string $adjustNotes = null;

    public string $newSide = 'credit';
    public string $newLabel = '';
    public string $newAmount = '';

    public ?string $editingDetailId = null;
    public string $editDetailAmount = '';

    public function mount(PayrollPeriod $period, PayrollEntry $entry): void
    {
        $this->period = $period;
        $this->entry = $entry;
        $this->syncAdjustFields();
    }

    public function toggleEditing(): void
    {
        if (! $this->period->isReview()) {
            return;
        }

        $this->editing = ! $this->editing;
        $this->resetValidation();
        $this->newSide = 'credit';
        $this->newLabel = '';
        $this->newAmount = '';
        $this->cancelEditAmount();
    }

    public function openHistory(): void
    {
        $this->showHistoryModal = true;
    }

    public function closeHistory(): void
    {
        $this->showHistoryModal = false;
    }

    public function startEditAmount(string $detailId, $current = null): void
    {
        if (! $this->editing || ! $this->period->isReview()) {
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
        if (! $this->editingDetailId || ! $this->period->isReview()) {
            return;
        }

        $data = $this->validate([
            'editDetailAmount' => ['required', 'numeric', 'min:1'],
        ], [], [
            'editDetailAmount' => 'nominal',
        ]);

        try {
            $this->entry = $service->updateDetailAmount(
                $this->entry,
                $this->editingDetailId,
                (float) $data['editDetailAmount'],
            );
            $this->syncAdjustFields();
            $this->cancelEditAmount();
            Toast::success('Nominal komponen diperbarui.', $this);
        } catch (\Throwable $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function recalculate(PayrollCalculationService $service): void
    {
        $this->entry = $service->recalculateEntry($this->entry);
        $this->period = $this->entry->period;
        $this->syncAdjustFields();
        $this->editing = false;
        Toast::success('Gaji dihitung ulang dari data master + absensi.', $this);
    }

    public function saveAdjustment(PayrollCalculationService $service): void
    {
        $this->entry = $service->applyAdjustment($this->entry, [
            'base_salary' => $this->adjustBaseSalary,
            'overtime_hours' => $this->adjustOvertimeHours,
            'notes' => $this->adjustNotes,
        ]);

        $this->syncAdjustFields();
        Toast::success('Penyesuaian disimpan dan total dihitung ulang.', $this);
    }

    public function addComponent(PayrollCalculationService $service): void
    {
        if (! $this->period->isReview()) {
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
            $this->entry = $service->addManualDetail(
                $this->entry,
                $data['newSide'],
                $data['newLabel'],
                (float) $data['newAmount'],
            );
            $this->syncAdjustFields();
            $this->newLabel = '';
            $this->newAmount = '';
            Toast::success('Komponen ditambahkan.', $this);
        } catch (\Throwable $e) {
            $this->addError('newLabel', $e->getMessage());
        }
    }

    public function deleteComponent(string $detailId, PayrollCalculationService $service): void
    {
        if (! $this->period->isReview()) {
            return;
        }

        try {
            $this->entry = $service->deleteDetail($this->entry, $detailId);
            $this->syncAdjustFields();
            Toast::success('Komponen dihapus.', $this);
        } catch (\Throwable $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    private function syncAdjustFields(): void
    {
        $this->adjustBaseSalary = (float) $this->entry->base_salary;
        $this->adjustOvertimeHours = (float) $this->entry->overtime_hours;
        $this->adjustNotes = $this->entry->notes;
    }

    public function with(): array
    {
        $this->entry->load(['employee', 'details', 'period']);
        $details = $this->entry->details->groupBy('category');

        $salaryHistory = $this->showHistoryModal
            ? PayrollEntry::query()
                ->with('period')
                ->where('employee_id', $this->entry->employee_id)
                ->whereHas('period')
                ->get()
                ->sortByDesc(fn (PayrollEntry $row) => optional($row->period)->period_start?->format('Y-m-d') ?? '')
                ->values()
            : collect();

        return compact('details', 'salaryHistory');
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
    <x-slot name="header">
        <div>
            <a href="{{ route('payroll.show', $period) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali ke {{ $period->label }}</a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detail Gaji — {{ $entry->employee->full_name }}</h2>
        </div>
    </x-slot>

    <div class="h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-6 overflow-y-auto">
            <div class="flex justify-end gap-2 shrink-0">
                <button
                    type="button"
                    wire:click="openHistory"
                    class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border bg-white text-gray-700 border-gray-300 hover:bg-gray-50 transition"
                >
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    History
                </button>
                @if ($period->isReview())
                    <button
                        type="button"
                        wire:click="toggleEditing"
                        @class([
                            'inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border transition',
                            'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700' => $editing,
                            'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' => ! $editing,
                        ])
                    >
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        {{ $editing ? 'Selesai Edit' : 'Edit' }}
                    </button>
                @endif
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Ringkasan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Gaji Pokok</span>
                            <span class="font-medium tabular-nums text-right">Rp {{ number_format($entry->base_salary, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Total Tunjangan</span>
                            <span class="font-medium text-green-600 tabular-nums text-right">+Rp {{ number_format($entry->total_allowances, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Lembur ({{ $entry->overtime_hours }} jam)</span>
                            <span class="font-medium text-green-600 tabular-nums text-right">+Rp {{ number_format($entry->overtime_amount, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Total Potongan</span>
                            <span class="font-medium text-red-600 tabular-nums text-right">-Rp {{ number_format($entry->total_deductions, 0, ',', '.') }}</span>
                        </div>
                        @php
                            $totalDenda = $entry->late_penalty + $entry->absent_penalty + $entry->early_out_penalty
                                + ($entry->short_work_penalty ?? 0) + ($entry->over_break_penalty ?? 0);
                        @endphp
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Total Denda</span>
                            <span class="font-medium text-red-600 tabular-nums text-right">-Rp {{ number_format($totalDenda, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">PPh 21</span>
                            <span class="font-medium text-red-600 tabular-nums text-right">-Rp {{ number_format($entry->pph21_amount, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-200 flex justify-between">
                    <span class="text-lg font-semibold text-gray-900">Gaji Bersih</span>
                    <span class="text-lg font-semibold text-gray-900 tabular-nums text-right">Rp {{ number_format($entry->net_salary, 0, ',', '.') }}</span>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">Ringkasan Absensi</h3>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 divide-x divide-y lg:divide-y-0 divide-gray-100">
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

            <div class="bg-white shadow-sm rounded-lg p-6">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Rincian</h3>
                    @if ($editing)
                        <span class="text-xs text-indigo-600 font-medium">Mode edit aktif</span>
                    @endif
                </div>

                <div class="space-y-4">
                    @if ($entry->details->isEmpty())
                        <p class="text-sm text-gray-500">Belum ada komponen rincian.</p>
                    @else
                        @foreach ($categoryLabels as $cat => $catLabel)
                            @continue(! isset($details[$cat]) || $details[$cat]->isEmpty())
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 uppercase mb-1">{{ $catLabel }}</h4>
                                @foreach ($details[$cat] as $detail)
                                    <div class="flex items-center justify-between gap-3 py-1.5 text-sm border-b border-gray-50 last:border-0">
                                        <span class="text-gray-700 min-w-0 truncate">{{ $detail->label }}</span>
                                        <div class="flex items-center gap-2 shrink-0">
                                            @if ($editing && $editingDetailId === $detail->id)
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
                                            @elseif ($editing)
                                                <button
                                                    type="button"
                                                    wire:click="startEditAmount('{{ $detail->id }}', '{{ $detail->amount }}')"
                                                    class="inline-flex items-center rounded px-1.5 py-0.5 -mx-1 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                    title="Klik untuk ubah nominal"
                                                >
                                                    <span @class([
                                                        'tabular-nums text-right font-medium',
                                                        'text-red-600' => in_array($cat, $debitCategories, true),
                                                        'text-green-600' => ! in_array($cat, $debitCategories, true),
                                                    ])>
                                                        {{ in_array($cat, $debitCategories, true) ? '-' : '+' }}Rp {{ number_format($detail->amount, 0, ',', '.') }}
                                                    </span>
                                                </button>
                                            @else
                                                <span @class([
                                                    'tabular-nums text-right font-medium',
                                                    'text-red-600' => in_array($cat, $debitCategories, true),
                                                    'text-green-600' => ! in_array($cat, $debitCategories, true),
                                                ])>
                                                    {{ in_array($cat, $debitCategories, true) ? '-' : '+' }}Rp {{ number_format($detail->amount, 0, ',', '.') }}
                                                </span>
                                            @endif
                                            @if ($editing && $editingDetailId !== $detail->id)
                                                <button
                                                    type="button"
                                                    wire:click="deleteComponent('{{ $detail->id }}')"
                                                    wire:confirm="Hapus komponen {{ $detail->label }}?"
                                                    class="inline-flex items-center justify-center p-1 rounded text-red-500 hover:bg-red-50 hover:text-red-700"
                                                    title="Hapus komponen"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7h6m-7 0h8m-1-3v-1a1 1 0 00-1-1h-4a1 1 0 00-1 1v1" />
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    @endif
                </div>

                @if ($editing)
                    <p class="mt-3 text-xs text-gray-500">Klik nominal untuk mengubah (termasuk Cash Bon variabel). Enter/blur = simpan, Esc = batal.</p>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <h4 class="text-sm font-semibold text-gray-900 mb-3">Tambah Komponen</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                            <div>
                                <x-input-label for="newSide" value="Jenis" />
                                <select wire:model="newSide" id="newSide" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    <option value="credit">Kredit (+)</option>
                                    <option value="debit">Debet (−)</option>
                                </select>
                                <x-input-error :messages="$errors->get('newSide')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="newLabel" value="Nama Komponen" />
                                <x-text-input wire:model="newLabel" id="newLabel" type="text" class="mt-1 block w-full" placeholder="Contoh: Bonus / Potongan lain" />
                                <x-input-error :messages="$errors->get('newLabel')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="newAmount" value="Nominal (Rp)" />
                                <x-text-input wire:model="newAmount" id="newAmount" type="number" min="1" step="1" class="mt-1 block w-full" placeholder="50000" />
                                <x-input-error :messages="$errors->get('newAmount')" class="mt-1" />
                            </div>
                        </div>
                        <div class="mt-3 flex justify-end">
                            <x-primary-button type="button" wire:click="addComponent">Tambah</x-primary-button>
                        </div>
                    </div>
                @endif
            </div>

            @if ($period->isReview())
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Adjustment</h3>
                    <div class="space-y-4">
                        <div>
                            <x-input-label for="adjustBaseSalary" value="Gaji Pokok Override" />
                            <x-currency-input id="adjustBaseSalary" wire="adjustBaseSalary" :value="$adjustBaseSalary" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <x-input-label for="adjustOvertimeHours" value="Jam Lembur Override" />
                            <x-text-input wire:model="adjustOvertimeHours" id="adjustOvertimeHours" type="number" step="0.5" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <x-input-label for="adjustNotes" value="Catatan" />
                            <textarea wire:model="adjustNotes" id="adjustNotes" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"></textarea>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <button type="button" wire:click="recalculate" wire:confirm="Reset & hitung ulang dari absensi & master? Komponen manual bisa hilang." class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                Reset
                            </button>
                            <x-primary-button type="button" wire:click="saveAdjustment">Simpan Adjustment</x-primary-button>
                        </div>
                    </div>
                </div>
            @endif

            @if ($entry->notes)
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-sm text-yellow-800"><strong>Catatan:</strong> {{ $entry->notes }}</p>
                </div>
            @endif
        </div>
    </div>

    @if ($showHistoryModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600/50 p-4"
             @keydown.escape.window="$wire.closeHistory()">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[90vh] flex flex-col overflow-hidden"
                 @click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">History Gaji</h3>
                        <p class="text-sm text-gray-500 mt-0.5">{{ $entry->employee->full_name }}</p>
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
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($salaryHistory as $history)
                                @php
                                    $historyPeriod = $history->period;
                                    $isCurrent = $history->id === $entry->id;
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
                                    <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                        @if ($historyPeriod && ! $isCurrent)
                                            <a href="{{ route('payroll.entry', [$historyPeriod, $history]) }}" class="text-blue-600 hover:text-blue-800 font-medium">Lihat</a>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500">Belum ada history gaji untuk karyawan ini.</td>
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
