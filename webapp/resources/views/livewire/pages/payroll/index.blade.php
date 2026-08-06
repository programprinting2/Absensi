<?php

use App\Models\PayrollPeriod;
use App\Services\PayrollCalculationService;
use App\Services\PayrollPeriodService;
use App\Support\Toast;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public int $newMonth;
    public int $newYear;
    public bool $showCreateModal = false;

    public function mount(): void
    {
        $this->newMonth = (int) now()->format('m');
        $this->newYear = (int) now()->format('Y');
    }

    public function openCreateModal(): void
    {
        $this->newMonth = (int) now()->format('m');
        $this->newYear = (int) now()->format('Y');
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
    }

    public function createPeriod(PayrollPeriodService $service): void
    {
        $service->createPeriod($this->newMonth, $this->newYear);
        $this->showCreateModal = false;
    }

    public function generate(string $periodId, PayrollCalculationService $service): void
    {
        $period = PayrollPeriod::findOrFail($periodId);
        if (! $period->isDraft() && ! $period->isReview()) {
            return;
        }

        $result = $service->generateForPeriod($period);

        $msg = "Generate selesai: {$result['employees']} karyawan";
        if ($result['without_salary'] > 0) {
            $msg .= " ({$result['without_salary']} tanpa gaji pokok)";
        }
        $msg .= '.';

        Toast::success($msg, $this);

        $this->redirect(route('payroll.show', $period), navigate: true);
    }

    public function finalize(string $periodId, PayrollPeriodService $service): void
    {
        $period = PayrollPeriod::findOrFail($periodId);
        if (! $period->isReview()) {
            return;
        }

        $service->finalize($period, auth()->user());
        Toast::success('Periode berhasil difinalisasi.', $this);
    }

    public function deletePeriod(string $periodId): void
    {
        $period = PayrollPeriod::findOrFail($periodId);
        if ($period->isFinalized()) {
            return;
        }

        $period->delete();
        Toast::success('Periode dihapus.', $this);
    }

    public function with(): array
    {
        return [
            'periods' => PayrollPeriod::query()
                ->withCount('entries')
                ->orderByDesc('period_start')
                ->get(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Penggajian</h2>
    </x-slot>

    <div class="h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-4">
            <div class="flex justify-end gap-2 shrink-0">
                <a href="{{ route('payroll.settings') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Pengaturan
                </a>
                <button type="button" wire:click="openCreateModal" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-gray-700">
                    + Buat Periode
                </button>
            </div>

            <div class="bg-white overflow-hidden shadow-sm rounded-lg flex-1 flex flex-col min-h-0">
                <div class="overflow-auto flex-1">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Periode</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Karyawan</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($periods as $period)
                            <tr>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $period->label }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $period->period_start->format('d/m/Y') }} — {{ $period->period_end->format('d/m/Y') }}</td>
                                <td class="px-6 py-4">
                                    @if ($period->isDraft())
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Draft</span>
                                    @elseif ($period->isReview())
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Review</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Final</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $period->entries_count }}</td>
                                <td class="px-6 py-4 text-right text-sm space-x-2">
                                    @if ($period->isDraft())
                                        <button type="button" wire:click="generate('{{ $period->id }}')" wire:confirm="Generate gaji untuk semua karyawan aktif?" wire:loading.attr="disabled" class="text-blue-600 hover:text-blue-800 font-medium">
                                            <span wire:loading.remove wire:target="generate('{{ $period->id }}')">Generate</span>
                                            <span wire:loading wire:target="generate('{{ $period->id }}')">Menghitung...</span>
                                        </button>
                                        <button type="button" wire:click="deletePeriod('{{ $period->id }}')" wire:confirm="Hapus periode ini?" class="text-red-600 hover:text-red-800 font-medium">Hapus</button>
                                    @elseif ($period->isReview())
                                        <a href="{{ route('payroll.show', $period) }}" class="text-blue-600 hover:text-blue-800 font-medium">Review</a>
                                        <button type="button" wire:click="generate('{{ $period->id }}')" wire:confirm="Generate ulang gaji periode ini? Penyesuaian manual akan diganti." class="text-indigo-600 hover:text-indigo-800 font-medium">Generate Ulang</button>
                                        <button type="button" wire:click="finalize('{{ $period->id }}')" wire:confirm="Finalisasi periode ini? Data tidak bisa diubah setelah finalisasi." class="text-green-600 hover:text-green-800 font-medium">Finalisasi</button>
                                    @else
                                        <a href="{{ route('payroll.show', $period) }}" class="text-blue-600 hover:text-blue-800 font-medium">Lihat</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">Belum ada periode penggajian.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>

    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600/50 p-4">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Buat Periode Baru</h3>
                    <button type="button" wire:click="closeCreateModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="space-y-4">
                    <div>
                        <x-input-label for="newMonth" value="Bulan" />
                        <select wire:model="newMonth" id="newMonth" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}">{{ \Carbon\Carbon::create(null, $m)->locale('id')->translatedFormat('F') }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <x-input-label for="newYear" value="Tahun" />
                        <x-text-input wire:model="newYear" id="newYear" type="number" min="2020" max="2099" class="mt-1 block w-full" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="closeCreateModal" class="px-4 py-2 text-sm text-gray-700 hover:text-gray-900">Batal</button>
                    <x-primary-button type="button" wire:click="createPeriod">Buat</x-primary-button>
                </div>
            </div>
        </div>
    @endif
</div>
