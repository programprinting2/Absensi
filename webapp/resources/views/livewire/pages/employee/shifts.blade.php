<?php

use App\Models\Employee;
use App\Models\ShiftEmployeeLibur;
use App\Models\ShiftEmployeeShiftOverride;
use App\Models\ShiftGroup;
use App\Models\ShiftSwapRequest;
use App\Models\WorkSchedule;
use App\Services\ShiftCalendarService;
use App\Services\ShiftGroupService;
use App\Services\ShiftSwapService;
use App\Services\ShiftResolver;
use App\Support\AppTimezone;
use App\Support\Toast;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $blockStart = '';

    /** @var 'block'|'month' */
    public string $calendarViewMode = 'block';

    /** @var 'mine'|'detail' */
    public string $scheduleViewMode = 'mine';

    public int $viewYear = 0;

    public int $viewMonth = 0;

    public bool $showRequestModal = false;

    /** @var 'choose'|'move'|'peer_swap' */
    public string $requestStep = 'choose';

    public string $work_date = '';

    public string $to_schedule_id = '';

    public string $counterparty_employee_id = '';

    public string $reason = '';

    public bool $showMemberPanel = false;

    public string $memberPanelDate = '';

    public string $memberPanelGroupId = '';

    public ?string $memberPanelEmployeeId = null;

    public function mount(ShiftCalendarService $calendar): void
    {
        $today = AppTimezone::nowDisplay();
        $todayStr = $today->toDateString();
        $this->blockStart = $calendar->blockStartContainingDate($todayStr);
        $this->viewYear = (int) $today->year;
        $this->viewMonth = (int) $today->month;

        $storedViewMode = (string) session('employee.shift.calendar_view_mode', 'block');
        $this->calendarViewMode = in_array($storedViewMode, ['block', 'month'], true) ? $storedViewMode : 'block';
        $storedScheduleMode = (string) session('employee.shift.schedule_view_mode', 'mine');
        $this->scheduleViewMode = in_array($storedScheduleMode, ['mine', 'detail'], true) ? $storedScheduleMode : 'mine';
        $this->viewYear = (int) session('employee.shift.view_year', $this->viewYear);
        $this->viewMonth = (int) session('employee.shift.view_month', $this->viewMonth);
        if ($this->viewMonth < 1 || $this->viewMonth > 12) {
            $this->viewMonth = (int) $today->month;
            $this->viewYear = (int) $today->year;
        }
    }

    private function rememberCalendarPreferences(): void
    {
        session([
            'employee.shift.calendar_view_mode' => $this->calendarViewMode,
            'employee.shift.schedule_view_mode' => $this->scheduleViewMode,
            'employee.shift.view_year' => $this->viewYear,
            'employee.shift.view_month' => $this->viewMonth,
        ]);
    }

    public function setScheduleViewMode(string $mode): void
    {
        if (! in_array($mode, ['mine', 'detail'], true) || $mode === $this->scheduleViewMode) {
            return;
        }

        $this->scheduleViewMode = $mode;
        $this->closeMemberPanel();
        $this->rememberCalendarPreferences();
    }

    public function setCalendarViewMode(string $mode, ShiftCalendarService $calendar): void
    {
        if (! in_array($mode, ['block', 'month'], true) || $mode === $this->calendarViewMode) {
            return;
        }

        if ($mode === 'month') {
            $anchor = $this->blockStart !== ''
                ? $this->blockStart
                : AppTimezone::nowDisplay()->toDateString();
            $parsed = Carbon::parse($anchor, AppTimezone::display());
            $this->viewYear = (int) $parsed->year;
            $this->viewMonth = (int) $parsed->month;
        } else {
            $anchor = sprintf('%04d-%02d-15', $this->viewYear, $this->viewMonth);
            $this->blockStart = $calendar->blockStartContainingDate($anchor);
        }

        $this->calendarViewMode = $mode;
        $this->closeMemberPanel();
        $this->rememberCalendarPreferences();
    }

    public function prevPeriod(ShiftCalendarService $calendar): void
    {
        if ($this->calendarViewMode === 'month') {
            $shifted = $calendar->shiftMonth($this->viewYear, $this->viewMonth, -1);
            $this->viewYear = $shifted['year'];
            $this->viewMonth = $shifted['month'];
            $this->rememberCalendarPreferences();
        } else {
            $this->blockStart = $calendar->shiftBlock($this->blockStart, -1);
        }
        $this->closeMemberPanel();
    }

    public function nextPeriod(ShiftCalendarService $calendar): void
    {
        if ($this->calendarViewMode === 'month') {
            $shifted = $calendar->shiftMonth($this->viewYear, $this->viewMonth, 1);
            $this->viewYear = $shifted['year'];
            $this->viewMonth = $shifted['month'];
            $this->rememberCalendarPreferences();
        } else {
            $this->blockStart = $calendar->shiftBlock($this->blockStart, 1);
        }
        $this->closeMemberPanel();
    }

    public function goToToday(ShiftCalendarService $calendar): void
    {
        $today = AppTimezone::nowDisplay();
        $todayStr = $today->toDateString();

        if ($this->calendarViewMode === 'month') {
            $this->viewYear = (int) $today->year;
            $this->viewMonth = (int) $today->month;
            $this->rememberCalendarPreferences();
        } else {
            $this->blockStart = $calendar->blockStartContainingDate($todayStr);
        }

        $this->closeMemberPanel();
    }

    public function openMemberPanel(string $date, string $groupId): void
    {
        $this->memberPanelDate = $date;
        $this->memberPanelGroupId = $groupId;
        $this->memberPanelEmployeeId = null;
        $this->showMemberPanel = true;
    }

    public function openEmployeePanel(string $date, string $employeeId): void
    {
        $this->memberPanelDate = $date;
        $this->memberPanelGroupId = '';
        $this->memberPanelEmployeeId = $employeeId;
        $this->showMemberPanel = true;
    }

    public function closeMemberPanel(): void
    {
        $this->showMemberPanel = false;
        $this->memberPanelDate = '';
        $this->memberPanelGroupId = '';
        $this->memberPanelEmployeeId = null;
    }

    public function openRequestChooser(string $date): void
    {
        $this->resetValidation();
        $this->work_date = $date;
        $this->to_schedule_id = '';
        $this->counterparty_employee_id = '';
        $this->reason = '';
        $this->requestStep = 'choose';
        $this->showRequestModal = true;
    }

    public function chooseRequestType(string $type): void
    {
        if (! in_array($type, ['move', 'peer_swap'], true)) {
            return;
        }
        $this->requestStep = $type;
    }

    public function backToRequestChooser(): void
    {
        $this->requestStep = 'choose';
        $this->resetValidation();
    }

    public function closeRequestModal(): void
    {
        $this->showRequestModal = false;
        $this->requestStep = 'choose';
        $this->resetValidation();
    }

    public function saveMove(ShiftSwapService $swaps): void
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
            $swaps->createMoveRequest(
                $employee,
                $data['work_date'],
                $data['to_schedule_id'],
                $data['reason'] ?: null,
            );
            $this->closeRequestModal();
            Toast::success('Pengajuan pindah shift terkirim.', $this);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function savePeerSwap(ShiftSwapService $swaps): void
    {
        $employee = auth()->user()?->employee;
        if (! $employee) {
            Toast::error('Akun belum terhubung ke data karyawan.', $this);

            return;
        }

        $data = $this->validate([
            'work_date' => ['required', 'date'],
            'counterparty_employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $swaps->createPeerSwapRequest(
                $employee,
                $data['work_date'],
                $data['counterparty_employee_id'],
                $data['reason'] ?: null,
            );
            $this->closeRequestModal();
            Toast::success('Pengajuan tukar shift terkirim. Menunggu konfirmasi rekan.', $this);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function approveIncomingPeer(string $id, ShiftSwapService $swaps): void
    {
        $employee = auth()->user()?->employee;
        if (! $employee) {
            Toast::error('Akun belum terhubung ke data karyawan.', $this);

            return;
        }

        try {
            $req = ShiftSwapRequest::query()->findOrFail($id);
            $swaps->approvePeer($req, $employee);
            Toast::success('Tukar shift disetujui. Menunggu konfirmasi admin.', $this);
        } catch (\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function rejectIncomingPeer(string $id, ShiftSwapService $swaps): void
    {
        $employee = auth()->user()?->employee;
        if (! $employee) {
            Toast::error('Akun belum terhubung ke data karyawan.', $this);

            return;
        }

        try {
            $req = ShiftSwapRequest::query()->findOrFail($id);
            $swaps->rejectPeer($req, $employee);
            Toast::success('Tukar shift ditolak.', $this);
        } catch (\RuntimeException $e) {
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

    public function with(ShiftCalendarService $calendar, ShiftGroupService $groupService, ShiftSwapService $swaps): array
    {
        $employee = auth()->user()?->employee;
        $board = null;
        $periodLabel = '';

        if ($employee) {
            if ($this->scheduleViewMode === 'detail') {
                $board = $calendar->boardPayload(
                    $this->calendarViewMode,
                    $this->blockStart,
                    $this->viewYear,
                    $this->viewMonth,
                    false,
                );
            } else {
                $board = $calendar->employeeScheduleBoard(
                    $employee,
                    $this->calendarViewMode,
                    $this->blockStart,
                    $this->viewYear,
                    $this->viewMonth,
                );
            }

            if ($this->calendarViewMode === 'month') {
                $periodLabel = Carbon::create($this->viewYear, $this->viewMonth, 1, 0, 0, 0, AppTimezone::display())
                    ->locale('id')
                    ->translatedFormat('F Y');
            } else {
                $periodLabel = Carbon::parse($board['block']['start'])->translatedFormat('d M')
                    .' – '.Carbon::parse($board['block']['end'])->translatedFormat('d M Y');
            }
        }

        $memberPanelMembers = [];
        $memberPanelLiburIds = [];
        $memberPanelOverrides = [];
        $memberPanelPlacementSchedules = [];
        $memberPanelPlacementGroups = [];
        $memberPanelGroupName = '';
        if ($employee && $this->showMemberPanel) {
            if (filled($this->memberPanelEmployeeId)) {
                $emp = Employee::find($this->memberPanelEmployeeId);
                $memberPanelMembers = $emp ? [[
                    'id' => (string) $emp->id,
                    'full_name' => $emp->full_name,
                    'employee_code' => $emp->employee_code,
                ]] : [];
            } elseif ($this->memberPanelGroupId !== '') {
                $memberPanelGroup = ShiftGroup::query()->find($this->memberPanelGroupId);
                $memberPanelGroupName = $memberPanelGroup?->name ?? '';
                $memberPanelMembers = $groupService->membersForCalendarDate($this->memberPanelGroupId, $this->memberPanelDate);
            }
            $memberPanelLiburIds = ShiftEmployeeLibur::query()
                ->whereDate('work_date', $this->memberPanelDate)
                ->whereIn('employee_id', collect($memberPanelMembers)->pluck('id'))
                ->pluck('employee_id')
                ->map(fn ($id) => (string) $id)
                ->all();
            $memberPanelOverrides = ShiftEmployeeShiftOverride::query()
                ->with('schedule')
                ->whereDate('work_date', $this->memberPanelDate)
                ->whereIn('employee_id', collect($memberPanelMembers)->pluck('id'))
                ->get()
                ->keyBy(fn ($r) => (string) $r->employee_id);

            if ($memberPanelOverrides->isNotEmpty()) {
                $resolver = app(ShiftResolver::class);
                foreach ($memberPanelOverrides->keys() as $empId) {
                    $placement = $resolver->placementScheduleForEmployeeOnDate($empId, $this->memberPanelDate);
                    if ($placement) {
                        $memberPanelPlacementSchedules[$empId] = $placement->name;
                    }
                    $group = $groupService->groupForEmployeeOnDate($empId, $this->memberPanelDate);
                    if ($group && ! $group->is_system_unassigned) {
                        $memberPanelPlacementGroups[$empId] = $group->name;
                    }
                }
            }
        }

        $requests = $employee
            ? ShiftSwapRequest::query()
                ->with(['toSchedule', 'counterparty'])
                ->where('employee_id', $employee->id)
                ->orderByDesc('created_at')
                ->limit(30)
                ->get()
            : collect();

        $incomingPeerRequests = $employee
            ? ShiftSwapRequest::query()
                ->with(['employee', 'toSchedule'])
                ->where('counterparty_employee_id', $employee->id)
                ->where('request_type', ShiftSwapRequest::TYPE_PEER_SWAP)
                ->where('status', ShiftSwapRequest::STATUS_PENDING)
                ->where('peer_status', ShiftSwapRequest::PEER_STATUS_PENDING)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
            : collect();

        $peerSwapOptions = [];
        if ($employee && $this->showRequestModal && $this->requestStep === 'peer_swap' && $this->work_date !== '') {
            $peerSwapOptions = $swaps->employeesScheduledOnDate($this->work_date, (string) $employee->id);
        }

        return [
            'employee' => $employee,
            'board' => $board,
            'periodLabel' => $periodLabel,
            'schedules' => WorkSchedule::query()->enabled()->orderBy('clock_in_time')->orderBy('name')->get(),
            'requests' => $requests,
            'incomingPeerRequests' => $incomingPeerRequests,
            'peerSwapOptions' => $peerSwapOptions,
            'weekdayLabels' => ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU', 'MINGGU'],
            'memberPanelMembers' => $memberPanelMembers,
            'memberPanelLiburIds' => $memberPanelLiburIds,
            'memberPanelOverrides' => $memberPanelOverrides,
            'memberPanelPlacementSchedules' => $memberPanelPlacementSchedules,
            'memberPanelPlacementGroups' => $memberPanelPlacementGroups,
            'memberPanelGroupName' => $memberPanelGroupName,
        ];
    }
}; ?>

<div class="flex flex-col min-h-0 flex-1">
    <div class="bg-white border-b border-gray-200 shrink-0">
        <div class="py-3 px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Jadwal Saya</h2>
            @if ($employee)
                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                    <div class="flex rounded-md border border-gray-300 p-0.5 bg-gray-50 shrink-0 h-8 items-center">
                        <button
                            type="button"
                            wire:click="setScheduleViewMode('mine')"
                            @class([
                                'inline-flex items-center justify-center h-7 shrink-0 rounded px-2.5 text-xs font-semibold whitespace-nowrap transition',
                                $scheduleViewMode === 'mine'
                                    ? 'bg-[#f7340d] text-white'
                                    : 'text-gray-500 hover:text-gray-700',
                            ])
                        >
                            Jadwal Saya
                        </button>
                        <button
                            type="button"
                            wire:click="setScheduleViewMode('detail')"
                            @class([
                                'inline-flex items-center justify-center h-7 shrink-0 rounded px-2.5 text-xs font-semibold whitespace-nowrap transition',
                                $scheduleViewMode === 'detail'
                                    ? 'bg-[#f7340d] text-white'
                                    : 'text-gray-500 hover:text-gray-700',
                            ])
                        >
                            Jadwal Detail
                        </button>
                    </div>
                    <div class="flex rounded-md border border-gray-300 p-0.5 bg-gray-50 shrink-0 h-8 items-center">
                        <button
                            type="button"
                            wire:click="setCalendarViewMode('block')"
                            @class([
                                'inline-flex items-center justify-center h-7 shrink-0 rounded px-2.5 text-xs font-semibold whitespace-nowrap transition',
                                $calendarViewMode === 'block'
                                    ? 'bg-[#f7340d] text-white'
                                    : 'text-gray-500 hover:text-gray-700',
                            ])
                        >
                            Blok 4 Minggu
                        </button>
                        <button
                            type="button"
                            wire:click="setCalendarViewMode('month')"
                            @class([
                                'inline-flex items-center justify-center h-7 shrink-0 rounded px-2.5 text-xs font-semibold whitespace-nowrap transition',
                                $calendarViewMode === 'month'
                                    ? 'bg-[#f7340d] text-white'
                                    : 'text-gray-500 hover:text-gray-700',
                            ])
                        >
                            Kalender
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="h-[calc(100vh-8rem)] flex flex-col min-h-0">
        <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-4 overflow-y-auto">
            @unless ($employee)
                <div class="bg-white shadow-sm rounded-lg px-6 py-10 text-center text-sm text-gray-500">
                    Akun ini belum terhubung ke data karyawan. Hubungi admin.
                </div>
            @else
                @if ($board)
                    <div class="bg-white shadow-sm rounded-lg overflow-hidden flex flex-col min-h-0">
                        <div class="flex flex-col flex-1 min-h-0 -m-0">
                            <div class="shift-calendar-toolbar shrink-0 z-30 bg-white px-4 sm:px-6 pt-3 pb-3 border-b border-gray-100">
                                <div class="flex flex-wrap items-end gap-2">
                                    <div class="flex items-center gap-2 shrink-0 ml-auto">
                                        <button type="button" wire:click="prevPeriod" class="inline-flex items-center justify-center h-8 w-8 rounded-md border border-gray-300 text-sm hover:bg-gray-50">←</button>
                                        <h3 class="text-base font-semibold text-gray-900 tabular-nums whitespace-nowrap">{{ $periodLabel }}</h3>
                                        <button type="button" wire:click="nextPeriod" class="inline-flex items-center justify-center h-8 w-8 rounded-md border border-gray-300 text-sm hover:bg-gray-50">→</button>
                                        <button
                                            type="button"
                                            wire:click="goToToday"
                                            class="inline-flex items-center justify-center h-8 shrink-0 rounded-md border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                        >
                                            Hari Ini
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-1 min-h-0 items-stretch">
                                <div class="flex-1 min-h-0 flex flex-col min-w-0">
                                    <div class="flex-1 min-h-0 overflow-auto">
                                        <div class="min-w-[56rem]">
                                            <div class="sticky top-0 z-20 border-b border-gray-200 bg-white shadow-sm">
                                                <div class="grid grid-cols-7 gap-2.5">
                                                    @foreach ($weekdayLabels as $label)
                                                        <div class="flex items-center justify-center text-xs font-bold tracking-wide text-gray-500 py-2">{{ $label }}</div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <div class="space-y-4" style="padding: 0.625rem;">
                                                @foreach ($board['weeks'] as $wi => $week)
                                                    <section wire:key="my-shift-week-{{ $wi }}-{{ $week[0]['date'] ?? $wi }}" class="rounded-lg border border-gray-200 bg-gray-50/60 overflow-hidden">
                                                        <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50/60 px-3 py-2 rounded-t-lg">
                                                            <span class="inline-flex shrink-0 items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium uppercase tracking-wide text-gray-600">
                                                                Minggu {{ $wi + 1 }}
                                                            </span>
                                                            <div class="flex-1 border-t border-dashed border-gray-300"></div>
                                                            <span class="shrink-0 text-xs font-medium text-gray-500 tabular-nums">
                                                                {{ \Illuminate\Support\Carbon::parse($week[0]['date'])->translatedFormat('d M') }}
                                                                –
                                                                {{ \Illuminate\Support\Carbon::parse($week[6]['date'])->translatedFormat('d M Y') }}
                                                            </span>
                                                        </div>
                                                        <div class="grid grid-cols-7 gap-2.5 p-2.5">
                                                            @foreach ($week as $cell)
                                                                @php
                                                                    $monthShort = mb_strtoupper(mb_substr(
                                                                        \Illuminate\Support\Carbon::parse($cell['date'])->locale('id')->translatedFormat('M'),
                                                                        0,
                                                                        3
                                                                    ));
                                                                    $mine = $cell['mine'] ?? null;
                                                                    $durationOverrideLines = [];
                                                                    if (filled($cell['work_duration_minutes'])) {
                                                                        $workMins = (int) $cell['work_duration_minutes'];
                                                                        $durationOverrideLines[] = $workMins % 60 === 0
                                                                            ? 'Jam kerja: '.($workMins / 60).' jam'
                                                                            : 'Jam kerja: '.$workMins.' menit';
                                                                    }
                                                                    if (filled($cell['break_duration_minutes'])) {
                                                                        $durationOverrideLines[] = 'Istirahat: '.(int) $cell['break_duration_minutes'].' menit';
                                                                    }
                                                                    if (filled($cell['break_earliest_time'])) {
                                                                        $durationOverrideLines[] = 'Mulai istirahat: '.$cell['break_earliest_time'];
                                                                    }
                                                                    $hasDurationOverride = filled($cell['work_duration_minutes'])
                                                                        || filled($cell['break_duration_minutes'])
                                                                        || filled($cell['break_earliest_time']);
                                                                @endphp
                                                                <div wire:key="my-cell-{{ $cell['date'] }}"
                                                                    style="padding: 0.5rem; gap: 0.5rem"
                                                                    @class([
                                                                        'rounded border flex flex-col min-h-[11rem] text-xs relative overflow-visible',
                                                                        $cell['is_today'] ? 'border-blue-500 border-2' : 'border-gray-200',
                                                                        $cell['is_holiday'] ? 'bg-red-100' : 'bg-white',
                                                                        ($calendarViewMode === 'month' && empty($cell['in_month'])) ? 'shift-cell-outside-month' : '',
                                                                    ])>
                                                                    <div class="relative z-10 flex shrink-0 items-stretch min-w-0" style="gap: 0.5rem">
                                                                        <button type="button"
                                                                            wire:click="openRequestChooser('{{ $cell['date'] }}')"
                                                                            class="inline-flex flex-col shrink-0 items-center justify-center rounded font-semibold tabular-nums box-border text-white bg-gray-800 hover:bg-gray-700 transition cursor-pointer"
                                                                            style="padding: 0.2rem 0.25rem; width: 2.5rem; min-height: 2.5rem;"
                                                                            title="Ajukan pindah atau tukar shift">
                                                                            <span class="text-sm leading-none">{{ $cell['day'] }}</span>
                                                                            <span class="text-[10px] font-semibold uppercase leading-none mt-0.5 opacity-80">{{ $monthShort }}</span>
                                                                        </button>
                                                                        @if (!empty($cell['national']['name']))
                                                                            <span
                                                                                x-data="{
                                                                                    showHolidayTip: false,
                                                                                    holidayTipStyle: '',
                                                                                    showTip() {
                                                                                        const r = this.$el.getBoundingClientRect();
                                                                                        const left = Math.max(8, Math.min(r.left, window.innerWidth - 288));
                                                                                        this.holidayTipStyle = `left:${left}px;top:${r.top - 4}px;transform:translateY(-100%)`;
                                                                                        this.showHolidayTip = true;
                                                                                    },
                                                                                }"
                                                                                class="relative min-w-0 flex-1 self-stretch inline-flex cursor-pointer items-center overflow-visible rounded px-1 text-xs text-red-700 bg-red-100 box-border"
                                                                                @mouseenter="showTip()"
                                                                                @mouseleave="showHolidayTip = false"
                                                                            >
                                                                                <template x-teleport="body">
                                                                                    <span
                                                                                        x-show="showHolidayTip"
                                                                                        role="tooltip"
                                                                                        :style="holidayTipStyle"
                                                                                        class="pointer-events-none fixed z-[9999] max-w-[280px] rounded-md bg-gray-900 px-2.5 py-1.5 text-xs font-medium leading-snug text-white shadow-lg whitespace-normal"
                                                                                    >{{ $cell['national']['name'] }}</span>
                                                                                </template>
                                                                                <span class="min-w-0 truncate">{{ $cell['national']['name'] }}</span>
                                                                            </span>
                                                                        @else
                                                                            <span class="min-w-0 flex-1"></span>
                                                                        @endif
                                                                        @if ($hasDurationOverride)
                                                                            <span
                                                                                x-data="{
                                                                                    showDurationTip: false,
                                                                                    durationTipStyle: '',
                                                                                    showTip() {
                                                                                        const r = this.$el.getBoundingClientRect();
                                                                                        const left = Math.max(8, Math.min(r.left, window.innerWidth - 288));
                                                                                        this.durationTipStyle = `left:${left}px;top:${r.top - 4}px;transform:translateY(-100%)`;
                                                                                        this.showDurationTip = true;
                                                                                    },
                                                                                }"
                                                                                class="inline-flex shrink-0 self-stretch"
                                                                                @mouseenter="showTip()"
                                                                                @mouseleave="showDurationTip = false"
                                                                            >
                                                                                <span class="inline-flex shrink-0 items-center justify-end rounded self-stretch pr-0.5 text-red-600 cursor-default"
                                                                                    style="padding-top: 0.25rem; padding-bottom: 0.25rem;">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                                    </svg>
                                                                                </span>
                                                                                <template x-teleport="body">
                                                                                    <span
                                                                                        x-show="showDurationTip"
                                                                                        role="tooltip"
                                                                                        :style="durationTipStyle"
                                                                                        class="pointer-events-none fixed z-[9999] max-w-[280px] rounded-md bg-gray-900 px-2.5 py-1.5 text-xs font-medium leading-snug text-white shadow-lg whitespace-normal"
                                                                                    >
                                                                                        @foreach ($durationOverrideLines as $line)
                                                                                            <span class="block">{{ $line }}</span>
                                                                                        @endforeach
                                                                                    </span>
                                                                                </template>
                                                                            </span>
                                                                        @endif
                                                                    </div>

                                                                    @if ($cell['is_holiday'])
                                                                        <div class="pointer-events-none absolute inset-0 z-0 flex items-center justify-center">
                                                                            <span class="text-sm font-bold tracking-wide text-red-700">LIBUR</span>
                                                                        </div>
                                                                    @elseif ($scheduleViewMode === 'mine')
                                                                        @if ($mine && $mine['is_work'] && ! empty($mine['schedule_color']))
                                                                            <div class="flex-1 flex flex-col min-w-0 rounded items-center justify-center text-center"
                                                                                style="padding: 0.5rem; gap: 0.25rem; background: {{ $mine['schedule_color'] }}">
                                                                                <p class="font-semibold text-gray-900 leading-tight">{{ $mine['schedule_name'] }}</p>
                                                                                <p class="text-[10px] text-gray-500 tabular-nums">
                                                                                    {{ \Illuminate\Support\Str::of((string) $mine['clock_in'])->substr(0, 5) }}
                                                                                    –
                                                                                    {{ \Illuminate\Support\Str::of((string) $mine['clock_out'])->substr(0, 5) }}
                                                                                </p>
                                                                            </div>
                                                                        @else
                                                                            <div class="flex-1 flex flex-col min-w-0 items-center justify-center text-center px-1" style="gap: 0.25rem">
                                                                                <p class="font-medium text-slate-500">{{ $mine['label'] ?? 'Jadwal belum diatur' }}</p>
                                                                            </div>
                                                                        @endif
                                                                    @else
                                                                        <div class="flex-1 flex flex-col min-w-0" style="gap: 0.5rem">
                                                                            @foreach ($board['schedules'] as $sched)
                                                                                @if (! in_array($sched->id, $cell['visible_schedule_ids'] ?? [], true))
                                                                                    @continue
                                                                                @endif
                                                                                @php
                                                                                    $chips = $cell['chips'][$sched->id] ?? [];
                                                                                    $scheduleHoursLabel = substr((string) $sched->clock_in_time, 0, 5).' ~ '.substr((string) $sched->clock_out_time, 0, 5);
                                                                                @endphp
                                                                                <div class="relative overflow-visible rounded flex flex-col min-w-0"
                                                                                    x-data="{
                                                                                        showScheduleTip: false,
                                                                                        scheduleTipStyle: '',
                                                                                        showTip() {
                                                                                            const r = this.$el.querySelector('[data-schedule-tip-trigger]')?.getBoundingClientRect()
                                                                                                ?? this.$el.getBoundingClientRect();
                                                                                            const left = Math.min(r.right + 4, window.innerWidth - 8);
                                                                                            const top = r.top + (r.height / 2);
                                                                                            this.scheduleTipStyle = `left:${left}px;top:${top}px;transform:translateY(-50%)`;
                                                                                            this.showScheduleTip = true;
                                                                                        },
                                                                                    }"
                                                                                    style="padding: 0.5rem; gap: 0.5rem; background: {{ ['#dbeafe','#fce7f3','#fef9c3','#dcfce7','#e0e7ff'][$loop->index % 5] }}">
                                                                                    <template x-teleport="body">
                                                                                        <span
                                                                                            x-show="showScheduleTip"
                                                                                            role="tooltip"
                                                                                            :style="scheduleTipStyle"
                                                                                            class="pointer-events-none fixed z-[9999] w-max rounded-md bg-gray-900 px-2.5 py-1.5 text-center text-xs font-medium leading-snug text-white shadow-lg whitespace-nowrap"
                                                                                        >
                                                                                            <span class="block font-semibold">{{ $sched->name }}</span>
                                                                                            <span class="block font-mono tabular-nums">{{ $scheduleHoursLabel }}</span>
                                                                                        </span>
                                                                                    </template>
                                                                                    <span data-schedule-tip-trigger
                                                                                        class="inline-block w-max max-w-full truncate text-[9px] font-semibold text-gray-500 cursor-default"
                                                                                        @mouseenter="showTip()"
                                                                                        @mouseleave="showScheduleTip = false">
                                                                                        {{ $sched->name }}
                                                                                    </span>
                                                                                    <div class="flex flex-col min-w-0" style="gap: 0.5rem">
                                                                                        @forelse ($chips as $chip)
                                                                                            @php
                                                                                                $chipKind = $chip['kind'] ?? 'employee';
                                                                                            @endphp
                                                                                            <div class="relative flex w-full min-w-0 items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-semibold text-white"
                                                                                                style="background: {{ $chip['color'] }}">
                                                                                                <button type="button"
                                                                                                    @if ($chipKind === 'group')
                                                                                                        wire:click="openMemberPanel('{{ $cell['date'] }}', '{{ $chip['group_id'] }}')"
                                                                                                    @elseif (!empty($chip['employee_id']))
                                                                                                        wire:click="openEmployeePanel('{{ $cell['date'] }}', '{{ $chip['employee_id'] }}')"
                                                                                                    @endif
                                                                                                    class="flex-1 min-w-0 truncate text-left p-0 bg-transparent border-0 font-semibold text-white"
                                                                                                    title="{{ $chipKind === 'group' ? 'Lihat anggota group' : 'Lihat detail' }}">
                                                                                                    {{ $chip['name'] }}
                                                                                                </button>
                                                                                                @if ($chipKind === 'group')
                                                                                                    <x-chip-count
                                                                                                        :count="max(0, (int) ($chip['member_count'] ?? 0))"
                                                                                                        class="shrink-0"
                                                                                                    />
                                                                                                @elseif ($chipKind === 'override' && !empty($chip['employee_id']))
                                                                                                    <span class="shrink-0 inline-flex items-center justify-center opacity-90" title="Tukar shift">
                                                                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                                                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                                                                                                        </svg>
                                                                                                    </span>
                                                                                                @endif
                                                                                            </div>
                                                                                        @empty
                                                                                            <span class="text-[9px] text-gray-400">Kosong</span>
                                                                                        @endforelse
                                                                                    </div>
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    @endif

                                                                    @if (! $cell['is_holiday'] && count($cell['foot']))
                                                                        <div class="border-t border-gray-100 flex flex-col min-w-0 max-h-24 overflow-y-auto" style="gap: 0.5rem; padding-top: 0.5rem">
                                                                            @foreach ($cell['foot'] as $f)
                                                                                <div class="flex w-full min-w-0 items-center justify-between text-[9px]" style="gap: 0.5rem">
                                                                                    @if (! empty($f['employee_id']))
                                                                                        <button type="button"
                                                                                            wire:click="openEmployeePanel('{{ $cell['date'] }}', '{{ $f['employee_id'] }}')"
                                                                                            class="truncate text-left text-gray-700 hover:underline"
                                                                                            title="Lihat detail">
                                                                                            {{ $f['name'] }}
                                                                                        </button>
                                                                                        <button type="button"
                                                                                            wire:click="openEmployeePanel('{{ $cell['date'] }}', '{{ $f['employee_id'] }}')"
                                                                                            @class([
                                                                                                'shrink-0 rounded px-1.5 py-0.5 font-medium whitespace-nowrap cursor-pointer transition hover:opacity-80',
                                                                                                $f['badge_kind'] === 'libur_request' ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : '',
                                                                                                $f['badge_kind'] === 'libur_karyawan' ? 'bg-slate-200 text-slate-700 hover:bg-slate-300' : '',
                                                                                                $f['badge_kind'] === 'swap' ? 'bg-indigo-100 text-indigo-800 hover:bg-indigo-200' : '',
                                                                                            ])>{{ $f['badge'] }}</button>
                                                                                    @else
                                                                                        <span class="truncate text-gray-700">{{ $f['name'] }}</span>
                                                                                        <span @class([
                                                                                            'shrink-0 rounded px-1.5 py-0.5 font-medium whitespace-nowrap',
                                                                                            $f['badge_kind'] === 'libur_request' ? 'bg-amber-100 text-amber-800' : '',
                                                                                            $f['badge_kind'] === 'libur_karyawan' ? 'bg-slate-200 text-slate-700' : '',
                                                                                            $f['badge_kind'] === 'swap' ? 'bg-indigo-100 text-indigo-800' : '',
                                                                                        ])>{{ $f['badge'] }}</span>
                                                                                    @endif
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </section>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($incomingPeerRequests->isNotEmpty())
                    <div class="bg-white shadow-sm rounded-lg border border-indigo-100 overflow-hidden">
                        <div class="px-4 py-3 border-b border-indigo-100 bg-indigo-50/50">
                            <h4 class="text-sm font-semibold text-indigo-900">Permintaan tukar shift masuk</h4>
                            <p class="text-xs text-indigo-700 mt-0.5">Rekan meminta bertukar shift dengan Anda.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <th class="px-4 py-3">Dari</th>
                                        <th class="px-4 py-3">Tanggal</th>
                                        <th class="px-4 py-3">Detail</th>
                                        <th class="px-4 py-3">Alasan</th>
                                        <th class="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($incomingPeerRequests as $incoming)
                                        <tr wire:key="incoming-peer-{{ $incoming->id }}">
                                            <td class="px-4 py-3 font-medium text-gray-900">{{ $incoming->employee?->full_name ?? '—' }}</td>
                                            <td class="px-4 py-3 tabular-nums whitespace-nowrap">{{ $incoming->work_date->translatedFormat('d M Y') }}</td>
                                            <td class="px-4 py-3 text-gray-700">Tukar shift → {{ $incoming->toSchedule?->name ?? '—' }}</td>
                                            <td class="px-4 py-3 text-gray-600 max-w-xs truncate">{{ $incoming->reason ?: '—' }}</td>
                                            <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                                                <button type="button" wire:click="approveIncomingPeer('{{ $incoming->id }}')" class="rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white">Setujui</button>
                                                <button type="button" wire:click="rejectIncomingPeer('{{ $incoming->id }}')" class="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700">Tolak</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="bg-white shadow-sm rounded-lg border border-gray-100 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h4 class="text-sm font-semibold text-gray-900">Pengajuan saya</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">Jenis</th>
                                    <th class="px-4 py-3">Detail</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Alasan</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($requests as $item)
                                    <tr wire:key="my-swap-{{ $item->id }}">
                                        <td class="px-4 py-3 tabular-nums whitespace-nowrap">{{ $item->work_date->translatedFormat('d M Y') }}</td>
                                        <td class="px-4 py-3">{{ \App\Models\ShiftSwapRequest::typeLabel($item->request_type ?? 'move') }}</td>
                                        <td class="px-4 py-3">
                                            @if ($item->isPeerSwap())
                                                Tukar dengan {{ $item->counterparty?->full_name ?? '—' }}
                                            @else
                                                → {{ $item->toSchedule?->name ?? '—' }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                                'bg-amber-50 text-amber-800' => $item->status === \App\Models\ShiftSwapRequest::STATUS_PENDING,
                                                'bg-emerald-50 text-emerald-800' => $item->status === \App\Models\ShiftSwapRequest::STATUS_APPROVED,
                                                'bg-rose-50 text-rose-800' => $item->status === \App\Models\ShiftSwapRequest::STATUS_REJECTED,
                                                'bg-gray-100 text-gray-600' => $item->status === \App\Models\ShiftSwapRequest::STATUS_CANCELLED,
                                            ])>
                                                {{ $item->displayStatusLabel() }}
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
                                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada pengajuan.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endunless
        </div>
    </div>

    @if ($showRequestModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            @if ($requestStep === 'choose')
                                Ajukan perubahan shift
                            @elseif ($requestStep === 'move')
                                Pindah Shift
                            @else
                                Tukar Shift
                            @endif
                        </h3>
                        @if ($work_date !== '')
                            <p class="text-xs text-gray-500 mt-0.5 tabular-nums">
                                {{ \Illuminate\Support\Carbon::parse($work_date, \App\Support\AppTimezone::display())->translatedFormat('l, d M Y') }}
                            </p>
                        @endif
                    </div>
                    <button type="button" wire:click="closeRequestModal" class="text-gray-500 text-sm hover:text-gray-700">Tutup</button>
                </div>

                @if ($requestStep === 'choose')
                    <div class="space-y-3">
                        <button type="button" wire:click="chooseRequestType('move')"
                            class="w-full rounded-lg border border-gray-200 px-4 py-3 text-left hover:bg-gray-50 transition">
                            <p class="text-sm font-semibold text-gray-900">Pindah Shift</p>
                            <p class="text-xs text-gray-500 mt-0.5">Pindah ke shift lain pada tanggal ini.</p>
                        </button>
                        <button type="button" wire:click="chooseRequestType('peer_swap')"
                            class="w-full rounded-lg border border-gray-200 px-4 py-3 text-left hover:bg-gray-50 transition">
                            <p class="text-sm font-semibold text-gray-900">Tukar Shift</p>
                            <p class="text-xs text-gray-500 mt-0.5">Bertukar shift dengan rekan di tanggal yang sama.</p>
                        </button>
                    </div>
                @elseif ($requestStep === 'move')
                    <form wire:submit="saveMove" class="space-y-4">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Pindah ke shift</label>
                            <select wire:model="to_schedule_id" class="w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— pilih —</option>
                                @foreach ($schedules as $sched)
                                    <option value="{{ $sched->id }}">{{ $sched->name }} ({{ \Illuminate\Support\Str::of((string) $sched->clock_in_time)->substr(0, 5) }}–{{ \Illuminate\Support\Str::of((string) $sched->clock_out_time)->substr(0, 5) }})</option>
                                @endforeach
                            </select>
                            @error('to_schedule_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Alasan</label>
                            <textarea wire:model="reason" rows="3" class="w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Opsional"></textarea>
                            @error('reason') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex justify-between gap-2 pt-1">
                            <button type="button" wire:click="backToRequestChooser" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Kembali</button>
                            <button type="submit" class="inline-flex items-center rounded-md bg-[#f7340d] border border-transparent px-4 py-2 text-xs font-semibold text-white uppercase tracking-widest hover:bg-[#d92c0a] transition">Kirim</button>
                        </div>
                    </form>
                @else
                    <form wire:submit="savePeerSwap" class="space-y-4">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Tukar dengan</label>
                            <select wire:model="counterparty_employee_id" class="w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— pilih karyawan —</option>
                                @foreach ($peerSwapOptions as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['full_name'] }} ({{ $opt['schedule_name'] }})</option>
                                @endforeach
                            </select>
                            @error('counterparty_employee_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            @if (count($peerSwapOptions) === 0)
                                <p class="text-xs text-amber-600 mt-1">Tidak ada karyawan lain yang dijadwalkan kerja pada tanggal ini.</p>
                            @endif
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Alasan</label>
                            <textarea wire:model="reason" rows="3" class="w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Opsional"></textarea>
                            @error('reason') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex justify-between gap-2 pt-1">
                            <button type="button" wire:click="backToRequestChooser" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Kembali</button>
                            <button type="submit" @disabled(count($peerSwapOptions) === 0) class="inline-flex items-center rounded-md bg-[#f7340d] border border-transparent px-4 py-2 text-xs font-semibold text-white uppercase tracking-widest hover:bg-[#d92c0a] transition disabled:opacity-40 disabled:cursor-not-allowed">Kirim</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif

    @if ($showMemberPanel)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-xl max-h-[85vh] flex flex-col overflow-hidden">
                <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4">
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-gray-900 truncate">
                            @if (filled($memberPanelEmployeeId) && count($memberPanelMembers))
                                {{ $memberPanelMembers[0]['full_name'] }}
                            @elseif ($memberPanelGroupName !== '')
                                {{ $memberPanelGroupName }}
                            @else
                                Anggota
                            @endif
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5 tabular-nums">
                            {{ \Illuminate\Support\Carbon::parse($memberPanelDate, \App\Support\AppTimezone::display())->translatedFormat('l, d M Y') }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeMemberPanel" class="shrink-0 rounded-md border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50">Tutup</button>
                </div>
                <ul class="divide-y divide-gray-100 overflow-y-auto px-5 py-2">
                    @forelse ($memberPanelMembers as $m)
                        @php
                            $isLibur = in_array((string) $m['id'], $memberPanelLiburIds, true);
                            $ov = $memberPanelOverrides[(string) $m['id']] ?? null;
                        @endphp
                        <li class="py-3" wire:key="member-panel-{{ $m['id'] }}">
                            <div class="flex items-start gap-2 min-w-0">
                                <p class="flex-1 min-w-0 text-sm font-medium text-gray-900 truncate" title="{{ $m['full_name'] }}">
                                    {{ $m['full_name'] }}
                                </p>
                                @if ($isLibur)
                                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 whitespace-nowrap">Libur Rutin</span>
                                @elseif ($ov)
                                    @php
                                        $fromGroupName = $memberPanelPlacementGroups[(string) $m['id']] ?? null;
                                        $fromScheduleName = $memberPanelPlacementSchedules[(string) $m['id']] ?? null;
                                        $toScheduleName = $ov->schedule?->name;
                                        $fromLabel = $fromGroupName && $fromScheduleName
                                            ? $fromGroupName.' : '.$fromScheduleName
                                            : $fromScheduleName;
                                    @endphp
                                    <span class="shrink-0 inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 max-w-[70%]"
                                        title="Tukar shift{{ $fromLabel ? ': '.$fromLabel.' → '.$toScheduleName : ' → '.$toScheduleName }}">
                                        @if ($fromLabel)
                                            <span class="truncate">{{ $fromLabel }}</span>
                                        @endif
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="truncate">{{ $toScheduleName }}</span>
                                    </span>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="py-8 text-center text-sm text-gray-500">Tidak ada anggota aktif di group ini.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endif
</div>
