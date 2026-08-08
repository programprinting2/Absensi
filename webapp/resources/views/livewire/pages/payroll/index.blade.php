<?php

use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Services\PayrollCalculationService;
use App\Services\PayrollPeriodService;
use App\Support\AppTimezone;
use App\Support\IndonesianHolidays;
use App\Support\Toast;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public int $newMonth;

    public int $newYear;

    public int $cutoffStartDay = 1;

    public int $cutoffEndDay = 31;

    public int $jointLeaveDays = 0;

    public bool $showCreateModal = false;

    public ?string $expandedPeriodId = null;

    public function mount(): void
    {
        $this->loadSettingsDefaults();
        $this->newMonth = (int) AppTimezone::nowDisplay()->format('m');
        $this->newYear = (int) AppTimezone::nowDisplay()->format('Y');

        if (request()->boolean('create')) {
            $this->showCreateModal = true;
        }
    }

    public function togglePeriod(string $periodId): void
    {
        $this->expandedPeriodId = $this->expandedPeriodId === $periodId ? null : $periodId;
    }

    public function openCreateModal(): void
    {
        $this->loadSettingsDefaults();
        $now = AppTimezone::nowDisplay();
        $this->newMonth = (int) $now->format('m');
        $this->newYear = (int) $now->format('Y');
        $this->showCreateModal = true;
        $this->resetValidation();
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    public function createPeriod(PayrollPeriodService $service): void
    {
        $this->validate([
            'newMonth' => ['required', 'integer', 'min:1', 'max:12'],
            'newYear' => ['required', 'integer', 'min:2020', 'max:2099'],
            'cutoffStartDay' => ['required', 'integer', 'min:1', 'max:31'],
            'cutoffEndDay' => ['required', 'integer', 'min:1', 'max:31'],
            'jointLeaveDays' => ['required', 'integer', 'min:0', 'max:31'],
        ]);

        PayrollSetting::active()->update([
            'cutoff_start_day' => $this->cutoffStartDay,
            'cutoff_end_day' => $this->cutoffEndDay,
            'joint_leave_days' => $this->jointLeaveDays,
        ]);

        $period = $service->createPeriod($this->newMonth, $this->newYear);
        $this->showCreateModal = false;

        Toast::success("Periode {$period->label} berhasil dibuat.", $this);
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
        $this->expandedPeriodId = $period->id;
    }

    public function finalize(string $periodId, PayrollPeriodService $service): void
    {
        $period = PayrollPeriod::findOrFail($periodId);
        if (! $period->isReview()) {
            return;
        }

        try {
            $service->finalize($period, auth()->user());
            Toast::success('Periode berhasil difinalisasi.', $this);
        } catch (\Throwable $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function unfinalize(string $periodId, PayrollPeriodService $service): void
    {
        $period = PayrollPeriod::findOrFail($periodId);
        if (! $period->isFinalized()) {
            return;
        }

        try {
            $service->unfinalize($period);
            $this->expandedPeriodId = $period->id;
            Toast::success('Finalisasi dibuka. Periode kembali ke Review dan bisa diedit.', $this);
        } catch (\Throwable $e) {
            Toast::error($e->getMessage(), $this);
        }
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
        $now = AppTimezone::nowDisplay();
        $year = (int) $now->year;

        return [
            'periods' => PayrollPeriod::query()
                ->withCount('entries')
                ->orderByDesc('period_start')
                ->get(),
            'calendarToday' => $now->toDateString(),
            'calendarYear' => $year,
            'calendarMonth' => (int) $now->month,
            'holidays' => IndonesianHolidays::forYears([$year - 2, $year - 1, $year, $year + 1, $year + 2]),
        ];
    }

    private function loadSettingsDefaults(): void
    {
        $settings = PayrollSetting::active();
        $this->cutoffStartDay = (int) $settings->cutoff_start_day;
        $this->cutoffEndDay = (int) $settings->cutoff_end_day;
        $this->jointLeaveDays = (int) ($settings->joint_leave_days ?? 0);
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Penggajian</h2>
    </x-slot>

    <div class="h-[calc(100vh-8rem)] flex flex-col">
        <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-4">
            <div class="flex justify-end gap-2 shrink-0">
                <button type="button" wire:click="openCreateModal" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-gray-700">
                    + Buat Periode
                </button>
            </div>

            <div class="flex-1 min-h-0 overflow-auto space-y-2">
                @forelse ($periods as $period)
                    @php
                        $pid = $period->id;
                        $isOpen = $expandedPeriodId === $pid;
                    @endphp
                    <div wire:key="period-row-{{ $pid }}" data-period-print-root="{{ $pid }}" class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-100">
                        <div class="flex items-stretch">
                            <button
                                type="button"
                                wire:click="togglePeriod('{{ $pid }}')"
                                class="flex-1 min-w-0 px-4 py-3 text-left hover:bg-gray-50 transition"
                                aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <svg class="h-4 w-4 text-gray-500 shrink-0 transition-transform {{ $isOpen ? 'rotate-90' : '' }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                        </svg>
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-gray-900">{{ $period->label }}</div>
                                            <div class="text-xs text-gray-500">{{ $period->period_start->format('d/m/Y') }} — {{ $period->period_end->format('d/m/Y') }}</div>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if ($period->isDraft())
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Draft</span>
                                        @elseif ($period->isReview())
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Review</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Final</span>
                                        @endif
                                        <span class="text-xs text-gray-500">{{ $period->entries_count }} karyawan</span>
                                    </div>
                                </div>
                            </button>
                            <div class="flex items-center gap-2 px-4 shrink-0 border-l border-gray-100" @click.stop>
                                @if ($period->isDraft())
                                    <button type="button" wire:click="generate('{{ $period->id }}')" wire:confirm="Generate gaji untuk semua karyawan aktif?" wire:loading.attr="disabled" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                        <span wire:loading.remove wire:target="generate('{{ $period->id }}')">Generate</span>
                                        <span wire:loading wire:target="generate('{{ $period->id }}')">Menghitung...</span>
                                    </button>
                                    <button type="button" wire:click="deletePeriod('{{ $period->id }}')" wire:confirm="Hapus periode ini?" class="text-sm text-red-600 hover:text-red-800 font-medium">Hapus</button>
                                @elseif ($period->isReview())
                                    @include('payroll.partials.print-paper-menu', [
                                        'baseUrl' => route('payroll.slips', $period),
                                        'label' => 'Print',
                                        'iconOnly' => true,
                                        'requireSelected' => true,
                                        'selectionRoot' => '[data-period-print-root="'.$pid.'"]',
                                    ])
                                    <button type="button" wire:click="generate('{{ $period->id }}')" wire:confirm="Generate ulang gaji periode ini? Penyesuaian manual akan diganti." class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">Generate Ulang</button>
                                    <button
                                        type="button"
                                        wire:click="finalize('{{ $period->id }}')"
                                        wire:confirm="Finalisasi periode ini? Data terkunci sampai dibuka kembali."
                                        title="Belum dikunci — klik untuk finalisasi"
                                        aria-label="Finalisasi periode"
                                        class="inline-flex items-center justify-center w-9 h-9 rounded-md text-amber-700 hover:bg-amber-50 hover:text-amber-900"
                                    >
                                        {{-- Status: terbuka (Review) — klik untuk mengunci --}}
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                @else
                                    @include('payroll.partials.print-paper-menu', [
                                        'baseUrl' => route('payroll.slips', $period),
                                        'label' => 'Print',
                                        'iconOnly' => true,
                                        'requireSelected' => true,
                                        'selectionRoot' => '[data-period-print-root="'.$pid.'"]',
                                    ])
                                    <button
                                        type="button"
                                        wire:click="unfinalize('{{ $period->id }}')"
                                        wire:confirm="Buka finalisasi periode ini? Status kembali ke Review. Cicilan cash bon yang sudah paid akan dikembalikan ke deducted."
                                        title="Terkunci — klik untuk buka kunci"
                                        aria-label="Buka finalisasi periode"
                                        class="inline-flex items-center justify-center w-9 h-9 rounded-md text-green-600 hover:bg-green-50 hover:text-green-800"
                                    >
                                        {{-- Status: terkunci (Final) — klik untuk membuka --}}
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </div>

                        @if ($isOpen)
                            <div class="border-t border-gray-100 bg-gray-50/70 p-4">
                                <livewire:payroll.period-entries :period="$period" :key="'idx-entries-'.$period->id.'-'.$expandedPeriodId" />
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="bg-white shadow-sm rounded-lg px-6 py-12 text-center text-sm text-gray-500">
                        Belum ada periode penggajian.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600/50 p-4"
             x-data="payrollCreateForm({
                 year: {{ (int) $calendarYear }},
                 month: {{ (int) $calendarMonth }},
                 today: {{ \Illuminate\Support\Js::from($calendarToday) }},
                 holidays: {{ \Illuminate\Support\Js::from($holidays) }},
                 startDay: $wire.entangle('cutoffStartDay'),
                 endDay: $wire.entangle('cutoffEndDay'),
                 jointLeave: $wire.entangle('jointLeaveDays'),
                 refMonth: $wire.entangle('newMonth'),
                 refYear: $wire.entangle('newYear'),
             })"
             x-init="syncCalendarToPeriod()"
             @keydown.escape.window="$wire.closeCreateModal()">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[92vh] flex flex-col overflow-hidden"
                 @click.stop>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Buat Periode</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Atur cutoff lalu buat periode penggajian dalam satu langkah.</p>
                    </div>
                    <button type="button" wire:click="closeCreateModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-6">
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
                        <div>
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <h4 class="font-semibold text-gray-900">Kalender</h4>
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="prevMonth()"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                    </button>
                                    <button type="button" @click="goToday()"
                                            class="px-2.5 py-1.5 text-xs font-medium rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50">
                                        Hari ini
                                    </button>
                                    <button type="button" @click="nextMonth()"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </div>
                            </div>

                            <p class="text-sm font-medium text-gray-800 mb-3" x-text="monthLabel"></p>

                            <div class="grid grid-cols-7 gap-1 text-center text-xs font-medium text-gray-500 mb-1">
                                <div>Sen</div><div>Sel</div><div>Rab</div><div>Kam</div><div>Jum</div><div>Sab</div>
                                <div class="text-red-600">Min</div>
                            </div>

                            <div class="grid grid-cols-7 gap-1">
                                <template x-for="cell in cells" :key="cell.key">
                                    <div class="relative min-h-[2.5rem] rounded-md border p-1 text-sm"
                                         :class="cellClasses(cell)"
                                         :title="cell.holidayName || ''">
                                        <span class="font-medium tabular-nums" x-text="cell.day || ''"></span>
                                        <template x-if="cell.holidayName">
                                            <span class="absolute bottom-0.5 left-0.5 right-0.5 block h-1 rounded-full"
                                                  :class="cell.isJointLeave ? 'bg-amber-400' : 'bg-red-500'"></span>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-3 text-xs text-gray-600">
                                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> Libur nasional / Minggu</span>
                                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span> Cuti bersama</span>
                            </div>
                        </div>

                        <div class="space-y-5">
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-3">Bulan Periode</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label for="newMonth" value="Bulan" />
                                        <select wire:model.live="newMonth" id="newMonth"
                                                @change="syncCalendarToPeriod()"
                                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                            @for ($m = 1; $m <= 12; $m++)
                                                <option value="{{ $m }}">{{ \Carbon\Carbon::create(null, $m)->locale('id')->translatedFormat('F') }}</option>
                                            @endfor
                                        </select>
                                        <x-input-error :messages="$errors->get('newMonth')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="newYear" value="Tahun" />
                                        <x-text-input wire:model.live="newYear" id="newYear" type="number" min="2020" max="2099"
                                                      class="mt-1 block w-full"
                                                      @change="syncCalendarToPeriod()" />
                                        <x-input-error :messages="$errors->get('newYear')" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-semibold text-gray-900 mb-3">Periode Cutoff</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label for="cutoffStartDay" value="Tanggal Mulai" />
                                        <x-text-input wire:model.live="cutoffStartDay" id="cutoffStartDay" type="number" min="1" max="31" class="mt-1 block w-full" />
                                        <x-input-error :messages="$errors->get('cutoffStartDay')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="cutoffEndDay" value="Tanggal Akhir" />
                                        <x-text-input wire:model.live="cutoffEndDay" id="cutoffEndDay" type="number" min="1" max="31" class="mt-1 block w-full" />
                                        <x-input-error :messages="$errors->get('cutoffEndDay')" class="mt-2" />
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Contoh: mulai 21 akhir 20 = tanggal 21 bulan lalu s/d 20 bulan ini.</p>
                                <p class="text-xs text-gray-500 mt-1" x-text="'Rentang: ' + periodLabel"></p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <x-input-label value="Hari Minggu" />
                                    <div class="mt-1 flex items-center h-10 px-3 rounded-md border border-gray-200 bg-gray-50 text-sm font-semibold text-red-600 tabular-nums"
                                         x-text="sundayCount"></div>
                                    <p class="mt-1 text-[11px] text-gray-400">Jumlah hari Minggu di periode</p>
                                </div>
                                <div>
                                    <x-input-label for="jointLeaveDays" value="Libur Bersama" />
                                    <x-text-input wire:model.live="jointLeaveDays" id="jointLeaveDays" type="number" min="0" max="31" class="mt-1 block w-full" />
                                    <p class="mt-1 text-[11px] text-gray-400">
                                        Variabel (saran:
                                        <button type="button" class="text-[#f7340d] hover:underline" @click="applySuggestedJointLeave()" x-text="suggestedJointLeave"></button>)
                                    </p>
                                    <x-input-error :messages="$errors->get('jointLeaveDays')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label value="Jumlah Hari Kerja" />
                                    <div class="mt-1 flex items-center h-10 px-3 rounded-md border border-gray-200 bg-gray-50 text-sm font-semibold text-gray-900 tabular-nums"
                                         x-text="workDayCount"></div>
                                    <p class="mt-1 text-[11px] text-gray-400">Otomatis: total − Minggu − libur bersama</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 shrink-0 bg-gray-50">
                    <button type="button" wire:click="closeCreateModal" class="px-4 py-2 text-sm text-gray-700 hover:text-gray-900">Batal</button>
                    <x-primary-button type="button" wire:click="createPeriod" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="createPeriod">Buat Periode</span>
                        <span wire:loading wire:target="createPeriod">Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    @endif

    @include('payroll.partials.print-slip-dialog')
</div>
