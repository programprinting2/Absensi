<?php

use App\Models\ShiftSwapRequest;
use App\Models\WorkSchedule;
use App\Services\ShiftCalendarService;
use App\Services\ShiftSwapService;
use App\Support\Toast;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $blockStart = '';

    public bool $showForm = false;

    public string $work_date = '';

    public string $to_schedule_id = '';

    public string $reason = '';

    public function mount(ShiftCalendarService $calendar): void
    {
        $block = $calendar->fourWeekBlock();
        $this->blockStart = $block['start'];
    }

    public function prevBlock(ShiftCalendarService $calendar): void
    {
        $start = Carbon::parse($this->blockStart)->subWeeks(4)->toDateString();
        $this->blockStart = $calendar->fourWeekBlock($start)['start'];
    }

    public function nextBlock(ShiftCalendarService $calendar): void
    {
        $start = Carbon::parse($this->blockStart)->addWeeks(4)->toDateString();
        $this->blockStart = $calendar->fourWeekBlock($start)['start'];
    }

    public function openForm(?string $date = null): void
    {
        $this->resetValidation();
        $this->work_date = $date ?: now()->toDateString();
        $this->to_schedule_id = '';
        $this->reason = '';
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function save(ShiftSwapService $swaps): void
    {
        $employee = auth()->user()?->employee;
        if (! $employee) {
            Toast::error('Akun belum terhubung ke data karyawan.', $this);

            return;
        }

        $data = $this->validate([
            'work_date' => ['required', 'date'],
            'to_schedule_id' => ['required', 'uuid', 'exists:work_schedules,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $swaps->createRequest(
                $employee,
                $data['work_date'],
                $data['to_schedule_id'],
                $data['reason'] ?: null,
            );
            $this->showForm = false;
            Toast::success('Pengajuan tukar sif terkirim.', $this);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function cancel(string $id, ShiftSwapService $swaps): void
    {
        $employee = auth()->user()?->employee;
        if (! $employee) {
            Toast::error('Akun belum terhubung ke data karyawan.', $this);

            return;
        }

        try {
            $req = ShiftSwapRequest::query()->findOrFail($id);
            $swaps->cancel($req, $employee);
            Toast::success('Pengajuan dibatalkan.', $this);
        } catch (\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function with(ShiftCalendarService $calendar): array
    {
        $employee = auth()->user()?->employee;
        $board = $employee
            ? $calendar->employeeBoard($employee, $this->blockStart ?: null)
            : null;

        $blockLabel = null;
        if ($board) {
            $blockLabel = Carbon::parse($board['block']['start'])->translatedFormat('d M')
                .' – '.Carbon::parse($board['block']['end'])->translatedFormat('d M Y');
        }

        $requests = $employee
            ? ShiftSwapRequest::query()
                ->with('toSchedule')
                ->where('employee_id', $employee->id)
                ->orderByDesc('created_at')
                ->limit(30)
                ->get()
            : collect();

        return [
            'employee' => $employee,
            'board' => $board,
            'blockLabel' => $blockLabel,
            'schedules' => WorkSchedule::query()->enabled()->orderBy('clock_in_time')->orderBy('name')->get(),
            'requests' => $requests,
            'weekdayLabels' => ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU', 'MINGGU'],
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
                    <h3 class="text-base font-semibold text-gray-900">Jadwal Saya</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Lihat jadwal 4 minggu dan ajukan tukar sif jika perlu.</p>
                </div>
                <button type="button" wire:click="openForm" class="inline-flex items-center px-3 py-2 rounded-md text-sm font-semibold text-white bg-[#f7340d] hover:bg-[#d92c0a]">
                    + Ajukan Tukar Sif
                </button>
            </div>

            @if ($board)
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="prevBlock" class="rounded-md border border-gray-300 px-2 py-1 text-sm hover:bg-gray-50">←</button>
                    <h4 class="text-sm font-semibold text-gray-900 tabular-nums">{{ $blockLabel }}</h4>
                    <button type="button" wire:click="nextBlock" class="rounded-md border border-gray-300 px-2 py-1 text-sm hover:bg-gray-50">→</button>
                </div>

                <div class="overflow-x-auto">
                    <div class="grid grid-cols-7 gap-1 min-w-[48rem]">
                        @foreach ($weekdayLabels as $label)
                            <div class="text-center text-[10px] font-bold tracking-wide text-gray-500 py-1">{{ $label }}</div>
                        @endforeach

                        @foreach ($board['weeks'] as $week)
                            @foreach ($week as $cell)
                                @php
                                    $kindBg = match ($cell['kind']) {
                                        'work' => 'bg-white',
                                        'libur_request', 'libur_karyawan' => 'bg-sky-50',
                                        'libur_hari', 'libur_event' => 'bg-slate-100',
                                        default => 'bg-gray-50',
                                    };
                                @endphp
                                <button type="button"
                                    wire:key="my-cell-{{ $cell['date'] }}"
                                    wire:click="openForm('{{ $cell['date'] }}')"
                                    @class([
                                        'rounded-md border text-left p-2 min-h-[5.5rem] text-xs transition hover:border-gray-400',
                                        $cell['is_today'] ? 'border-blue-500 border-2' : 'border-gray-200',
                                        $kindBg,
                                    ])>
                                    <div class="flex items-start justify-between gap-1">
                                        <span class="inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded px-1 text-[11px] font-bold tabular-nums bg-gray-900 text-white">
                                            {{ $cell['day'] }}
                                        </span>
                                        @if (!empty($cell['national']))
                                            <span title="{{ $cell['national']['name'] }}" class="text-[9px] text-rose-600 font-medium">•Merah</span>
                                        @endif
                                    </div>
                                    <div class="mt-1.5 space-y-0.5">
                                        @if ($cell['is_work'])
                                            <p class="font-semibold text-gray-900 truncate">{{ $cell['schedule_name'] }}</p>
                                            <p class="text-[10px] text-gray-500 tabular-nums">
                                                {{ \Illuminate\Support\Str::of((string) $cell['clock_in'])->substr(0, 5) }}
                                                –
                                                {{ \Illuminate\Support\Str::of((string) $cell['clock_out'])->substr(0, 5) }}
                                            </p>
                                        @else
                                            <p class="font-medium text-slate-600">{{ $cell['label'] }}</p>
                                        @endif
                                    </div>
                                </button>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($showForm)
                <div class="bg-white border border-gray-200 rounded-lg p-5 shadow-sm">
                    <h4 class="text-sm font-semibold text-gray-900 mb-4">Ajukan tukar sif</h4>
                    <form wire:submit="save" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tanggal</label>
                            <input type="date" wire:model="work_date" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('work_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Pindah ke rule</label>
                            <select wire:model="to_schedule_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— pilih —</option>
                                @foreach ($schedules as $sched)
                                    <option value="{{ $sched->id }}">{{ $sched->name }} ({{ \Illuminate\Support\Str::of((string) $sched->clock_in_time)->substr(0, 5) }}–{{ \Illuminate\Support\Str::of((string) $sched->clock_out_time)->substr(0, 5) }})</option>
                                @endforeach
                            </select>
                            @error('to_schedule_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Alasan</label>
                            <textarea wire:model="reason" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Opsional"></textarea>
                            @error('reason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2 flex justify-end gap-2">
                            <button type="button" wire:click="closeForm" class="px-3 py-2 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Batal</button>
                            <button type="submit" class="px-3 py-2 text-sm rounded-md font-semibold text-white bg-[#f7340d] hover:bg-[#d92c0a]">Kirim</button>
                        </div>
                    </form>
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg border border-gray-100 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h4 class="text-sm font-semibold text-gray-900">Pengajuan tukar sif</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Ke rule</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Alasan</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($requests as $item)
                                <tr wire:key="my-swap-{{ $item->id }}">
                                    <td class="px-4 py-3 tabular-nums whitespace-nowrap">{{ $item->work_date->translatedFormat('d M Y') }}</td>
                                    <td class="px-4 py-3">{{ $item->toSchedule?->name ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-amber-50 text-amber-800' => $item->status === \App\Models\ShiftSwapRequest::STATUS_PENDING,
                                            'bg-emerald-50 text-emerald-800' => $item->status === \App\Models\ShiftSwapRequest::STATUS_APPROVED,
                                            'bg-rose-50 text-rose-800' => $item->status === \App\Models\ShiftSwapRequest::STATUS_REJECTED,
                                            'bg-gray-100 text-gray-600' => $item->status === \App\Models\ShiftSwapRequest::STATUS_CANCELLED,
                                        ])>
                                            {{ \App\Models\ShiftSwapRequest::statusLabel($item->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 max-w-xs truncate">{{ $item->reason ?: '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($item->status === \App\Models\ShiftSwapRequest::STATUS_PENDING)
                                            <button type="button" wire:click="cancel('{{ $item->id }}')" class="text-xs text-gray-600 underline hover:text-gray-900">Batalkan</button>
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada pengajuan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endunless
    </div>
</div>
