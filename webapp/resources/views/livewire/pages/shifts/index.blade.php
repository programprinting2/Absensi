<?php

use App\Models\Employee;
use App\Models\ShiftDayOverride;
use App\Models\ShiftRotationPlan;
use App\Models\WorkSchedule;
use App\Services\ShiftResolver;
use App\Services\ShiftRotationService;
use App\Support\AppTimezone;
use App\Support\Toast;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url(as: 'tab', except: 'rules', history: false)]
    public string $tab = 'rules';

    // —— Rule shift form ——
    public bool $showRuleModal = false;

    public ?string $editingScheduleId = null;

    public string $rule_name = '';

    public string $rule_clock_in = '08:00';

    public float $rule_work_hours = 8;

    public int $rule_break_minutes = 60;

    public string $rule_late_after = '08:15';

    // —— Pola Rotasi ——
    public string $rotationPlanName = '';

    public string $rotationStartDate = '';

    public int $rotationPhaseWorkDays = 6;

    public string $rotScheduleA = '';

    public string $rotScheduleB = '';

    // —— Penempatan ——
    public string $placeBoardDate = '';

    /** placement = ubah dasar; override = pengecualian tanggal board saja */
    public string $placeMode = 'placement';

    public string $placeFilterShift = '';

    public string $placeFilterDept = '';

    public string $placeSearch = '';

    public function mount(): void
    {
        $today = AppTimezone::nowDisplay()->toDateString();
        $this->placeBoardDate = $today;
        $this->rotationStartDate = $today;

        if ($this->tab === 'ops') {
            $this->tab = 'placement';
            $this->placeMode = 'override';
        }

        $activePlan = ShiftRotationPlan::active();
        if ($activePlan) {
            $this->rotationPlanName = $activePlan->name;
            $this->rotationStartDate = $activePlan->start_date->toDateString();
            $this->rotationPhaseWorkDays = (int) $activePlan->phase_work_days;
            $this->rotScheduleA = (string) ($activePlan->schedule_a_id ?? '');
            $this->rotScheduleB = (string) ($activePlan->schedule_b_id ?? '');
        }
    }

    public function openCreateRule(): void
    {
        $this->resetValidation();
        $this->editingScheduleId = null;
        $this->rule_name = '';
        $this->rule_clock_in = '08:00';
        $this->rule_work_hours = 8;
        $this->rule_break_minutes = 60;
        $this->rule_late_after = '08:15';
        $this->showRuleModal = true;
    }

    public function openEditRule(string $id): void
    {
        $this->resetValidation();
        $row = WorkSchedule::findOrFail($id);
        $this->editingScheduleId = $row->id;
        $this->rule_name = $row->name;
        $this->rule_clock_in = substr((string) $row->clock_in_time, 0, 5);
        $this->rule_work_hours = round(((int) ($row->work_duration_minutes ?? 480)) / 60, 1);
        $this->rule_break_minutes = (int) $row->break_duration_minutes;
        $this->rule_late_after = substr((string) ($row->late_after_time ?: $row->clock_in_time), 0, 5);
        $this->showRuleModal = true;
    }

    public function closeRuleModal(): void
    {
        $this->showRuleModal = false;
        $this->editingScheduleId = null;
        $this->resetValidation();
    }

    public function saveRule(): void
    {
        $data = $this->validate([
            'rule_name' => ['required', 'string', 'max:255'],
            'rule_clock_in' => ['required', 'date_format:H:i'],
            'rule_work_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'rule_break_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'rule_late_after' => ['required', 'date_format:H:i'],
        ]);

        $workMinutes = (int) round(((float) $data['rule_work_hours']) * 60);
        $clockOut = $this->calcClockOut($data['rule_clock_in'], $workMinutes, (int) $data['rule_break_minutes']);

        $payload = [
            'name' => $data['rule_name'],
            'clock_in_time' => $data['rule_clock_in'],
            'clock_out_time' => $clockOut,
            'break_duration_minutes' => (int) $data['rule_break_minutes'],
            'work_duration_minutes' => $workMinutes,
            'late_after_time' => $data['rule_late_after'],
            'crosses_midnight' => $clockOut < $data['rule_clock_in'],
        ];

        if ($this->editingScheduleId) {
            WorkSchedule::findOrFail($this->editingScheduleId)->update($payload);
            Toast::success('Rule shift diperbarui.', $this);
        } else {
            $hasDefault = WorkSchedule::query()->where('is_active', true)->exists();
            $payload['is_active'] = ! $hasDefault;
            WorkSchedule::create($payload);
            Toast::success('Rule shift dibuat.', $this);
        }

        $this->closeRuleModal();
    }

    public function setDefaultRule(string $id): void
    {
        DB::transaction(function () use ($id) {
            WorkSchedule::query()->where('is_active', true)->where('id', '!=', $id)->update(['is_active' => false]);
            WorkSchedule::query()->where('id', $id)->update(['is_active' => true]);
        });

        Toast::success('Jadwal default perusahaan diperbarui.', $this);
    }

    public function deleteRule(string $id): void
    {
        $schedule = WorkSchedule::findOrFail($id);

        if ($schedule->is_active) {
            Toast::error('Tidak bisa hapus jadwal default. Jadikan rule lain sebagai default dulu.', $this);

            return;
        }

        if (WorkSchedule::query()->count() <= 1) {
            Toast::error('Minimal harus ada satu rule shift.', $this);

            return;
        }

        if ($schedule->assignments()->exists()) {
            Toast::error('Rule masih dipakai penempatan karyawan. Pindahkan dulu di tab Penempatan.', $this);

            return;
        }

        $name = $schedule->name;
        $schedule->delete();
        Toast::success("Rule \"{$name}\" dihapus.", $this);
    }

    public function saveRotationPlan(ShiftRotationService $rotationService): void
    {
        $data = $this->validate([
            'rotationPlanName' => ['required', 'string', 'max:255'],
            'rotationStartDate' => ['required', 'date'],
            'rotationPhaseWorkDays' => ['required', 'integer', 'min:1', 'max:30'],
            'rotScheduleA' => ['required', 'uuid', 'exists:work_schedules,id', 'different:rotScheduleB'],
            'rotScheduleB' => ['required', 'uuid', 'exists:work_schedules,id'],
        ], [
            'rotScheduleA.different' => 'Pilih dua rule shift yang berbeda.',
        ]);

        try {
            $rotationService->savePairPlan(
                $data['rotationPlanName'],
                $data['rotationStartDate'],
                (int) $data['rotationPhaseWorkDays'],
                $data['rotScheduleA'],
                $data['rotScheduleB'],
                activate: true,
            );
            Toast::success('Pola rotasi disimpan dan diaktifkan.', $this);
        } catch (\Throwable $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function moveToShift(string $employeeId, string $scheduleId, ShiftRotationService $rotationService, ShiftResolver $resolver): void
    {
        $data = validator(
            [
                'employeeId' => $employeeId,
                'scheduleId' => $scheduleId,
                'placeBoardDate' => $this->placeBoardDate,
                'placeMode' => $this->placeMode,
            ],
            [
                'employeeId' => ['required', 'uuid', 'exists:employees,id'],
                'scheduleId' => ['required', 'uuid', 'exists:work_schedules,id'],
                'placeBoardDate' => ['required', 'date'],
                'placeMode' => ['required', 'in:placement,override'],
            ],
        )->validate();

        $schedule = WorkSchedule::findOrFail($data['scheduleId']);
        $employee = Employee::findOrFail($data['employeeId']);

        if ($data['placeMode'] === 'override') {
            $currentId = $resolver->forEmployeeOnDate($employee, $data['placeBoardDate'])?->id;
            if ($currentId && (string) $currentId === (string) $schedule->id) {
                return;
            }

            $rotationService->setOverride($employee, $data['placeBoardDate'], $schedule, 'Pengecualian via board');
            $resolver->forgetCache();
            Toast::success("{$employee->full_name} → {$schedule->name} hanya untuk {$data['placeBoardDate']}.", $this);

            return;
        }

        $base = $rotationService->basePlacement($employee->id, $data['placeBoardDate']);
        if ($base && (string) $base->id === (string) $schedule->id) {
            return;
        }

        $resolver->assign($employee, $schedule, $data['placeBoardDate']);
        Toast::success("{$employee->full_name} ditempatkan ke {$schedule->name} mulai {$data['placeBoardDate']}.", $this);
    }

    public function clearEmployeeOverride(string $employeeId, ShiftRotationService $rotationService, ShiftResolver $resolver): void
    {
        $data = validator(
            [
                'employeeId' => $employeeId,
                'placeBoardDate' => $this->placeBoardDate,
            ],
            [
                'employeeId' => ['required', 'uuid', 'exists:employees,id'],
                'placeBoardDate' => ['required', 'date'],
            ],
        )->validate();

        $employee = Employee::findOrFail($data['employeeId']);
        $rotationService->clearOverride($employee, $data['placeBoardDate']);
        $resolver->forgetCache();
        Toast::success("Override {$employee->full_name} pada {$data['placeBoardDate']} dihapus.", $this);
    }

    private function calcClockOut(string $clockIn, int $workMinutes, int $breakMinutes): string
    {
        [$h, $m] = array_map('intval', explode(':', $clockIn));
        $total = ($h * 60) + $m + $workMinutes + $breakMinutes;
        $total = (($total % 1440) + 1440) % 1440;

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    public function with(ShiftResolver $resolver, ShiftRotationService $rotationService): array
    {
        $today = AppTimezone::nowDisplay()->toDateString();
        $boardDate = $this->placeBoardDate ?: $today;

        $schedules = WorkSchedule::query()
            ->withCount('assignments')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $employees = Employee::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code', 'department']);

        $defaultSchedule = WorkSchedule::active() ?? $schedules->first();
        $scheduleById = $schedules->keyBy('id');

        $activePlan = ShiftRotationPlan::active();
        $phaseIndex = $activePlan
            ? $rotationService->phaseIndexForDate($activePlan, $boardDate)
            : null;
        $phaseLabel = $phaseIndex !== null
            ? $rotationService->phaseLabel($phaseIndex)
            : null;

        $overrideIds = ShiftDayOverride::query()
            ->whereDate('work_date', $boardDate)
            ->pluck('employee_id')
            ->map(fn ($id) => (string) $id)
            ->all();
        $overrideSet = array_fill_keys($overrideIds, true);

        $placementPreview = [];
        $placementRows = $employees->map(function (Employee $employee) use (
            $rotationService,
            $resolver,
            $scheduleById,
            $defaultSchedule,
            $boardDate,
            $overrideSet,
            &$placementPreview,
        ) {
            $base = $rotationService->basePlacement($employee->id, $boardDate);
            $baseId = $base?->id ?? $defaultSchedule?->id;
            $baseSchedule = $baseId ? $scheduleById->get($baseId) : null;

            $resolved = $resolver->forEmployeeOnDate($employee->id, $boardDate);
            $resolvedId = $resolved?->id ?? $defaultSchedule?->id;
            $resolvedSchedule = $resolvedId ? ($resolved && (string) $resolved->id === (string) $resolvedId ? $resolved : $scheduleById->get($resolvedId)) : null;
            $placementPreview[(string) $employee->id] = $resolvedId;

            $resolvedDiffers = $resolvedId && $baseId && (string) $resolvedId !== (string) $baseId;

            return [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
                'department' => $employee->department,
                'base_schedule_id' => $baseId,
                'base_schedule_name' => $baseSchedule?->name ?? '—',
                'resolved_schedule_id' => $resolvedId,
                'resolved_schedule_name' => $resolvedSchedule?->name,
                'resolved_differs' => $resolvedDiffers,
                'has_override' => isset($overrideSet[(string) $employee->id]),
            ];
        });

        if ($this->placeFilterShift !== '') {
            $filterKey = $this->placeMode === 'override' ? 'resolved_schedule_id' : 'base_schedule_id';
            $placementRows = $placementRows->where($filterKey, $this->placeFilterShift)->values();
        }

        if ($this->placeFilterDept !== '') {
            $dept = mb_strtolower(trim($this->placeFilterDept));
            $placementRows = $placementRows->filter(
                fn ($r) => mb_strtolower(trim((string) ($r['department'] ?? ''))) === $dept
            )->values();
        }

        if (trim($this->placeSearch) !== '') {
            $q = mb_strtolower(trim($this->placeSearch));
            $placementRows = $placementRows->filter(function ($r) use ($q) {
                return str_contains(mb_strtolower($r['full_name']), $q)
                    || str_contains(mb_strtolower((string) $r['employee_code']), $q);
            })->values();
        }

        $departments = $employees->pluck('department')->filter()->map(fn ($d) => trim((string) $d))->unique()->sort()->values();

        $groupKey = $this->placeMode === 'override' ? 'resolved_schedule_id' : 'base_schedule_id';

        $placementGroups = $schedules
            ->when($this->placeFilterShift !== '', fn ($c) => $c->where('id', $this->placeFilterShift))
            ->values()
            ->map(function (WorkSchedule $schedule) use ($placementRows, $groupKey) {
                $rows = $placementRows
                    ->where($groupKey, $schedule->id)
                    ->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values();

                return [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'clock_in' => substr((string) $schedule->clock_in_time, 0, 5),
                    'clock_out' => substr((string) $schedule->clock_out_time, 0, 5),
                    'is_default' => (bool) $schedule->is_active,
                    'count' => $rows->count(),
                    'rows' => $rows,
                ];
            });

        return [
            'schedules' => $schedules,
            'employees' => $employees,
            'activePlan' => $activePlan,
            'phaseIndex' => $phaseIndex,
            'phaseLabel' => $phaseLabel,
            'overrideIds' => $overrideIds,
            'placementPreview' => $placementPreview,
            'placementRows' => $placementRows,
            'placementGroups' => $placementGroups,
            'departments' => $departments,
            'boardDate' => $boardDate,
            'today' => $today,
        ];
    }
}; ?>

<div class="h-[calc(100vh-8rem)] flex flex-col">
    <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-3">
        <div class="shrink-0 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Shift Kerja</h2>
                <p class="text-sm text-gray-500 mt-0.5">Rule shift → penempatan → pola rotasi.</p>
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden flex-1 flex flex-col min-h-0">
            <nav class="shrink-0 border-b border-gray-200 px-4">
                <div class="flex gap-1 -mb-px overflow-x-auto">
                    @foreach ([
                        'rules' => 'Rule Shift',
                        'placement' => 'Penempatan',
                        'rotation' => 'Pola Rotasi',
                    ] as $key => $label)
                        <button type="button" wire:click="$set('tab', '{{ $key }}')"
                            @class([
                                'whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition-colors',
                                $tab === $key
                                    ? 'border-[#f7340d] text-[#f7340d]'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                            ])>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </nav>

            <div class="flex-1 min-h-0 overflow-y-auto p-4 sm:p-6">
                {{-- ========== RULE SHIFT ========== --}}
                @if ($tab === 'rules')
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Rule Shift</h3>
                            <p class="text-sm text-gray-500 mt-0.5">Contoh: Shift Pagi, Shift Malam, Jam Ramadhan. Satu rule bertanda Default untuk karyawan tanpa penempatan khusus.</p>
                        </div>
                        <button type="button" wire:click="openCreateRule"
                            class="inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                            + Buat Rule
                        </button>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-2.5">Nama</th>
                                    <th class="px-4 py-2.5">Masuk</th>
                                    <th class="px-4 py-2.5">Pulang</th>
                                    <th class="px-4 py-2.5">Istirahat</th>
                                    <th class="px-4 py-2.5">Jam kerja</th>
                                    <th class="px-4 py-2.5">Telat setelah</th>
                                    <th class="px-4 py-2.5">Status</th>
                                    <th class="px-4 py-2.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($schedules as $row)
                                    <tr wire:key="rule-{{ $row->id }}">
                                        <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">
                                            {{ $row->name }}
                                            @if ($row->crosses_midnight)
                                                <span class="ml-1 text-xs text-amber-700">overnight</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 font-mono tabular-nums">{{ substr((string) $row->clock_in_time, 0, 5) }}</td>
                                        <td class="px-4 py-3 font-mono tabular-nums">{{ substr((string) $row->clock_out_time, 0, 5) }}</td>
                                        <td class="px-4 py-3">{{ $row->break_duration_minutes }} m</td>
                                        <td class="px-4 py-3">{{ (($row->work_duration_minutes ?? 480) / 60) }} jam</td>
                                        <td class="px-4 py-3 font-mono tabular-nums">{{ substr((string) ($row->late_after_time ?: $row->clock_in_time), 0, 5) }}</td>
                                        <td class="px-4 py-3">
                                            @if ($row->is_active)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Default</span>
                                            @else
                                                <button type="button" wire:click="setDefaultRule('{{ $row->id }}')"
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 hover:bg-indigo-50 hover:text-indigo-700">
                                                    Jadikan default
                                                </button>
                                            @endif
                                            <div class="text-xs text-gray-400 mt-1">{{ $row->assignments_count }} penempatan</div>
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <button type="button" wire:click="openEditRule('{{ $row->id }}')" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">Edit</button>
                                            <button type="button" wire:click="deleteRule('{{ $row->id }}')" wire:confirm="Hapus rule {{ $row->name }}?" class="ml-3 text-red-600 hover:text-red-800 text-sm font-medium">Hapus</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-10 text-center text-gray-500">Belum ada rule shift. Buat yang pertama.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                {{-- ========== PENEMPATAN ========== --}}
                @elseif ($tab === 'placement')
                    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Penempatan Karyawan</h3>
                            <p class="text-sm text-gray-500 mt-0.5">
                                @if ($placeMode === 'override')
                                    Mode pengecualian: seret untuk ubah shift <strong>hanya tanggal ini</strong>. Penempatan dasar &amp; pola rotasi tidak berubah.
                                @else
                                    Mode penempatan: seret untuk ubah penempatan dasar. Pola rotasi memutar otomatis dari posisi ini.
                                @endif
                            </p>
                            @if ($phaseLabel)
                                <p class="text-xs text-indigo-700 mt-1">Rotasi aktif · {{ $phaseLabel }} · {{ $boardDate }}</p>
                            @endif
                        </div>
                        <div class="w-44">
                            <label class="block text-xs font-medium text-gray-600">
                                {{ $placeMode === 'override' ? 'Tanggal pengecualian' : 'Berlaku mulai' }}
                            </label>
                            <input wire:model.live="placeBoardDate" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                            @error('placeBoardDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mb-4 inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1">
                        <button type="button" wire:click="$set('placeMode', 'placement')"
                            @class([
                                'rounded-md px-3 py-1.5 text-sm font-medium transition',
                                $placeMode === 'placement' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900',
                            ])>
                            Atur penempatan
                        </button>
                        <button type="button" wire:click="$set('placeMode', 'override')"
                            @class([
                                'rounded-md px-3 py-1.5 text-sm font-medium transition',
                                $placeMode === 'override' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900',
                            ])>
                            Pengecualian tanggal ini
                        </button>
                    </div>

                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="w-44">
                                <label class="block text-sm font-medium text-gray-700">Filter shift</label>
                                <select wire:model.live="placeFilterShift" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    <option value="">Semua</option>
                                    @foreach ($schedules as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="w-44">
                                <label class="block text-sm font-medium text-gray-700">Departemen</label>
                                <select wire:model.live="placeFilterDept" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    <option value="">Semua</option>
                                    @foreach ($departments as $dept)
                                        <option value="{{ $dept }}">{{ $dept }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="w-52">
                                <label class="block text-sm font-medium text-gray-700">Cari</label>
                                <input wire:model.live.debounce.300ms="placeSearch" type="search" placeholder="Nama / kode" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                            </div>
                        </div>
                    </div>

                    @if ($placementGroups->isEmpty())
                        <div class="border border-gray-200 rounded-lg px-6 py-10 text-center text-sm text-gray-500">
                            Belum ada rule shift. Buat dulu di tab Rule Shift.
                        </div>
                    @else
                        <div
                            class="flex gap-4 overflow-x-auto pb-2 items-start"
                            wire:key="board-{{ $placeMode }}-{{ $boardDate }}"
                            x-data="{
                                draggingId: null,
                                fromId: null,
                                overId: null,
                                start(id, from) {
                                    this.draggingId = id;
                                    this.fromId = from;
                                },
                                enter(to) {
                                    if (this.draggingId) this.overId = to;
                                },
                                leave(to) {
                                    if (this.overId === to) this.overId = null;
                                },
                                drop(to) {
                                    if (this.draggingId && this.fromId !== to) {
                                        $wire.moveToShift(this.draggingId, to);
                                    }
                                    this.draggingId = null;
                                    this.fromId = null;
                                    this.overId = null;
                                },
                                end() {
                                    this.draggingId = null;
                                    this.fromId = null;
                                    this.overId = null;
                                }
                            }"
                        >
                            @foreach ($placementGroups as $group)
                                <div
                                    wire:key="shift-card-{{ $placeMode }}-{{ $group['id'] }}"
                                    class="w-72 shrink-0 rounded-xl border bg-white shadow-sm flex flex-col max-h-[min(70vh,36rem)] transition"
                                    :class="overId === '{{ $group['id'] }}' ? 'border-[#f7340d] ring-2 ring-[#f7340d]/20' : 'border-gray-200'"
                                    @dragover.prevent="enter('{{ $group['id'] }}')"
                                    @dragenter.prevent="enter('{{ $group['id'] }}')"
                                    @dragleave="leave('{{ $group['id'] }}')"
                                    @drop.prevent="drop('{{ $group['id'] }}')"
                                >
                                    <div class="px-4 py-3 border-b border-gray-100 shrink-0">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $group['name'] }}</p>
                                                <p class="text-xs font-mono tabular-nums text-gray-500 mt-0.5">
                                                    {{ $group['clock_in'] }}–{{ $group['clock_out'] }}
                                                </p>
                                            </div>
                                            <div class="flex flex-col items-end gap-1 shrink-0">
                                                @if ($group['is_default'])
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-800">Default</span>
                                                @endif
                                                <span class="text-xs text-gray-500">{{ $group['count'] }} orang</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex-1 overflow-y-auto p-3 space-y-2 min-h-[8rem]">
                                        @forelse ($group['rows'] as $row)
                                            <div
                                                wire:key="emp-card-{{ $row['id'] }}-{{ $placeMode }}-{{ $boardDate }}"
                                                draggable="true"
                                                @dragstart="start('{{ $row['id'] }}', '{{ $group['id'] }}')"
                                                @dragend="end()"
                                                class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 cursor-grab active:cursor-grabbing hover:border-gray-300 hover:bg-white transition select-none"
                                                :class="draggingId === '{{ $row['id'] }}' ? 'opacity-40' : ''"
                                            >
                                                <div class="flex items-start justify-between gap-1">
                                                    <p class="text-sm font-medium text-gray-900 leading-snug">{{ $row['full_name'] }}</p>
                                                    @if ($row['has_override'])
                                                        <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800">override</span>
                                                    @endif
                                                </div>
                                                <p class="text-xs text-gray-500 mt-0.5 truncate">
                                                    @if ($row['employee_code'])
                                                        {{ $row['employee_code'] }}
                                                    @endif
                                                    @if ($row['department'])
                                                        @if ($row['employee_code']) · @endif{{ $row['department'] }}
                                                    @endif
                                                    @if (! $row['employee_code'] && ! $row['department'])
                                                        —
                                                    @endif
                                                </p>
                                                @if ($placeMode === 'placement' && $row['resolved_differs'] && $row['resolved_schedule_name'])
                                                    <p class="text-[10px] text-indigo-600 mt-1">
                                                        Hari ini → {{ $row['resolved_schedule_name'] }}
                                                    </p>
                                                @endif
                                                @if ($placeMode === 'override' && $row['resolved_differs'] && $row['base_schedule_name'])
                                                    <p class="text-[10px] text-gray-500 mt-1">
                                                        Dasar: {{ $row['base_schedule_name'] }}
                                                    </p>
                                                @endif
                                                @if ($placeMode === 'override' && $row['has_override'])
                                                    <button type="button"
                                                        wire:click.stop="clearEmployeeOverride('{{ $row['id'] }}')"
                                                        class="mt-1.5 text-[10px] font-medium text-amber-800 hover:text-amber-950 underline">
                                                        Hapus override
                                                    </button>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="h-full min-h-[6rem] flex items-center justify-center rounded-lg border border-dashed border-gray-200 text-xs text-gray-400 px-3 text-center">
                                                Drop karyawan ke sini
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                {{-- ========== POLA ROTASI ========== --}}
                @elseif ($tab === 'rotation')
                    <div class="mb-4">
                        <h3 class="text-base font-semibold text-gray-900">Pola Rotasi</h3>
                        <p class="text-sm text-gray-500 mt-0.5">
                            Penempatan karyawan ke salah satu shift ini. Setiap fase (N hari kerja), shift mereka saling bertukar otomatis.
                        </p>
                    </div>

                    @if ($activePlan)
                        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                            <p class="text-sm font-semibold text-green-900">Pola aktif: {{ $activePlan->name }}</p>
                            <p class="text-xs text-green-800 mt-1">
                                Mulai {{ $activePlan->start_date->format('d/m/Y') }} ·
                                {{ $activePlan->phase_work_days }} hari kerja/fase
                            </p>
                            @if ($activePlan->scheduleA && $activePlan->scheduleB)
                                <p class="text-xs text-green-800 mt-1">
                                    <span class="font-medium">{{ $activePlan->scheduleA->name }}</span>
                                    ↔
                                    <span class="font-medium">{{ $activePlan->scheduleB->name }}</span>
                                </p>
                            @endif
                            @if ($phaseLabel)
                                <p class="text-xs text-green-700 mt-2">Fase hari ini ({{ $today }}): <strong>{{ $phaseLabel }}</strong></p>
                            @endif
                        </div>
                    @endif

                    <form wire:submit="saveRotationPlan" class="border border-gray-200 rounded-lg p-5 space-y-4 max-w-2xl">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nama pola</label>
                            <input wire:model="rotationPlanName" type="text" placeholder="Contoh: Rotasi Pagi-Sore" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                            @error('rotationPlanName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Mulai tanggal</label>
                                <input wire:model="rotationStartDate" type="date" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                                @error('rotationStartDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Hari kerja per fase</label>
                                <input wire:model="rotationPhaseWorkDays" type="number" min="1" max="30" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                                @error('rotationPhaseWorkDays') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-4 space-y-3">
                            <p class="text-sm font-semibold text-gray-900">Pasangan shift rotasi</p>
                            <p class="text-xs text-gray-500">Karyawan ditempatkan ke salah satu shift ini. Fase genap = tetap di penempatan; fase ganjil = tukar ke pasangan.</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600">Shift A</label>
                                    <select wire:model="rotScheduleA" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="">— Pilih shift —</option>
                                        @foreach ($schedules as $s)
                                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('rotScheduleA') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600">Shift B</label>
                                    <select wire:model="rotScheduleB" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="">— Pilih shift —</option>
                                        @foreach ($schedules as $s)
                                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('rotScheduleB') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                            Simpan &amp; Aktifkan
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal Rule --}}
    @if ($showRuleModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
            <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                <div class="fixed inset-0 bg-gray-500/75" wire:click="closeRuleModal"></div>
                <div class="relative w-full max-w-lg rounded-lg bg-white shadow-xl p-6 space-y-4"
                    x-data="{
                        clockIn: @js($rule_clock_in),
                        workHours: {{ (float) $rule_work_hours }},
                        breakMinutes: {{ (int) $rule_break_minutes }},
                        get clockOut() {
                            return window.calcWorkClockOut ? window.calcWorkClockOut(this.clockIn, this.workHours, this.breakMinutes) : '—';
                        },
                        sync() {
                            $wire.set('rule_clock_in', this.clockIn);
                            $wire.set('rule_work_hours', this.workHours);
                            $wire.set('rule_break_minutes', this.breakMinutes);
                        }
                    }">
                    <div class="flex items-start justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ $editingScheduleId ? 'Update Rule Shift' : 'Buat Rule Shift' }}
                        </h3>
                        <button type="button" wire:click="closeRuleModal" class="text-gray-400 hover:text-gray-600">&times;</button>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nama Rule</label>
                        <input wire:model="rule_name" type="text" placeholder="Contoh: Shift Pagi, Shift Malam" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                        @error('rule_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <p class="text-sm font-medium text-gray-900">Jadwal harian</p>
                        <p class="text-xs text-gray-500 mt-0.5">Isi jam masuk, lama kerja, dan istirahat — jam pulang dihitung otomatis.</p>
                        <div class="grid grid-cols-3 gap-3 mt-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Jam Masuk</label>
                                <input type="text" maxlength="5" placeholder="08:00" x-model="clockIn" @blur="sync()"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm font-mono shadow-sm" />
                                @error('rule_clock_in') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Lama Kerja</label>
                                <input type="number" min="1" max="24" step="0.5" x-model.number="workHours" @change="sync()"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                                @error('rule_work_hours') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Istirahat</label>
                                <input type="number" min="0" max="480" x-model.number="breakMinutes" @change="sync()"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                                @error('rule_break_minutes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="mt-3 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Jam Pulang</p>
                                <p class="text-xs text-gray-500 mt-0.5" x-text="(clockIn || '—') + ' + ' + (workHours || 0) + ' jam + ' + (breakMinutes || 0) + ' m'"></p>
                            </div>
                            <p class="text-xl font-semibold font-mono tabular-nums text-gray-900" x-text="clockOut"></p>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <p class="text-sm font-medium text-gray-900">Aturan keterlambatan</p>
                        <p class="text-xs text-gray-500 mt-0.5">Masuk setelah jam ini dihitung terlambat.</p>
                        <div class="max-w-xs mt-2">
                            <label class="block text-sm font-medium text-gray-700">Terlambat Ketika Jam</label>
                            <input wire:model="rule_late_after" type="text" maxlength="5" placeholder="08:15" class="mt-1 block w-full rounded-md border-gray-300 text-sm font-mono shadow-sm" />
                            @error('rule_late_after') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeRuleModal" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Batal</button>
                        <button type="button" @click="sync(); $wire.saveRule()" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                            {{ $editingScheduleId ? 'Simpan Perubahan' : 'Simpan' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
