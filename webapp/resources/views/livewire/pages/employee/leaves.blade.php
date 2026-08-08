<?php

use App\Models\EmployeeLeave;
use App\Services\LeaveService;
use App\Support\AppTimezone;
use App\Support\Toast;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showForm = false;

    public string $leave_type = 'tahunan';

    public string $start_date = '';

    public string $end_date = '';

    public string $reason = '';

    public function openForm(): void
    {
        $this->resetValidation();
        $this->leave_type = EmployeeLeave::TYPE_TAHUNAN;
        $this->start_date = now()->toDateString();
        $this->end_date = now()->toDateString();
        $this->reason = '';
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function save(LeaveService $leaves): void
    {
        $employee = auth()->user()?->employee;
        if (! $employee) {
            Toast::error('Akun belum terhubung ke data karyawan.', $this);

            return;
        }

        $data = $this->validate([
            'leave_type' => ['required', 'in:'.implode(',', array_keys(EmployeeLeave::typeOptions()))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $leaves->create(
                $employee,
                $data['leave_type'],
                $data['start_date'],
                $data['end_date'],
                $data['reason'] ?: null,
                EmployeeLeave::STATUS_PENDING,
            );

            $this->showForm = false;
            Toast::success('Pengajuan cuti terkirim. Menunggu persetujuan.', $this);
        } catch (\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function cancel(string $id, LeaveService $leaves): void
    {
        $employee = auth()->user()?->employee;
        $leave = EmployeeLeave::query()
            ->where('id', $id)
            ->when($employee, fn ($q) => $q->where('employee_id', $employee->id))
            ->firstOrFail();

        if ($leave->status !== EmployeeLeave::STATUS_PENDING) {
            Toast::error('Hanya pengajuan menunggu yang bisa dibatalkan.', $this);

            return;
        }

        try {
            $leaves->cancel($leave);
            Toast::success('Pengajuan cuti dibatalkan.', $this);
        } catch (\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function with(LeaveService $leaves): array
    {
        $employee = auth()->user()?->employee;
        $payload = $employee
            ? $leaves->indexPayload('all', $employee->id)
            : ['items' => [], 'counts' => []];

        $year = (int) AppTimezone::nowDisplay()->year;
        $quota = $employee ? $leaves->quotaSummary($employee, $year) : null;

        return [
            'employee' => $employee,
            'items' => $payload['items'],
            'typeOptions' => EmployeeLeave::typeOptions(),
            'quota' => $quota,
        ];
    }
}; ?>

<div class="h-[calc(100vh-8rem)] flex flex-col">
    <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-4 overflow-y-auto">
        @unless ($employee)
            <div class="bg-white shadow-sm rounded-lg px-6 py-10 text-center text-sm text-gray-500">
                Akun ini belum terhubung ke data karyawan. Hubungi admin.
            </div>
        @else
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Cuti Saya</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Ajukan cuti dan lihat status persetujuan.</p>
                </div>
                <button type="button" wire:click="openForm" class="inline-flex items-center px-3 py-2 rounded-md text-sm font-semibold text-white bg-[#f7340d] hover:bg-[#d92c0a]">
                    + Ajukan Cuti
                </button>
            </div>

            @if ($quota)
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <p class="text-[10px] uppercase tracking-wider text-gray-500">Jatah {{ $quota['year'] }}</p>
                        <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-900">{{ $quota['entitlement'] }} hari</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <p class="text-[10px] uppercase tracking-wider text-gray-500">Terpakai</p>
                        <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-800">{{ $quota['used'] }}</p>
                    </div>
                    <div class="rounded-lg border border-sky-100 bg-sky-50 px-4 py-3">
                        <p class="text-[10px] uppercase tracking-wider text-sky-700">Bisa diajukan</p>
                        <p class="mt-0.5 text-lg font-semibold tabular-nums text-sky-900">{{ $quota['available'] }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <p class="text-[10px] uppercase tracking-wider text-gray-500">Hangus / Diuangkan</p>
                        <p class="mt-0.5 text-lg font-semibold tabular-nums text-gray-700">{{ $quota['expired'] + $quota['cashed'] }}</p>
                    </div>
                </div>
            @endif

            @if ($showForm)
                <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm">
                    <h4 class="text-sm font-semibold text-gray-900 mb-4">Pengajuan cuti</h4>
                    <form wire:submit="save" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Jenis cuti</label>
                            <select wire:model="leave_type" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($typeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('leave_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Mulai</label>
                            <input type="date" wire:model="start_date" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Selesai</label>
                            <input type="date" wire:model="end_date" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('end_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Keterangan</label>
                            <textarea wire:model="reason" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Alasan cuti (opsional)"></textarea>
                            @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2 flex justify-end gap-2">
                            <button type="button" wire:click="closeForm" class="px-3 py-2 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" class="px-3 py-2 text-sm rounded-md font-semibold text-white bg-[#f7340d] hover:bg-[#d92c0a]">Kirim pengajuan</button>
                        </div>
                    </form>
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-3">Jenis</th>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3 text-right">Hari</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Keterangan</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($items as $item)
                                @php
                                    $badge = match ($item['status']) {
                                        'approved' => 'bg-green-50 text-green-700 border-green-100',
                                        'rejected' => 'bg-red-50 text-red-700 border-red-100',
                                        'cancelled' => 'bg-gray-100 text-gray-600 border-gray-200',
                                        default => 'bg-amber-50 text-amber-800 border-amber-100',
                                    };
                                @endphp
                                <tr wire:key="my-leave-{{ $item['id'] }}" class="align-top hover:bg-gray-50/80">
                                    <td class="px-4 py-3 text-gray-800">{{ $item['leave_type_label'] }}</td>
                                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                                        {{ $item['start_date_label'] }}
                                        @if ($item['start_date'] !== $item['end_date'])
                                            <span class="text-gray-400">→</span> {{ $item['end_date_label'] }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $item['days_count'] }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ $badge }}">
                                            {{ $item['status_label'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $item['reason'] ?: '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($item['status'] === 'pending')
                                            <button type="button" wire:click="cancel('{{ $item['id'] }}')" wire:confirm="Batalkan pengajuan cuti ini?" class="text-sm font-medium text-red-600 hover:text-red-700">Batalkan</button>
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-gray-500">Belum ada pengajuan cuti.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endunless
    </div>
</div>
