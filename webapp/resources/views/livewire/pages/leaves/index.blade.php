<?php

use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveGrant;
use App\Models\PayrollSetting;
use App\Services\LeaveService;
use App\Support\AppTimezone;
use App\Support\Toast;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url(except: 'pending', history: false)]
    public string $status = 'pending';

    #[Url(as: 'tab', except: 'pengajuan', history: false)]
    public string $tab = 'pengajuan';

    public bool $showForm = false;

    public string $employee_id = '';

    public string $leave_type = 'tahunan';

    public string $start_date = '';

    public string $end_date = '';

    public string $reason = '';

    public bool $auto_approve = true;

    public int $quotaYear;

    public bool $showJatahForm = false;

    public string $jatah_employee_id = '';

    public string $jatah_start_date = '';

    public string $jatah_end_date = '';

    public int $jatah_days = 12;

    public string $jatah_notes = '';

    public string $history_period = 'month';

    public string $history_employee_id = '';

    public int $history_month;

    public int $history_year;

    public function mount(): void
    {
        $now = AppTimezone::nowDisplay();
        $this->quotaYear = (int) $now->year;
        $this->history_month = (int) $now->month;
        $this->history_year = (int) $now->year;
    }

    public function resetHistoryFilters(): void
    {
        $now = AppTimezone::nowDisplay();
        $this->history_period = 'month';
        $this->history_employee_id = '';
        $this->history_month = (int) $now->month;
        $this->history_year = (int) $now->year;
    }

    public function updatedTab(string $value): void
    {
        if ($value !== 'jatah') {
            $this->closeJatahForm();
        }
    }

    public function openJatahForm(): void
    {
        $this->resetValidation();
        $this->jatah_employee_id = '';
        $year = $this->quotaYear;
        $this->jatah_start_date = sprintf('%d-01-01', $year);
        $this->jatah_end_date = sprintf('%d-12-31', $year);
        $this->jatah_days = max(1, (int) (PayrollSetting::active()->annual_leave_days ?? 12));
        $this->jatah_notes = '';
        $this->showJatahForm = true;
        $this->tab = 'jatah';
    }

    public function closeJatahForm(): void
    {
        $this->showJatahForm = false;
        $this->resetValidation();
    }

    public function syncJatahDaysFromDates(): void
    {
        if (! filled($this->jatah_start_date) || ! filled($this->jatah_end_date)) {
            return;
        }

        if ($this->jatah_end_date < $this->jatah_start_date) {
            $this->jatah_days = 0;

            return;
        }

        $this->jatah_days = app(LeaveService::class)->countLeaveDays(
            $this->jatah_start_date,
            $this->jatah_end_date,
        );
    }

    public function saveJatah(LeaveService $leaves): void
    {
        $data = $this->validate([
            'jatah_employee_id' => ['required', 'exists:employees,id'],
            'jatah_start_date' => ['required', 'date'],
            'jatah_end_date' => ['required', 'date', 'after_or_equal:jatah_start_date'],
            'jatah_days' => ['required', 'integer', 'min:1', 'max:366'],
            'jatah_notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $leaves->addEntitlementFromPeriod(
                Employee::findOrFail($data['jatah_employee_id']),
                $data['jatah_start_date'],
                $data['jatah_end_date'],
                (int) $data['jatah_days'],
                $data['jatah_notes'] ?: null,
            );

            $this->quotaYear = (int) substr($data['jatah_start_date'], 0, 4);
            $this->showJatahForm = false;
            Toast::success("Jatah cuti ditambah {$result['added']} hari dan dicatat di history.", $this);
        } catch (\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function deleteGrant(string $id, LeaveService $leaves): void
    {
        $this->authorize('deleteLeaveGrant');

        try {
            $leaves->deleteGrant(EmployeeLeaveGrant::findOrFail($id));
            Toast::success('History jatah dihapus dan jatah dikurangi.', $this);
        } catch (\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function openForm(): void
    {
        $this->resetValidation();
        $this->employee_id = '';
        $this->leave_type = EmployeeLeave::TYPE_TAHUNAN;
        $this->start_date = now()->toDateString();
        $this->end_date = now()->toDateString();
        $this->reason = '';
        $this->auto_approve = true;
        $this->showForm = true;
        $this->tab = 'pengajuan';
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function save(LeaveService $leaves): void
    {
        $data = $this->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type' => ['required', 'in:'.implode(',', array_keys(EmployeeLeave::typeOptions()))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'auto_approve' => ['boolean'],
        ]);

        try {
            $leave = $leaves->create(
                Employee::findOrFail($data['employee_id']),
                $data['leave_type'],
                $data['start_date'],
                $data['end_date'],
                $data['reason'] ?: null,
                ! empty($data['auto_approve']) ? EmployeeLeave::STATUS_APPROVED : EmployeeLeave::STATUS_PENDING,
            );

            $this->showForm = false;
            $this->status = $leave->status;
            Toast::success(
                $leave->status === EmployeeLeave::STATUS_APPROVED
                    ? 'Cuti berhasil dicatat & disetujui.'
                    : 'Pengajuan cuti berhasil disimpan.',
                $this,
            );
        } catch (\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function approve(string $id, LeaveService $leaves): void
    {
        $this->authorize('approveLeave');

        try {
            $leaves->approve(EmployeeLeave::findOrFail($id));
            Toast::success('Cuti disetujui.', $this);
        } catch (\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function reject(string $id, LeaveService $leaves): void
    {
        $this->authorize('approveLeave');

        try {
            $leaves->reject(EmployeeLeave::findOrFail($id));
            Toast::success('Cuti ditolak.', $this);
        } catch (\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function cancel(string $id, LeaveService $leaves): void
    {
        $this->authorize('approveLeave');

        try {
            $leaves->cancel(EmployeeLeave::findOrFail($id));
            Toast::success('Cuti dibatalkan.', $this);
        } catch (\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function expireBalance(string $employeeId, LeaveService $leaves): void
    {
        $this->authorize('manageLeaveQuotaMoney');

        try {
            $employee = Employee::findOrFail($employeeId);
            $before = $leaves->quotaSummary($employee, $this->quotaYear);
            $leaves->expireRemaining($employee, $this->quotaYear);
            Toast::success("Sisa cuti {$this->quotaYear} digantungkan ({$before['remaining']} hari hangus).", $this);
        } catch (\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function cashOutBalance(string $employeeId, LeaveService $leaves): void
    {
        $this->authorize('manageLeaveQuotaMoney');

        try {
            $employee = Employee::findOrFail($employeeId);
            $before = $leaves->quotaSummary($employee, $this->quotaYear);
            $balance = $leaves->cashOutRemaining($employee, $this->quotaYear);
            $amount = (float) $balance->cash_amount;
            Toast::success(
                "Sisa {$before['remaining']} hari cuti {$this->quotaYear} diuangkan: Rp ".number_format($amount, 0, ',', '.'),
                $this,
            );
        } catch (\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function with(LeaveService $leaves): array
    {
        $payload = $leaves->indexPayload($this->status);
        $quota = null;
        $formOverlaps = [];
        $previewDays = 0;

        if ($this->showForm && filled($this->employee_id) && filled($this->start_date) && filled($this->end_date)) {
            try {
                $previewDays = $leaves->countLeaveDays($this->start_date, $this->end_date);
            } catch (\Throwable) {
                $previewDays = 0;
            }

            $formOverlaps = $leaves->overlappingPeers(
                $this->start_date,
                $this->end_date,
                $this->employee_id,
            );

            if ($this->leave_type === EmployeeLeave::TYPE_TAHUNAN) {
                $year = (int) substr($this->start_date, 0, 4) ?: $this->quotaYear;
                $quota = $leaves->quotaSummary($this->employee_id, $year);
            }
        }

        $overlapItems = collect($payload['items'])->filter(fn ($i) => ! empty($i['has_overlap']))->values()->all();

        return [
            'items' => $payload['items'],
            'counts' => $payload['counts'],
            'employees' => Employee::query()
                ->where('is_active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_code']),
            'typeOptions' => EmployeeLeave::typeOptions(),
            'quota' => $quota,
            'formOverlaps' => $formOverlaps,
            'previewDays' => $previewDays,
            'overlapItems' => $overlapItems,
            'balanceRows' => $this->tab === 'jatah' ? $leaves->balanceRows($this->quotaYear) : [],
            'quotaHistory' => $this->tab === 'history'
                ? $leaves->quotaHistory(
                    filled($this->history_employee_id) ? $this->history_employee_id : null,
                    $this->history_period,
                    $this->history_year,
                    $this->history_month,
                )
                : [],
            'historyYearOptions' => range((int) AppTimezone::nowDisplay()->year + 1, (int) AppTimezone::nowDisplay()->year - 5),
            'monthLabels' => ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
        ];
    }
}; ?>

<div class="h-[calc(100vh-8rem)] flex flex-col">
    <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-4 overflow-y-auto">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-900">Cuti Karyawan</h3>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="openJatahForm" class="inline-flex items-center px-3 py-2 rounded-md text-sm font-semibold text-[#f7340d] bg-white border border-[#f7340d] hover:bg-orange-50">
                    + Jatah Cuti
                </button>
                <button type="button" wire:click="openForm" class="inline-flex items-center px-3 py-2 rounded-md text-sm font-semibold text-white bg-[#f7340d] hover:bg-[#d92c0a]">
                    + Catat Cuti
                </button>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-0">
            <button type="button" wire:click="$set('tab', 'pengajuan')"
                @class(['px-4 py-2 text-sm font-medium border-b-2 -mb-px', $tab === 'pengajuan' ? 'border-[#f7340d] text-[#f7340d]' : 'border-transparent text-gray-500 hover:text-gray-700'])>
                Pengajuan
            </button>
            <button type="button" wire:click="$set('tab', 'bentrok')"
                @class(['px-4 py-2 text-sm font-medium border-b-2 -mb-px', $tab === 'bentrok' ? 'border-[#f7340d] text-[#f7340d]' : 'border-transparent text-gray-500 hover:text-gray-700'])>
                Bentrok jadwal
                @if (count($overlapItems) > 0)
                    <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full text-xs bg-amber-100 text-amber-800">{{ count($overlapItems) }}</span>
                @endif
            </button>
            <button type="button" wire:click="$set('tab', 'jatah')"
                @class(['px-4 py-2 text-sm font-medium border-b-2 -mb-px', $tab === 'jatah' ? 'border-[#f7340d] text-[#f7340d]' : 'border-transparent text-gray-500 hover:text-gray-700'])>
                Jatah cuti
            </button>
            <button type="button" wire:click="$set('tab', 'history')"
                @class(['px-4 py-2 text-sm font-medium border-b-2 -mb-px', $tab === 'history' ? 'border-[#f7340d] text-[#f7340d]' : 'border-transparent text-gray-500 hover:text-gray-700'])>
                History cuti
            </button>
        </div>

        @if ($tab === 'jatah')
            <div class="bg-white shadow-sm rounded-lg border border-gray-100 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h4 class="text-sm font-semibold text-gray-900">Sisa cuti</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-3">Karyawan</th>
                                <th class="px-4 py-3 text-right">Jatah</th>
                                <th class="px-4 py-3 text-right">Terpakai</th>
                                <th class="px-4 py-3 text-right">Hangus</th>
                                <th class="px-4 py-3 text-right">Diuangkan</th>
                                <th class="px-4 py-3 text-right">Sisa</th>
                                <th class="px-4 py-3 text-right">Nilai uang</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($balanceRows as $row)
                                <tr wire:key="bal-{{ $row['employee_id'] }}-{{ $row['year'] }}" class="hover:bg-gray-50/80">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $row['employee_name'] }}</div>
                                        <div class="text-xs text-gray-500">{{ $row['employee_code'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-900">{{ $row['entitlement'] }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $row['used'] }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-500">{{ $row['expired'] }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-500">{{ $row['cashed'] }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums font-semibold {{ $row['remaining'] > 0 ? 'text-green-700' : 'text-gray-400' }}">{{ $row['remaining'] }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                                        @if ($row['cashed'] > 0)
                                            Rp {{ number_format($row['cash_amount'], 0, ',', '.') }}
                                        @elseif ($row['remaining'] > 0 && $row['cash_rate'] > 0)
                                            <span class="text-xs text-gray-400">est.</span>
                                            Rp {{ number_format($row['cash_preview'], 0, ',', '.') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        @if ($row['status'] === 'open' && $row['remaining'] > 0)
                                            @can('manageLeaveQuotaMoney')
                                            <button type="button" wire:click="cashOutBalance('{{ $row['employee_id'] }}')" wire:confirm="Uangkan sisa {{ $row['remaining'] }} hari cuti {{ $row['year'] }} untuk {{ $row['employee_name'] }}?" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Uangkan</button>
                                            <button type="button" wire:click="expireBalance('{{ $row['employee_id'] }}')" wire:confirm="Hanguskan sisa {{ $row['remaining'] }} hari cuti {{ $row['year'] }} untuk {{ $row['employee_name'] }}? Tidak bisa dibatalkan." class="ml-2 text-sm font-medium text-red-600 hover:text-red-700">Hangus</button>
                                            @else
                                            <span class="text-xs text-gray-400">—</span>
                                            @endcan
                                        @else
                                            <span class="text-xs text-gray-400">{{ $row['status'] === 'closed' ? 'Ditutup' : '—' }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif ($tab === 'history')
            <div class="bg-white shadow-sm rounded-lg border border-gray-100 p-4 shrink-0">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="w-36">
                        <label class="block text-sm font-medium text-gray-700">Periode</label>
                        <select wire:model.live="history_period" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="month">Bulanan</option>
                            <option value="year">Tahunan</option>
                        </select>
                    </div>

                    <div class="w-56">
                        <label class="block text-sm font-medium text-gray-700">Karyawan</label>
                        <select wire:model.live="history_employee_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Semua Karyawan</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($history_period === 'month')
                        <div class="w-40">
                            <label class="block text-sm font-medium text-gray-700">Bulan</label>
                            <select wire:model.live="history_month" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($monthLabels as $index => $label)
                                    <option value="{{ $index + 1 }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="w-28">
                        <label class="block text-sm font-medium text-gray-700">Tahun</label>
                        <select wire:model.live="history_year" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($historyYearOptions as $y)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="button" wire:click="resetHistoryFilters"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Reset
                    </button>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg border border-gray-100 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h4 class="text-sm font-semibold text-gray-900">History cuti</h4>
                    <p class="text-xs text-gray-500 mt-0.5">Penambahan jatah dan pengambilan cuti tahunan.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-3">Waktu</th>
                                <th class="px-4 py-3">Karyawan</th>
                                <th class="px-4 py-3">Jenis</th>
                                <th class="px-4 py-3">Periode</th>
                                <th class="px-4 py-3 text-right">Hari</th>
                                <th class="px-4 py-3">Keterangan</th>
                                <th class="px-4 py-3">Oleh</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($quotaHistory as $entry)
                                <tr wire:key="qh-{{ $entry['kind'] }}-{{ $entry['id'] }}" class="hover:bg-gray-50/80">
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-600 text-xs">{{ $entry['created_at_label'] }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $entry['employee']['full_name'] ?? '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ $entry['employee']['employee_code'] ?? '' }}</div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if ($entry['direction'] === 'in')
                                            <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">{{ $entry['kind_label'] }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">{{ $entry['kind_label'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-700">
                                        {{ $entry['start_date_label'] }}
                                        @if (($entry['start_date'] ?? null) !== ($entry['end_date'] ?? null))
                                            → {{ $entry['end_date_label'] }}
                                        @endif
                                    </td>
                                    <td @class([
                                        'px-4 py-3 text-right tabular-nums font-semibold',
                                        'text-green-700' => $entry['direction'] === 'in',
                                        'text-red-700' => $entry['direction'] === 'out',
                                    ])>
                                        {{ $entry['direction'] === 'in' ? '+' : '-' }}{{ $entry['days'] }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $entry['notes'] ?: '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ $entry['created_by'] ?: '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($entry['kind'] === 'grant')
                                            @can('deleteLeaveGrant')
                                            <button type="button" wire:click="deleteGrant('{{ $entry['id'] }}')" wire:confirm="Hapus penambahan ini dan kurangi jatah {{ $entry['days'] }} hari?" class="text-sm font-medium text-red-600 hover:text-red-700">Hapus</button>
                                            @else
                                            <span class="text-xs text-gray-400">—</span>
                                            @endcan
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-gray-500">Belum ada history cuti untuk filter ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif ($tab === 'bentrok')
            <div class="bg-amber-50 border border-amber-100 rounded-lg px-4 py-3 text-sm text-amber-900">
                Daftar cuti (menunggu/disetujui pada filter aktif) yang tanggalnya berpotongan dengan karyawan lain.
            </div>
            <div class="bg-white shadow-sm rounded-lg border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-3">Karyawan</th>
                                <th class="px-4 py-3">Tanggal cuti</th>
                                <th class="px-4 py-3">Bentrok dengan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($overlapItems as $item)
                                <tr wire:key="ov-{{ $item['id'] }}" class="align-top">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $item['employee']['full_name'] }}</div>
                                        <div class="text-xs text-gray-500">{{ $item['leave_type_label'] }} · {{ $item['status_label'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-700">
                                        {{ $item['start_date_label'] }}
                                        @if ($item['start_date'] !== $item['end_date'])
                                            → {{ $item['end_date_label'] }}
                                        @endif
                                        <div class="text-xs text-gray-400">{{ $item['days_count'] }} hari</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <ul class="space-y-1">
                                            @foreach ($item['overlaps'] as $peer)
                                                <li class="text-amber-800">
                                                    <span class="font-medium">{{ $peer['employee_name'] }}</span>
                                                    <span class="text-gray-500">· {{ $peer['leave_type_label'] ?? '' }}</span>
                                                    <span class="text-gray-400">
                                                        ({{ $peer['start_date_label'] }}@if (($peer['end_date_label'] ?? null) && ($peer['start_date_label'] ?? '') !== ($peer['end_date_label'] ?? '')) → {{ $peer['end_date_label'] }}@endif)
                                                    </span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-12 text-center text-gray-500">Tidak ada cuti yang berpotongan untuk filter ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach (['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', 'all' => 'Semua'] as $key => $label)
                    <button
                        type="button"
                        wire:click="$set('status', '{{ $key }}')"
                        @class([
                            'px-3 py-1.5 text-sm rounded-md border transition',
                            $status === $key ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                        ])
                    >
                        {{ $label }}
                        <span class="opacity-70">({{ number_format($counts[$key] ?? 0) }})</span>
                    </button>
                @endforeach
            </div>

            <div class="bg-white shadow-sm rounded-lg border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-3">Karyawan</th>
                                <th class="px-4 py-3">Jenis</th>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3 text-right">Hari</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Bentrok</th>
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
                                <tr wire:key="leave-{{ $item['id'] }}" @class(['align-top hover:bg-gray-50/80', 'bg-amber-50/40' => !empty($item['has_overlap'])])>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $item['employee']['full_name'] ?? '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ $item['employee']['employee_code'] ?? '' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ $item['leave_type_label'] }}</td>
                                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                                        {{ $item['start_date_label'] }}
                                        @if ($item['start_date'] !== $item['end_date'])
                                            <span class="text-gray-400">→</span> {{ $item['end_date_label'] }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-800">{{ $item['days_count'] }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ $badge }}">
                                            {{ $item['status_label'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        @if (! empty($item['has_overlap']))
                                            <div class="text-amber-800 space-y-0.5">
                                                @foreach ($item['overlaps'] as $peer)
                                                    <div>
                                                        <span class="font-medium">{{ $peer['employee_name'] }}</span>
                                                        <span class="text-gray-500">({{ $peer['start_date_label'] }}@if (($peer['end_date_label'] ?? null) && $peer['start_date_label'] !== ($peer['end_date_label'] ?? ''))–{{ $peer['end_date_label'] }}@endif)</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        @if ($item['status'] === 'pending')
                                            <button type="button" wire:click="approve('{{ $item['id'] }}')" wire:confirm="Setujui pengajuan cuti ini?" class="text-sm font-medium text-green-700 hover:text-green-800">Setujui</button>
                                            <button type="button" wire:click="reject('{{ $item['id'] }}')" wire:confirm="Tolak pengajuan cuti ini?" class="ml-2 text-sm font-medium text-red-600 hover:text-red-700">Tolak</button>
                                            <button type="button" wire:click="cancel('{{ $item['id'] }}')" wire:confirm="Batalkan pengajuan cuti ini?" class="ml-2 text-sm font-medium text-gray-500 hover:text-gray-700">Batal</button>
                                        @elseif ($item['status'] === 'approved')
                                            <button type="button" wire:click="cancel('{{ $item['id'] }}')" wire:confirm="Batalkan cuti yang sudah disetujui? Jatah cuti akan dikembalikan." class="text-sm font-medium text-red-600 hover:text-red-700">Batalkan</button>
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-12 text-center text-gray-500">Belum ada data cuti untuk filter ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    @if ($showJatahForm)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4" wire:click="closeJatahForm">
            <div class="relative w-full max-w-lg bg-white rounded-xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col" wire:click.stop>
                <div class="flex items-center justify-between px-6 py-3.5 border-b border-gray-200 bg-gray-50 shrink-0">
                    <h3 class="text-lg font-semibold text-gray-900">Tambah Jatah Cuti</h3>
                    <button type="button" wire:click="closeJatahForm" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form wire:submit="saveJatah" class="flex flex-col min-h-0 flex-1">
                    <div class="p-6 space-y-4 overflow-y-auto">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Pilih karyawan</label>
                            <select wire:model="jatah_employee_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Pilih karyawan —</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->full_name }}@if ($employee->employee_code) ({{ $employee->employee_code }})@endif</option>
                                @endforeach
                            </select>
                            @error('jatah_employee_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Tanggal awal</label>
                                <input type="date" wire:model.live="jatah_start_date" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('jatah_start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Tanggal akhir</label>
                                <input type="date" wire:model.live="jatah_end_date" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('jatah_end_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Jumlah hari</label>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <span class="text-gray-500">=</span>
                                <input type="number" min="1" max="366" wire:model="jatah_days" class="w-28 rounded-md border-gray-300 text-sm tabular-nums shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <button type="button" wire:click="syncJatahDaysFromDates" class="text-sm text-[#f7340d] hover:underline">
                                    Hitung dari tanggal
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Isi manual, atau hitung otomatis dari rentang tanggal (Senin–Sabtu).</p>
                            @error('jatah_days') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Keterangan</label>
                            <input type="text" wire:model="jatah_notes" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Opsional">
                            @error('jatah_notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 px-6 py-3.5 border-t border-gray-200 bg-gray-50 shrink-0">
                        <button type="button" wire:click="closeJatahForm" class="px-4 py-2 text-xs font-semibold uppercase border border-gray-300 rounded-md bg-white text-gray-700">Batal</button>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold uppercase rounded-md bg-[#f7340d] text-white hover:bg-[#d92c0a]">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showForm)
        <div class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4" wire:click="closeForm">
            <div class="relative w-full max-w-lg bg-white rounded-xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col" wire:click.stop>
                <div class="flex items-center justify-between px-6 py-3.5 border-b border-gray-200 bg-gray-50 shrink-0">
                    <h3 class="text-lg font-semibold text-gray-900">Catat Cuti</h3>
                    <button type="button" wire:click="closeForm" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form wire:submit="save" class="flex flex-col min-h-0 flex-1">
                    <div class="p-6 space-y-4 overflow-y-auto">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Karyawan</label>
                            <select wire:model.live="employee_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Pilih karyawan —</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->full_name }}@if ($employee->employee_code) ({{ $employee->employee_code }})@endif</option>
                                @endforeach
                            </select>
                            @error('employee_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Jenis cuti</label>
                                <select wire:model.live="leave_type" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach ($typeOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('leave_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex items-end pb-2">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" wire:model="auto_approve" class="rounded border-gray-300 text-[#f7340d] focus:ring-[#f7340d]">
                                    Langsung setujui
                                </label>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Mulai</label>
                                <input type="date" wire:model.live="start_date" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Selesai</label>
                                <input type="date" wire:model.live="end_date" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('end_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @if ($quota)
                            <div class="rounded-md bg-sky-50 border border-sky-100 px-3 py-2 text-sm text-sky-900">
                                Jatah {{ $quota['year'] }}:
                                <strong>{{ $quota['available'] }}</strong> hari bisa diajukan
                                (sisa {{ $quota['remaining'] }}, terpakai {{ $quota['used'] }})
                                @if ($previewDays > 0)
                                    · pengajuan <strong>{{ $previewDays }}</strong> hari
                                @endif
                            </div>
                        @elseif ($leave_type === 'tahunan' && filled($employee_id))
                            <p class="text-sm text-gray-500">Pilih tanggal untuk melihat sisa jatah.</p>
                        @endif

                        @if (count($formOverlaps) > 0)
                            <div class="rounded-md bg-amber-50 border border-amber-100 px-3 py-2 text-sm text-amber-900">
                                <p class="font-medium mb-1">Berpotongan dengan cuti karyawan lain:</p>
                                <ul class="list-disc list-inside space-y-0.5">
                                    @foreach ($formOverlaps as $peer)
                                        <li>
                                            {{ $peer['employee_name'] }} — {{ $peer['leave_type_label'] }}
                                            ({{ $peer['start_date_label'] }}@if ($peer['start_date'] !== $peer['end_date']) → {{ $peer['end_date_label'] }}@endif)
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Keterangan</label>
                            <textarea wire:model="reason" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Alasan cuti (opsional)"></textarea>
                            @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 px-6 py-3.5 border-t border-gray-200 bg-gray-50 shrink-0">
                        <button type="button" wire:click="closeForm" class="px-4 py-2 text-xs font-semibold uppercase border border-gray-300 rounded-md bg-white text-gray-700">Batal</button>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold uppercase rounded-md bg-[#f7340d] text-white hover:bg-[#d92c0a]">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
