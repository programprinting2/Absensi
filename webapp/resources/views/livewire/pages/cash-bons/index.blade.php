<?php

use App\Models\CashBon;
use App\Models\Employee;
use App\Services\CashBonService;
use App\Support\Toast;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url(except: 'active', history: false)]
    public string $status = 'active';

    public bool $showForm = false;
    public ?string $expandedId = null;

    public string $employee_id = '';
    public string $amount = '';
    public string $installment_count = '';
    public string $disbursed_at = '';
    public string $notes = '';

    public function openForm(): void
    {
        $this->resetValidation();
        $this->employee_id = '';
        $this->amount = '';
        $this->installment_count = '';
        $this->disbursed_at = '';
        $this->notes = '';
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function getMonthlyInstallmentProperty(): string
    {
        $amount = (float) $this->amount;
        $count = (int) $this->installment_count;

        if ($amount <= 0 || $count <= 0) {
            return '';
        }

        return (string) (int) floor($amount / $count);
    }

    public function toggleExpand(string $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function save(CashBonService $cashBons): void
    {
        $data = $this->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'min:1000'],
            'installment_count' => ['required', 'integer', 'min:1', 'max:60'],
            'disbursed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        $cashBon = $cashBons->create(
            $employee,
            (float) $data['amount'],
            (int) $data['installment_count'],
            $data['disbursed_at'],
            $data['notes'] ?: null,
        );

        $this->showForm = false;
        $this->expandedId = $cashBon->id;
        Toast::success('Cash bon berhasil dicatat.', $this);
    }

    public function cancelBon(string $id, CashBonService $cashBons): void
    {
        $cashBon = CashBon::findOrFail($id);

        try {
            $cashBons->cancel($cashBon);
            Toast::success('Cash bon dibatalkan.', $this);
        } catch (\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function with(CashBonService $cashBons): array
    {
        $payload = $cashBons->indexPayload($this->status);

        return [
            'items' => $payload['items'],
            'activeRemaining' => $payload['active_remaining'],
            'activeCount' => $payload['active_count'],
            'employees' => Employee::query()
                ->where('is_active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_code']),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Cash Bon</h2>
    </x-slot>

    <div class="h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-4 overflow-y-auto">
            <div class="flex justify-end shrink-0">
                <button type="button" wire:click="openForm"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-gray-700">
                    + Cash Bon Baru
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 shrink-0">
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm font-medium text-gray-500">Cash bon berjalan</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $activeCount }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm font-medium text-gray-500">Total sisa cicilan</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900 text-right tabular-nums">Rp {{ number_format($activeRemaining, 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 shrink-0">
                @foreach (['active' => 'Berjalan', 'paid' => 'Lunas', 'cancelled' => 'Dibatalkan', 'all' => 'Semua'] as $key => $label)
                    <button type="button" wire:click="$set('status', '{{ $key }}')"
                            class="px-3 py-1.5 text-sm rounded-md border transition {{ $status === $key ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden flex-1 min-h-0 flex flex-col">
                <div class="overflow-auto flex-1">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-3">Karyawan</th>
                                <th class="px-6 py-3 text-right">Nominal</th>
                                <th class="px-6 py-3 text-right">Cicilan</th>
                                <th class="px-6 py-3 text-right">Sisa</th>
                                <th class="px-6 py-3">Tanggal Cair</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($items as $item)
                                <tr class="hover:bg-gray-50" wire:key="cashbon-{{ $item['id'] }}">
                                    <td class="px-6 py-3">
                                        <p class="font-medium text-gray-900">{{ $item['employee']['full_name'] ?? '-' }}</p>
                                        <p class="text-xs text-gray-500">ID {{ $item['employee']['employee_code'] ?? '-' }}</p>
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap font-medium text-gray-900 text-right tabular-nums">{{ $item['amount_label'] }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-700 text-right tabular-nums">{{ $item['installment_count'] }}x · {{ $item['installment_amount_label'] }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-700 text-right tabular-nums">{{ $item['remaining_amount_label'] }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-500">{{ $item['disbursed_at_label'] }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        @php
                                            $badge = match ($item['status']) {
                                                'active' => 'bg-blue-50 text-blue-700',
                                                'paid' => 'bg-green-50 text-green-700',
                                                default => 'bg-gray-100 text-gray-600',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">{{ $item['status_label'] }}</span>
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-right space-x-2">
                                        <button type="button" wire:click="toggleExpand('{{ $item['id'] }}')" class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold">
                                            {{ $expandedId === $item['id'] ? 'Tutup' : 'Cicilan' }}
                                        </button>
                                        @if ($item['status'] === 'active')
                                            <button type="button" wire:click="cancelBon('{{ $item['id'] }}')" wire:confirm="Batalkan cash bon ini? Cicilan yang belum dipotong akan dibatalkan." class="text-red-600 hover:text-red-800 text-xs font-semibold">
                                                Batal
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @if ($expandedId === $item['id'])
                                    <tr class="bg-gray-50" wire:key="cashbon-detail-{{ $item['id'] }}">
                                        <td colspan="7" class="px-6 py-4">
                                            <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Riwayat cicilan</p>
                                            @if (!empty($item['notes']))
                                                <p class="text-xs text-gray-500 mb-3">Catatan: {{ $item['notes'] }}</p>
                                            @endif
                                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                @foreach ($item['installments'] as $inst)
                                                    <div class="rounded-md border border-gray-200 bg-white px-3 py-2">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <p class="text-sm font-medium text-gray-900">{{ $inst['label'] }}</p>
                                                            <p class="text-sm font-semibold text-gray-900 text-right tabular-nums">{{ $inst['amount_label'] }}</p>
                                                        </div>
                                                        <p class="text-xs text-gray-500 mt-1">
                                                            {{ $inst['status_label'] }}
                                                            @if ($inst['period_label']) · Periode {{ $inst['period_label'] }} @endif
                                                            @if ($inst['paid_at_label']) · {{ $inst['paid_at_label'] }} @endif
                                                        </p>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-gray-500">Belum ada data cash bon untuk filter ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4">
            <div class="relative w-full max-w-lg bg-white rounded-xl shadow-2xl overflow-hidden" wire:click.stop>
                <div class="flex items-center justify-between px-6 py-3.5 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900">Cash Bon Baru</h3>
                    <button type="button" wire:click="closeForm" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Karyawan</label>
                        <select wire:model="employee_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Pilih karyawan…</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }} ({{ $employee->employee_code }})</option>
                            @endforeach
                        </select>
                        @error('employee_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nominal (Rp)</label>
                            <x-currency-input wire="amount" :value="$amount !== '' ? $amount : 0" class="mt-1 block w-full text-sm" placeholder="0" />
                            @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Cicilan (bulan)</label>
                            <input type="number" min="1" max="60" wire:model.live.debounce.300ms="installment_count" placeholder="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                            @error('installment_count') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jumlah cicilan per bulan (Rp)</label>
                        <input type="text" value="{{ $this->monthlyInstallment !== '' ? number_format((float) $this->monthlyInstallment, 0, ',', '.') : '' }}" placeholder="Otomatis" readonly disabled
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm text-right tabular-nums bg-gray-50 text-gray-700 cursor-not-allowed" />
                        <p class="mt-1 text-xs text-gray-500">Dihitung otomatis dari nominal ÷ jumlah bulan.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tanggal Cair</label>
                        <input type="date" wire:model="disbursed_at" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        @error('disbursed_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Catatan</label>
                        <input type="text" maxlength="500" wire:model="notes" placeholder="Opsional" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <p class="text-xs text-gray-500">Cicilan dipotong otomatis tiap periode gaji hingga lunas.</p>
                </div>
                <div class="flex justify-end gap-3 px-6 py-3.5 border-t border-gray-200 bg-gray-50">
                    <button type="button" wire:click="closeForm" class="px-4 py-2 text-xs font-semibold uppercase border border-gray-300 rounded-md bg-white text-gray-700">Batal</button>
                    <button type="button" wire:click="save" class="px-4 py-2 text-xs font-semibold uppercase rounded-md bg-gray-800 text-white hover:bg-gray-700">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
