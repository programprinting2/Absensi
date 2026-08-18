<?php

namespace App\Services;

use App\Jobs\SyncRepeatingShiftPatternCellJob;
use App\Models\Employee;
use App\Models\ShiftCalendarEntry;
use App\Models\ShiftDaySetting;
use App\Models\ShiftEmployeeLibur;
use App\Models\ShiftEmployeeShiftOverride;
use App\Models\ShiftGroup;
use App\Models\ShiftGroupMember;
use App\Models\ShiftScheduleTemplate;
use App\Models\ShiftSwapRequest;
use App\Models\WorkSchedule;
use App\Support\AppTimezone;
use App\Support\IndonesianHolidays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShiftCalendarService
{
    private bool $suppressAudit = false;

    private bool $suppressRepeatingSync = false;

    private const REPEATING_MONTHS_AHEAD = 12;

    private function audit(string $description, string $action, array $context = []): void
    {
        if ($this->suppressAudit) {
            return;
        }

        ActivityLogger::normal($description, $action, $context);
    }
    /**
     * Blok 4 minggu mulai Senin yang berisi $anchor (atau Senin minggu ini).
     *
     * @return array{start: string, end: string, weeks: list<list<string>>}
     */
    /** Senin acuan grid blok 4 minggu (28 hari tetap, tidak bergeser tiap minggu). */
    private const BLOCK_GRID_EPOCH = '2020-01-06';

    /**
     * Senin awal blok 4 minggu yang memuat $date (grid 28 hari, bukan Senin minggu berjalan).
     */
    public function blockStartContainingDate(?string $date = null): string
    {
        $tz = AppTimezone::display();
        $day = $date
            ? Carbon::parse($date, $tz)->startOfDay()
            : AppTimezone::nowDisplay()->startOfDay();

        $epoch = Carbon::parse(self::BLOCK_GRID_EPOCH, $tz)->startOfDay();
        $daysSince = (int) $epoch->diffInDays($day);
        $blockIndex = intdiv($daysSince, 28);

        return $epoch->copy()->addDays($blockIndex * 28)->toDateString();
    }

    public function fourWeekBlock(?string $anchorDate = null): array
    {
        $tz = AppTimezone::display();
        $start = Carbon::parse(
            $this->blockStartContainingDate($anchorDate),
            $tz,
        )->startOfDay();
        $weeks = [];
        $cursor = $start->copy();
        for ($w = 0; $w < 4; $w++) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $week[] = $cursor->toDateString();
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return [
            'start' => $start->toDateString(),
            'end' => $cursor->copy()->subDay()->toDateString(),
            'weeks' => $weeks,
        ];
    }

    public function shiftBlock(string $currentStart, int $direction): string
    {
        $tz = AppTimezone::display();

        return Carbon::parse($currentStart, $tz)
            ->addWeeks($direction * 4)
            ->toDateString();
    }

    /**
     * Grid kalender satu bulan (Senin–Minggu) termasuk hari padding bulan lain.
     *
     * @return array{
     *   start: string,
     *   end: string,
     *   month_start: string,
     *   month_end: string,
     *   year: int,
     *   month: int,
     *   weeks: list<list<string>>
     * }
     */
    public function calendarMonth(int $year, int $month): array
    {
        $tz = AppTimezone::display();
        $firstOfMonth = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->startOfDay();
        $lastOfMonth = $firstOfMonth->copy()->endOfMonth();
        $start = $firstOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $end = $lastOfMonth->copy()->endOfWeek(Carbon::SUNDAY);

        $weeks = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $week[] = $cursor->toDateString();
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'month_start' => $firstOfMonth->toDateString(),
            'month_end' => $lastOfMonth->toDateString(),
            'year' => $year,
            'month' => $month,
            'weeks' => $weeks,
        ];
    }

    /**
     * @return array{year: int, month: int}
     */
    public function shiftMonth(int $year, int $month, int $direction): array
    {
        $tz = AppTimezone::display();
        $cursor = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->addMonths($direction);

        return [
            'year' => (int) $cursor->year,
            'month' => (int) $cursor->month,
        ];
    }

    public function placeGroupOnDate(string $groupId, string $scheduleId, string $workDate): ShiftCalendarEntry
    {
        $existing = ShiftCalendarEntry::query()
            ->where('group_id', $groupId)
            ->whereDate('work_date', $workDate)
            ->where('work_schedule_id', $scheduleId)
            ->first();

        if ($existing) {
            return $existing->load(['group', 'schedule']);
        }

        // Satu group hanya di satu sif per hari — pindahkan jika sudah ada di sif lain
        ShiftCalendarEntry::query()
            ->where('group_id', $groupId)
            ->whereDate('work_date', $workDate)
            ->where('work_schedule_id', '!=', $scheduleId)
            ->delete();

        $maxSort = (int) ShiftCalendarEntry::query()
            ->whereDate('work_date', $workDate)
            ->where('work_schedule_id', $scheduleId)
            ->max('sort_order');

        $entry = ShiftCalendarEntry::query()->create([
            'work_date' => $workDate,
            'work_schedule_id' => $scheduleId,
            'group_id' => $groupId,
            'employee_id' => null,
            'sort_order' => $maxSort + 1,
            'created_at' => now(),
        ])->load(['group', 'schedule']);

        $this->audit(
            'Menempatkan group di kalender '.$workDate,
            'shift.calendar.place',
            ['group_id' => $groupId, 'schedule_id' => $scheduleId, 'work_date' => $workDate],
        );

        $this->syncRepeatingPatternIfAnchorDate($workDate);

        return $entry;
    }

    public function removeGroupFromDate(string $groupId, string $workDate, ?string $scheduleId = null): void
    {
        $q = ShiftCalendarEntry::query()
            ->where('group_id', $groupId)
            ->whereDate('work_date', $workDate);

        if ($scheduleId) {
            $q->where('work_schedule_id', $scheduleId);
        }

        $q->delete();
        $this->audit(
            'Menghapus group dari kalender '.$workDate,
            'shift.calendar.remove',
            ['group_id' => $groupId, 'schedule_id' => $scheduleId, 'work_date' => $workDate],
        );

        $this->syncRepeatingPatternIfAnchorDate($workDate);
    }

    public function moveGroupOnCalendar(
        string $groupId,
        string $fromDate,
        string $fromScheduleId,
        string $toDate,
        string $toScheduleId,
    ): void {
        $previousSuppressRepeatingSync = $this->suppressRepeatingSync;
        $this->suppressRepeatingSync = true;

        try {
            DB::transaction(function () use ($groupId, $fromDate, $fromScheduleId, $toDate, $toScheduleId) {
                $this->placeGroupOnDate($groupId, $toScheduleId, $toDate);
                if ($fromDate !== $toDate) {
                    $this->removeGroupFromDate($groupId, $fromDate, $fromScheduleId);
                }
            });
        } finally {
            $this->suppressRepeatingSync = $previousSuppressRepeatingSync;
        }

        $this->syncRepeatingPatternIfAnchorDate($toDate);
        if ($fromDate !== $toDate) {
            $this->syncRepeatingPatternIfAnchorDate($fromDate);
        }
    }

    public function placeEmployeeOnDate(string $employeeId, string $scheduleId, string $workDate): ShiftCalendarEntry
    {
        $existing = ShiftCalendarEntry::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $workDate)
            ->where('work_schedule_id', $scheduleId)
            ->first();

        if ($existing) {
            return $existing->load(['employee', 'schedule']);
        }

        // Satu karyawan hanya di satu sif per hari
        ShiftCalendarEntry::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $workDate)
            ->where('work_schedule_id', '!=', $scheduleId)
            ->delete();

        $maxSort = (int) ShiftCalendarEntry::query()
            ->whereDate('work_date', $workDate)
            ->where('work_schedule_id', $scheduleId)
            ->max('sort_order');

        $entry = ShiftCalendarEntry::query()->create([
            'work_date' => $workDate,
            'work_schedule_id' => $scheduleId,
            'group_id' => null,
            'employee_id' => $employeeId,
            'sort_order' => $maxSort + 1,
            'created_at' => now(),
        ])->load(['employee', 'schedule']);

        $this->audit(
            'Menempatkan karyawan di kalender '.$workDate,
            'shift.calendar.place_employee',
            ['employee_id' => $employeeId, 'schedule_id' => $scheduleId, 'work_date' => $workDate],
        );

        $this->syncRepeatingPatternIfAnchorDate($workDate);

        return $entry;
    }

    public function removeEmployeeFromDate(string $employeeId, string $workDate, ?string $scheduleId = null): void
    {
        $q = ShiftCalendarEntry::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $workDate);

        if ($scheduleId) {
            $q->where('work_schedule_id', $scheduleId);
        }

        $q->delete();
        $this->audit(
            'Menghapus karyawan dari kalender '.$workDate,
            'shift.calendar.remove_employee',
            ['employee_id' => $employeeId, 'schedule_id' => $scheduleId, 'work_date' => $workDate],
        );

        $this->syncRepeatingPatternIfAnchorDate($workDate);
    }

    public function moveEmployeeOnCalendar(
        string $employeeId,
        string $fromDate,
        string $fromScheduleId,
        string $toDate,
        string $toScheduleId,
    ): void {
        $previousSuppressRepeatingSync = $this->suppressRepeatingSync;
        $this->suppressRepeatingSync = true;

        try {
            DB::transaction(function () use ($employeeId, $fromDate, $fromScheduleId, $toDate, $toScheduleId) {
                $this->placeEmployeeOnDate($employeeId, $toScheduleId, $toDate);
                if ($fromDate !== $toDate) {
                    $this->removeEmployeeFromDate($employeeId, $fromDate, $fromScheduleId);
                }
            });
        } finally {
            $this->suppressRepeatingSync = $previousSuppressRepeatingSync;
        }

        $this->syncRepeatingPatternIfAnchorDate($toDate);
        if ($fromDate !== $toDate) {
            $this->syncRepeatingPatternIfAnchorDate($fromDate);
        }
    }

    public function removeEntry(string $entryId): void
    {
        $entry = ShiftCalendarEntry::query()->whereKey($entryId)->first();
        if (! $entry) {
            return;
        }

        $workDate = $entry->work_date->toDateString();
        $entry->delete();

        $this->syncRepeatingPatternIfAnchorDate($workDate);
    }

    public function setCompanyHoliday(string $workDate, string $kind, bool $on = true): void
    {
        $row = ShiftDaySetting::query()->firstOrNew(['work_date' => $workDate]);
        $row->is_company_holiday = $on;
        $row->holiday_kind = $on ? $kind : null;
        $row->created_at ??= now();
        $row->updated_at = now();
        $row->save();

        $this->audit(
            ($on ? 'Set' : 'Lepas').' libur perusahaan '.$workDate.($on ? ' ('.$kind.')' : ''),
            'shift.holiday.set',
            ['work_date' => $workDate, 'kind' => $kind, 'on' => $on],
        );

        $this->syncRepeatingPatternIfAnchorDate($workDate);
    }

    /**
     * Libur rutin: set weekday di seluruh minggu dalam rentang blok.
     *
     * @param  list<string>  $datesInBlock
     */
    public function setRoutineHolidayForWeekday(array $datesInBlock, int $isoWeekday, bool $on = true): void
    {
        foreach ($datesInBlock as $date) {
            $carbon = Carbon::parse($date, AppTimezone::display());
            if ((int) $carbon->dayOfWeekIso !== $isoWeekday) {
                continue;
            }
            $this->setCompanyHoliday($date, ShiftDaySetting::HOLIDAY_ROUTINE, $on);
        }
    }

    public function setDayDurations(string $workDate, ?int $workMinutes, ?int $breakMinutes): void
    {
        $row = ShiftDaySetting::query()->firstOrNew(['work_date' => $workDate]);
        $row->work_duration_minutes = $workMinutes;
        $row->break_duration_minutes = $breakMinutes;
        $row->created_at ??= now();
        $row->updated_at = now();
        $row->save();

        $this->audit(
            'Override durasi hari '.$workDate,
            'shift.day.durations',
            [
                'work_date' => $workDate,
                'work_minutes' => $workMinutes,
                'break_minutes' => $breakMinutes,
            ],
        );

        $this->syncRepeatingPatternIfAnchorDate($workDate);
    }

    public function toggleEmployeeLibur(string $employeeId, string $workDate, string $source = 'pattern'): bool
    {
        $existing = ShiftEmployeeLibur::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $workDate)
            ->first();

        if ($existing) {
            $existing->delete();
            $this->audit(
                'Lepas libur rutin '.$workDate,
                'shift.libur.clear',
                ['employee_id' => $employeeId, 'work_date' => $workDate],
            );

            if ($existing->source === 'pattern') {
                $this->syncRepeatingPatternIfAnchorDate($workDate);
            }

            return false;
        }

        ShiftEmployeeLibur::query()->create([
            'employee_id' => $employeeId,
            'work_date' => $workDate,
            'source' => $source,
            'created_at' => now(),
        ]);

        $this->audit(
            'Set libur rutin '.$workDate,
            'shift.libur.set',
            ['employee_id' => $employeeId, 'work_date' => $workDate],
        );

        if ($source === 'pattern') {
            $this->syncRepeatingPatternIfAnchorDate($workDate);
        }

        return true;
    }

    public function setShiftOverride(string $employeeId, string $workDate, string $scheduleId, ?string $reason = null): void
    {
        ShiftEmployeeShiftOverride::query()->updateOrCreate(
            [
                'employee_id' => $employeeId,
                'work_date' => $workDate,
            ],
            [
                'work_schedule_id' => $scheduleId,
                'reason' => $reason,
                'created_at' => now(),
            ]
        );

        $this->audit(
            'Override tukar sif '.$workDate,
            'shift.override.set',
            ['employee_id' => $employeeId, 'work_date' => $workDate, 'schedule_id' => $scheduleId],
        );
    }

    public function clearShiftOverride(string $employeeId, string $workDate): void
    {
        ShiftEmployeeShiftOverride::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $workDate)
            ->delete();
    }

    /**
     * Payload board untuk UI kalender.
     *
     * @return array{
     *   weeks: list<list<array>>,
     *   nationalHolidays: array<string, array{name: string, is_joint_leave: bool}>,
     *   schedules: Collection,
     *   poolGroups: Collection,
     *   poolEmployees: Collection
     * }
     */
    public function boardPayload(
        string $viewMode = 'block',
        ?string $blockStart = null,
        ?int $viewYear = null,
        ?int $viewMonth = null,
        bool $planningMode = false,
    ): array {
        $todayCarbon = AppTimezone::nowDisplay();
        $block = $viewMode === 'month'
            ? $this->calendarMonth(
                $viewYear ?? (int) $todayCarbon->year,
                $viewMonth ?? (int) $todayCarbon->month,
            )
            : $this->fourWeekBlock($blockStart);
        $allDates = collect($block['weeks'])->flatten()->values();
        $start = $block['start'];
        $end = $block['end'];

        $schedules = WorkSchedule::query()
            ->enabled()
            ->orderBy('clock_in_time')
            ->orderBy('name')
            ->get();
        $groups = ShiftGroup::query()
            ->where('is_system_unassigned', false)
            ->where('is_solo', false)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $entries = ShiftCalendarEntry::query()
            ->with(['group', 'employee', 'schedule'])
            ->whereBetween('work_date', [$start, $end])
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn ($e) => $e->work_date->toDateString());

        $daySettings = ShiftDaySetting::query()
            ->whereBetween('work_date', [$start, $end])
            ->get()
            ->keyBy(fn ($r) => $r->work_date->toDateString());

        $liburRows = ShiftEmployeeLibur::query()
            ->with('employee')
            ->whereBetween('work_date', [$start, $end])
            ->get()
            ->groupBy(fn ($r) => $r->work_date->toDateString());

        $overrideRows = ShiftEmployeeShiftOverride::query()
            ->with(['employee', 'schedule'])
            ->whereBetween('work_date', [$start, $end])
            ->get()
            ->groupBy(fn ($r) => $r->work_date->toDateString());

        $pendingSwapRows = ShiftSwapRequest::query()
            ->with(['employee', 'toSchedule'])
            ->whereBetween('work_date', [$start, $end])
            ->where('status', ShiftSwapRequest::STATUS_PENDING)
            ->get()
            ->groupBy(fn ($r) => $r->work_date->toDateString());

        $leaveService = app(LeaveService::class);
        $employeeIds = Employee::query()->where('is_active', true)->pluck('id');
        $leaveMap = $leaveService->approvedLeavesByEmployeeDate($employeeIds, $start, $end);
        $leaveEmployees = Employee::query()
            ->whereIn('id', array_keys($leaveMap))
            ->get(['id', 'full_name'])
            ->keyBy(fn (Employee $e) => (string) $e->id);

        $years = $allDates->map(fn ($d) => (int) substr($d, 0, 4))->unique()->values()->all();
        $national = IndonesianHolidays::forYears($years);

        $groupService = app(ShiftGroupService::class);
        $today = AppTimezone::nowDisplay()->toDateString();
        $membershipsByGroup = $this->membershipsByGroup($start, $end);

        $weeksUi = [];
        foreach ($block['weeks'] as $weekDates) {
            $weekUi = [];
            foreach ($weekDates as $date) {
                $setting = $daySettings->get($date);
                $isHoliday = (bool) ($setting?->is_company_holiday);
                $chipsBySchedule = [];
                foreach ($schedules as $schedule) {
                    $chipsBySchedule[$schedule->id] = [];
                }

                $dayOverrides = $overrideRows->get($date, collect());
                $overrideByEmployee = $dayOverrides->keyBy(fn ($o) => (string) $o->employee_id);
                $pendingSwaps = $pendingSwapRows->get($date, collect());
                $pendingByEmployee = $pendingSwaps->keyBy(fn ($p) => (string) $p->employee_id);
                $patternLiburIds = $liburRows->get($date, collect())
                    ->pluck('employee_id')
                    ->map(fn ($id) => (string) $id);
                $onLeaveEmployeeIds = $this->employeesOnLeaveForDate(
                    $date,
                    $liburRows->get($date, collect()),
                    $leaveMap,
                );
                if ($planningMode) {
                    // Mode atur: hitungan pola bersih (libur rutin saja, tanpa request/cuti).
                    $onLeaveForDisplay = $patternLiburIds;
                } else {
                    // Mode lihat: hari lampau roster penuh; hari ini+ operasional.
                    $onLeaveForDisplay = $date < $today ? collect() : $onLeaveEmployeeIds;
                }

                foreach ($entries->get($date, collect()) as $entry) {
                    if ($entry->employee_id) {
                        $employeeId = (string) $entry->employee_id;

                        if ($this->employeeOnLeave($employeeId, $onLeaveForDisplay)) {
                            continue;
                        }

                        if (! $planningMode && $this->employeeRelocatedFromSchedule(
                            $employeeId,
                            (string) $entry->work_schedule_id,
                            $overrideByEmployee,
                            $pendingByEmployee,
                        )) {
                            continue;
                        }

                        $chipsBySchedule[$entry->work_schedule_id][] = [
                            'entry_id' => $entry->id,
                            'kind' => 'employee',
                            'employee_id' => $entry->employee_id,
                            'name' => $entry->employee?->full_name ?? '—',
                            'color' => $groupService->colorForName($entry->employee?->full_name ?? ''),
                        ];

                        continue;
                    }

                    if (! $entry->group_id) {
                        continue;
                    }

                    $chipsBySchedule[$entry->work_schedule_id][] = [
                        'entry_id' => $entry->id,
                        'kind' => 'group',
                        'group_id' => $entry->group_id,
                        'name' => $entry->group?->name ?? '—',
                        'color' => $entry->group?->color ?? '#64748b',
                        'member_count' => $this->countGroupMembersOnSchedule(
                            $membershipsByGroup,
                            (string) $entry->group_id,
                            $date,
                            (string) $entry->work_schedule_id,
                            $overrideByEmployee,
                            $pendingByEmployee,
                            $onLeaveForDisplay,
                            $today,
                            $planningMode,
                        ),
                    ];
                }

                if (! $planningMode) {
                foreach ($dayOverrides as $ov) {
                    if (! $ov->employee) {
                        continue;
                    }

                    if ($this->employeeOnLeave((string) $ov->employee_id, $onLeaveForDisplay)) {
                        continue;
                    }

                    $alreadyOnSchedule = collect($chipsBySchedule[$ov->work_schedule_id] ?? [])
                        ->contains(fn (array $c) => ($c['employee_id'] ?? null) === $ov->employee_id);
                    if ($alreadyOnSchedule) {
                        continue;
                    }

                    $chipsBySchedule[$ov->work_schedule_id][] = [
                        'entry_id' => null,
                        'kind' => 'override',
                        'employee_id' => $ov->employee_id,
                        'name' => $ov->employee->full_name,
                        'color' => $groupService->colorForName($ov->employee->full_name),
                    ];
                }

                foreach ($pendingSwaps as $req) {
                    if ($overrideByEmployee->has((string) $req->employee_id) || ! $req->employee) {
                        continue;
                    }

                    if ($this->employeeOnLeave((string) $req->employee_id, $onLeaveForDisplay)) {
                        continue;
                    }

                    $alreadyOnSchedule = collect($chipsBySchedule[$req->to_work_schedule_id] ?? [])
                        ->contains(fn (array $c) => ($c['employee_id'] ?? null) === $req->employee_id);
                    if ($alreadyOnSchedule) {
                        continue;
                    }

                    $chipsBySchedule[$req->to_work_schedule_id][] = [
                        'entry_id' => null,
                        'kind' => 'swap_pending',
                        'employee_id' => $req->employee_id,
                        'name' => $req->employee->full_name,
                        'color' => $groupService->colorForName($req->employee->full_name),
                    ];
                }
                }

                $foot = [];
                foreach ($liburRows->get($date, collect()) as $libur) {
                    if (! $libur->employee) {
                        continue;
                    }
                    $foot[] = [
                        'employee_id' => $libur->employee_id,
                        'name' => $libur->employee->full_name,
                        'badge' => 'Libur Rutin',
                        'badge_kind' => 'libur_karyawan',
                    ];
                }
                if (! $planningMode) {
                    foreach ($leaveMap as $empId => $days) {
                        if (empty($days[$date])) {
                            continue;
                        }
                        $emp = $leaveEmployees->get((string) $empId);
                        if (! $emp) {
                            continue;
                        }
                        $foot[] = [
                            'employee_id' => $empId,
                            'name' => $emp->full_name,
                            'badge' => 'Libur request',
                            'badge_kind' => 'libur_request',
                        ];
                    }
                }

                $monthStart = $block['month_start'] ?? $block['start'];
                $monthEnd = $block['month_end'] ?? $block['end'];

                $weekUi[] = [
                    'date' => $date,
                    'day' => (int) Carbon::parse($date)->format('j'),
                    'in_month' => $date >= $monthStart && $date <= $monthEnd,
                    'is_today' => $date === $today,
                    'is_holiday' => $isHoliday,
                    'holiday_kind' => $setting?->holiday_kind,
                    'work_duration_minutes' => $setting?->work_duration_minutes,
                    'break_duration_minutes' => $setting?->break_duration_minutes,
                    'national' => $national[$date] ?? null,
                    'chips' => $chipsBySchedule,
                    'foot' => $foot,
                ];
            }
            $weeksUi[] = $weekUi;
        }

        $unassigned = $groupService->ensureUnassigned();
        $poolEmployees = collect($groupService->membersOnDate($unassigned, $today))
            ->map(fn (array $pe) => array_merge($pe, [
                'color' => $groupService->colorForName($pe['full_name']),
            ]));

        $poolGroups = $groups->values()->map(fn (ShiftGroup $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'color' => $g->color,
            'member_count' => $this->countMembersOnDate($membershipsByGroup, (string) $g->id, $today),
        ]);

        return [
            'block' => $block,
            'weeks' => $weeksUi,
            'view_mode' => $viewMode,
            'nationalHolidays' => $national,
            'schedules' => $schedules,
            'poolGroups' => $poolGroups,
            'poolEmployees' => $poolEmployees,
            'today' => $today,
        ];
    }

    /**
     * Simpan blok saat ini sebagai template.
     */
    public function saveTemplateFromBlock(string $name, string $blockStart, bool $asDefault = false): ShiftScheduleTemplate
    {
        $payload = $this->buildTemplatePayloadFromBlock($blockStart);

        return DB::transaction(function () use ($name, $payload, $asDefault) {
            $tpl = ShiftScheduleTemplate::query()->create([
                'name' => $name,
                'is_default' => $asDefault,
                'payload' => $payload,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit(
                'Menyimpan template pola "'.$name.'"',
                'shift.template.save',
                ['template_id' => $tpl->id, 'as_default' => $asDefault],
            );

            return $tpl;
        });
    }

    public function updateTemplateFromBlock(ShiftScheduleTemplate $template, string $blockStart): void
    {
        $anchorStart = $template->is_default ? ($template->payload['anchor_start'] ?? null) : null;
        $payloadSource = $anchorStart ?? $blockStart;
        $payload = $this->buildTemplatePayloadFromBlock($payloadSource);
        if ($anchorStart) {
            $payload['anchor_start'] = $anchorStart;
        }

        DB::transaction(function () use ($template, $payload, $anchorStart, $blockStart) {
            $template->update([
                'payload' => $payload,
                'updated_at' => now(),
            ]);

            if ($anchorStart !== null && $blockStart === $anchorStart) {
                $this->rematerializeRepeatingFuture($template->fresh());
            }
        });

        $this->audit(
            'Memperbarui template pola "'.$template->name.'"',
            'shift.template.update',
            ['template_id' => $template->id],
        );
    }

    /**
     * @return array{version: int, weeks: list<list<array>>}
     */
    private function buildTemplatePayloadFromBlock(string $blockStart): array
    {
        return $this->buildTemplatePayloadFromView('block', $blockStart, null, null);
    }

    /**
     * @return array{version: int, weeks: list<list<array>>}
     */
    private function buildTemplatePayloadFromView(
        string $viewMode,
        ?string $blockStart,
        ?int $viewYear,
        ?int $viewMonth,
    ): array {
        $board = $this->boardPayload($viewMode, $blockStart, $viewYear, $viewMonth, true);
        $payload = [
            'version' => 1,
            'weeks' => [],
        ];

        foreach ($board['weeks'] as $week) {
            $weekPayload = [];
            foreach ($week as $di => $cell) {
                $weekPayload[] = [
                    'weekday' => $di + 1, // 1=Mon
                    'is_holiday' => $cell['is_holiday'],
                    'holiday_kind' => $cell['holiday_kind'],
                    'work_duration_minutes' => $cell['work_duration_minutes'],
                    'break_duration_minutes' => $cell['break_duration_minutes'],
                    'entries' => collect($cell['chips'])->map(function ($chips, $scheduleId) {
                        return collect($chips)->map(function ($c) use ($scheduleId) {
                            $row = ['work_schedule_id' => $scheduleId];
                            if (($c['kind'] ?? '') === 'employee') {
                                $row['employee_id'] = $c['employee_id'];
                            } else {
                                $row['group_id'] = $c['group_id'];
                            }

                            return $row;
                        })->all();
                    })->flatten(1)->values()->all(),
                    'employee_libur' => collect($cell['foot'])
                        ->where('badge_kind', 'libur_karyawan')
                        ->pluck('employee_id')
                        ->values()
                        ->all(),
                ];
            }
            $payload['weeks'][] = $weekPayload;
        }

        return $payload;
    }

    public function saveTemplateFromCurrentView(
        string $name,
        string $viewMode,
        ?string $blockStart,
        ?int $viewYear,
        ?int $viewMonth,
        bool $asDefault = false,
    ): ShiftScheduleTemplate {
        $payload = $this->buildTemplatePayloadFromView($viewMode, $blockStart, $viewYear, $viewMonth);

        return DB::transaction(function () use ($name, $payload, $asDefault) {
            $tpl = ShiftScheduleTemplate::query()->create([
                'name' => $name,
                'is_default' => $asDefault,
                'payload' => $payload,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit(
                'Menyimpan template pola "'.$name.'"',
                'shift.template.save',
                ['template_id' => $tpl->id, 'as_default' => $asDefault],
            );

            return $tpl;
        });
    }

    public function updateTemplateFromCurrentView(
        ShiftScheduleTemplate $template,
        string $viewMode,
        ?string $blockStart,
        ?int $viewYear,
        ?int $viewMonth,
    ): void {
        $anchorStart = $template->is_default ? ($template->payload['anchor_start'] ?? null) : null;
        if ($anchorStart) {
            $payload = $this->buildTemplatePayloadFromBlock($anchorStart);
            $payload['anchor_start'] = $anchorStart;
            $compareAnchor = $blockStart;
        } else {
            $payload = $this->buildTemplatePayloadFromView($viewMode, $blockStart, $viewYear, $viewMonth);
            $compareAnchor = $viewMode === 'month'
                ? $this->calendarMonth($viewYear ?? (int) date('Y'), $viewMonth ?? (int) date('n'))['month_start']
                : $blockStart;
        }

        DB::transaction(function () use ($template, $payload, $anchorStart, $compareAnchor) {
            $template->update([
                'payload' => $payload,
                'updated_at' => now(),
            ]);

            if ($anchorStart !== null && $compareAnchor === $anchorStart) {
                $this->rematerializeRepeatingFuture($template->fresh());
            }
        });

        $this->audit(
            'Memperbarui template pola "'.$template->name.'"',
            'shift.template.update',
            ['template_id' => $template->id],
        );
    }

    public function applyTemplateToCurrentView(
        ShiftScheduleTemplate $template,
        string $viewMode,
        ?string $blockStart,
        ?int $viewYear,
        ?int $viewMonth,
        bool $preserveEvents = true,
    ): void {
        $block = $viewMode === 'month'
            ? $this->calendarMonth($viewYear ?? (int) date('Y'), $viewMonth ?? (int) date('n'))
            : $this->fourWeekBlock($blockStart);
        $payload = $template->payload ?? [];
        $weeks = $payload['weeks'] ?? [];

        $previousSuppressAudit = $this->suppressAudit;
        $previousSuppressRepeatingSync = $this->suppressRepeatingSync;
        $this->suppressAudit = true;
        $this->suppressRepeatingSync = true;

        try {
            DB::transaction(function () use ($block, $weeks, $preserveEvents) {
                foreach ($block['weeks'] as $wi => $weekDates) {
                    $weekPayload = $weeks[$wi] ?? [];
                    foreach ($weekDates as $di => $date) {
                        $cell = $weekPayload[$di] ?? null;
                        if (! $cell) {
                            continue;
                        }

                        $this->applyPatternCellToDate($cell, $date, $preserveEvents);
                    }
                }
            });
        } finally {
            $this->suppressAudit = $previousSuppressAudit;
            $this->suppressRepeatingSync = $previousSuppressRepeatingSync;
        }

        $label = $viewMode === 'month'
            ? sprintf('bulan %02d/%d', $viewMonth, $viewYear)
            : 'blok '.$blockStart;

        $this->audit(
            'Memuat template "'.$template->name.'" ke '.$label,
            'shift.template.apply',
            ['template_id' => $template->id, 'view_mode' => $viewMode],
        );
    }

    public function applyTemplate(ShiftScheduleTemplate $template, string $blockStart, bool $preserveEvents = true): void
    {
        $this->applyTemplateToCurrentView($template, 'block', $blockStart, null, null, $preserveEvents);
    }

    public function deactivateRepeatingPattern(
        ShiftScheduleTemplate $template,
        int $monthsAhead = self::REPEATING_MONTHS_AHEAD,
    ): void {
        if (! $template->is_default) {
            return;
        }

        $anchorStart = $template->payload['anchor_start'] ?? null;

        DB::transaction(function () use ($template, $anchorStart, $monthsAhead) {
            if (is_string($anchorStart) && $anchorStart !== '') {
                $this->clearFutureAfterAnchorBlock($anchorStart, $monthsAhead);
            }

            $template->update(['is_default' => false, 'updated_at' => now()]);
        });

        $this->audit(
            'Menonaktifkan pola berulang "'.$template->name.'" dan mengosongkan blok mendatang',
            'shift.template.repeating.deactivate',
            ['template_id' => $template->id, 'anchor_start' => $anchorStart],
        );
    }

    /**
     * Simpan pola 4 minggu saat ini sebagai template berulang dan terapkan ke bulan-bulan berikutnya.
     * Jadwal manual di blok mendatang diganti pola acuan. Override tukar sif & libur event tetap di tanggal asalnya.
     */
    public function applyRepeatingPattern(
        ShiftScheduleTemplate $template,
        string $blockStart,
        int $monthsAhead = self::REPEATING_MONTHS_AHEAD,
    ): void {
        $payload = $this->buildTemplatePayloadFromBlock($blockStart);
        $payload['anchor_start'] = $blockStart;

        DB::transaction(function () use ($template, $payload, $blockStart, $monthsAhead) {
            ShiftScheduleTemplate::query()
                ->whereKeyNot($template->getKey())
                ->where('is_default', true)
                ->update([
                    'is_default' => false,
                    'updated_at' => now(),
                ]);

            $template->update([
                'payload' => $payload,
                'is_default' => true,
                'updated_at' => now(),
            ]);

            // Kosongkan dulu lalu terapkan pola — mengganti penempatan manual di blok mendatang.
            $this->clearFutureAfterAnchorBlock($blockStart, $monthsAhead);
            $this->rematerializeRepeatingFuture($template->fresh(), $monthsAhead);
        });

        $this->audit(
            'Mengaktifkan pola berulang "'.$template->name.'" dari blok '.$blockStart,
            'shift.template.repeating',
            ['template_id' => $template->id, 'block_start' => $blockStart, 'months_ahead' => $monthsAhead],
        );
    }

    /**
     * Setelah edit di blok acuan pola berulang, perbarui payload template & terapkan ulang ke bulan-bulan berikutnya.
     */
    public function syncRepeatingPatternIfAnchorDate(
        string $workDate,
        int $monthsAhead = self::REPEATING_MONTHS_AHEAD,
    ): void {
        if ($this->suppressRepeatingSync) {
            return;
        }

        $templates = ShiftScheduleTemplate::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->get();

        foreach ($templates as $template) {
            $anchorStart = $template->payload['anchor_start'] ?? null;
            if (! $anchorStart || ! $this->dateInBlock($workDate, $anchorStart)) {
                continue;
            }

            $payload = $this->refreshTemplatePatternCell($template, $workDate, $anchorStart);

            DB::transaction(function () use ($template, $payload) {
                $template->update([
                    'payload' => $payload,
                    'updated_at' => now(),
                ]);
            });

            SyncRepeatingShiftPatternCellJob::dispatch(
                (string) $template->getKey(),
                $workDate,
                $monthsAhead,
            )->afterCommit();

            $this->audit(
                'Menjadwalkan sinkronisasi pola berulang "'.$template->name.'" setelah perubahan di blok acuan',
                'shift.template.repeating.sync_queued',
                ['template_id' => $template->id, 'anchor_start' => $anchorStart],
            );
        }
    }

    /**
     * Perbarui data penempatan/libur pada satu tanggal acuan tanpa membangun
     * ulang payload seluruh blok 4 minggu.
     */
    private function refreshTemplatePatternCell(
        ShiftScheduleTemplate $template,
        string $workDate,
        string $anchorStart,
    ): array {
        $payload = $template->payload ?? [];
        $anchor = Carbon::parse($anchorStart, AppTimezone::display())->startOfDay();
        $date = Carbon::parse($workDate, AppTimezone::display())->startOfDay();
        $offset = (int) $anchor->diffInDays($date);
        $weekIndex = intdiv($offset, 7);
        $dayIndex = $offset % 7;

        if (! isset($payload['weeks'][$weekIndex][$dayIndex])) {
            $payload = $this->buildTemplatePayloadFromBlock($anchorStart);
        }

        $entries = ShiftCalendarEntry::query()
            ->whereDate('work_date', $workDate)
            ->orderBy('sort_order')
            ->get()
            ->map(function (ShiftCalendarEntry $entry): array {
                $row = ['work_schedule_id' => $entry->work_schedule_id];
                if ($entry->employee_id) {
                    $row['employee_id'] = $entry->employee_id;
                } else {
                    $row['group_id'] = $entry->group_id;
                }

                return $row;
            })
            ->values()
            ->all();

        $employeeLibur = ShiftEmployeeLibur::query()
            ->whereDate('work_date', $workDate)
            ->where('source', 'pattern')
            ->pluck('employee_id')
            ->values()
            ->all();
        $daySetting = ShiftDaySetting::query()
            ->whereDate('work_date', $workDate)
            ->first();

        $payload['weeks'][$weekIndex][$dayIndex]['entries'] = $entries;
        $payload['weeks'][$weekIndex][$dayIndex]['employee_libur'] = $employeeLibur;
        $payload['weeks'][$weekIndex][$dayIndex]['is_holiday'] = (bool) ($daySetting?->is_company_holiday);
        $payload['weeks'][$weekIndex][$dayIndex]['holiday_kind'] = $daySetting?->holiday_kind;
        $payload['weeks'][$weekIndex][$dayIndex]['work_duration_minutes'] = $daySetting?->work_duration_minutes;
        $payload['weeks'][$weekIndex][$dayIndex]['break_duration_minutes'] = $daySetting?->break_duration_minutes;
        $payload['anchor_start'] = $anchorStart;

        return $payload;
    }

    /**
     * Terapkan ulang hanya satu sel pola 4-mingguan yang berubah.
     *
     * Satu tanggal acuan berulang setiap 28 hari, sehingga perubahan drag/drop
     * tidak perlu membangun ulang seluruh kalender satu tahun.
     */
    public function materializeRepeatingPatternCell(
        string $templateId,
        string $workDate,
        int $monthsAhead = self::REPEATING_MONTHS_AHEAD,
    ): void {
        $template = ShiftScheduleTemplate::query()
            ->whereKey($templateId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return;
        }

        $anchorStart = $template->payload['anchor_start'] ?? null;
        $weeks = $template->payload['weeks'] ?? [];
        if (! is_string($anchorStart) || $anchorStart === '' || $weeks === []) {
            return;
        }

        if (! $this->dateInBlock($workDate, $anchorStart)) {
            return;
        }

        $cell = $this->patternCellForDate($workDate, $anchorStart, $weeks);
        if (! $cell) {
            return;
        }

        $tz = AppTimezone::display();
        $firstOccurrence = Carbon::parse($workDate, $tz)->addDays(28)->startOfDay();
        $block = $this->fourWeekBlock($anchorStart);
        $through = Carbon::parse($block['end'], $tz)->addDay()->addMonths($monthsAhead);

        $previousSuppressAudit = $this->suppressAudit;
        $previousSuppressRepeatingSync = $this->suppressRepeatingSync;
        $this->suppressAudit = true;
        $this->suppressRepeatingSync = true;

        try {
            DB::transaction(function () use ($cell, $firstOccurrence, $through) {
                $cursor = $firstOccurrence->copy();
                while ($cursor->lte($through)) {
                    $this->applyPatternCellToDate($cell, $cursor->toDateString(), true);
                    $cursor->addDays(28);
                }
            });
        } finally {
            $this->suppressAudit = $previousSuppressAudit;
            $this->suppressRepeatingSync = $previousSuppressRepeatingSync;
        }

        $this->audit(
            'Menyelesaikan sinkronisasi pola berulang "'.$template->name.'" untuk tanggal acuan '.$workDate,
            'shift.template.repeating.sync_completed',
            [
                'template_id' => $template->id,
                'anchor_start' => $anchorStart,
                'work_date' => $workDate,
            ],
        );
    }

    private function dateInBlock(string $date, string $blockStart): bool
    {
        $block = $this->fourWeekBlock($blockStart);

        return collect($block['weeks'])->flatten()->contains($date);
    }

    private function rematerializeRepeatingFuture(
        ShiftScheduleTemplate $template,
        int $monthsAhead = self::REPEATING_MONTHS_AHEAD,
    ): void {
        $anchorStart = $template->payload['anchor_start'] ?? null;
        $weeks = $template->payload['weeks'] ?? [];
        if (! $anchorStart || $weeks === []) {
            return;
        }

        $block = $this->fourWeekBlock($anchorStart);
        $from = Carbon::parse($block['end'], AppTimezone::display())->addDay()->startOfDay();
        $to = $from->copy()->addMonths($monthsAhead);

        $previousSuppressAudit = $this->suppressAudit;
        $previousSuppressRepeatingSync = $this->suppressRepeatingSync;
        $this->suppressAudit = true;
        $this->suppressRepeatingSync = true;
        try {
            $cursor = $from->copy();
            while ($cursor->lte($to)) {
                $date = $cursor->toDateString();
                $cell = $this->patternCellForDate($date, $anchorStart, $weeks);
                if ($cell) {
                    $this->applyPatternCellToDate($cell, $date, true);
                }
                $cursor->addDay();
            }
        } finally {
            $this->suppressAudit = $previousSuppressAudit;
            $this->suppressRepeatingSync = $previousSuppressRepeatingSync;
        }
    }

    /**
     * Kosongkan penempatan jadwal di semua tanggal setelah akhir blok acuan (blok mendatang).
     */
    private function clearFutureAfterAnchorBlock(string $anchorStart, int $monthsAhead = self::REPEATING_MONTHS_AHEAD): void
    {
        $block = $this->fourWeekBlock($anchorStart);
        $from = Carbon::parse($block['end'], AppTimezone::display())->addDay()->startOfDay();
        $to = $from->copy()->addMonths($monthsAhead);

        $this->suppressAudit = true;
        try {
            $cursor = $from->copy();
            while ($cursor->lte($to)) {
                $this->clearSchedulingForDate($cursor->toDateString(), true);
                $cursor->addDay();
            }
        } finally {
            $this->suppressAudit = false;
        }
    }

    private function clearSchedulingForDate(string $date, bool $preserveEvents = true): void
    {
        ShiftCalendarEntry::query()->whereDate('work_date', $date)->delete();
        ShiftEmployeeLibur::query()
            ->whereDate('work_date', $date)
            ->where('source', 'pattern')
            ->delete();

        $setting = ShiftDaySetting::query()->whereDate('work_date', $date)->first();
        if ($setting && $preserveEvents && $setting->holiday_kind === ShiftDaySetting::HOLIDAY_EVENT) {
            return;
        }

        $this->setCompanyHoliday($date, ShiftDaySetting::HOLIDAY_ROUTINE, false);
        $this->setDayDurations($date, null, null);
    }

    /**
     * @param  list<list<array>>  $weeks
     */
    private function patternCellForDate(string $date, string $anchorStart, array $weeks): ?array
    {
        $tz = AppTimezone::display();
        $anchor = Carbon::parse($anchorStart, $tz)->startOfDay();
        $day = Carbon::parse($date, $tz)->startOfDay();

        if ($day->lt($anchor)) {
            return null;
        }

        $diffDays = (int) $anchor->diffInDays($day);
        $weekIndex = intdiv($diffDays, 7) % 4;
        $dayIndex = $day->dayOfWeekIso - 1;

        return $weeks[$weekIndex][$dayIndex] ?? null;
    }

    private function applyPatternCellToDate(array $cell, string $date, bool $preserveEvents = true): void
    {
        ShiftCalendarEntry::query()->whereDate('work_date', $date)->delete();
        ShiftEmployeeLibur::query()
            ->whereDate('work_date', $date)
            ->where('source', 'pattern')
            ->delete();

        $setting = ShiftDaySetting::query()->whereDate('work_date', $date)->first();
        if ($setting && $preserveEvents && $setting->holiday_kind === ShiftDaySetting::HOLIDAY_EVENT) {
            // keep event
        } else {
            $this->setCompanyHoliday(
                $date,
                $cell['holiday_kind'] ?? ShiftDaySetting::HOLIDAY_ROUTINE,
                (bool) ($cell['is_holiday'] ?? false),
            );
            if (! empty($cell['work_duration_minutes']) || ! empty($cell['break_duration_minutes'])) {
                $this->setDayDurations(
                    $date,
                    $cell['work_duration_minutes'] ?? null,
                    $cell['break_duration_minutes'] ?? null,
                );
            }
        }

        foreach ($cell['entries'] ?? [] as $entry) {
            if (empty($entry['work_schedule_id'])) {
                continue;
            }
            if (! empty($entry['employee_id'])) {
                if (! Employee::query()->whereKey($entry['employee_id'])->where('is_active', true)->exists()) {
                    continue;
                }
                $this->placeEmployeeOnDate($entry['employee_id'], $entry['work_schedule_id'], $date);

                continue;
            }
            if (empty($entry['group_id'])) {
                continue;
            }
            if (! ShiftGroup::query()->whereKey($entry['group_id'])->exists()) {
                continue;
            }
            $this->placeGroupOnDate($entry['group_id'], $entry['work_schedule_id'], $date);
        }

        foreach ($cell['employee_libur'] ?? [] as $empId) {
            if (! Employee::query()->whereKey($empId)->where('is_active', true)->exists()) {
                continue;
            }
            ShiftEmployeeLibur::query()->firstOrCreate(
                ['employee_id' => $empId, 'work_date' => $date],
                ['source' => 'pattern', 'created_at' => now()],
            );
        }
    }

    /**
     * Kosongkan penempatan group & karyawan di seluruh blok (libur/pengaturan hari tetap).
     */
    public function clearBlock(string $blockStart): void
    {
        $block = $this->fourWeekBlock($blockStart);
        $this->clearDateRange($block['start'], $block['end'], 'blok '.$blockStart);
    }

    public function clearMonth(int $year, int $month): void
    {
        $block = $this->calendarMonth($year, $month);
        $this->clearDateRange(
            $block['month_start'],
            $block['month_end'],
            sprintf('bulan %02d/%d', $month, $year),
        );
    }

    private function clearDateRange(string $start, string $end, string $label): void
    {
        DB::transaction(function () use ($start, $end) {
            ShiftCalendarEntry::query()
                ->whereBetween('work_date', [$start, $end])
                ->delete();
            ShiftEmployeeLibur::query()
                ->whereBetween('work_date', [$start, $end])
                ->where('source', 'pattern')
                ->delete();
        });

        $this->audit(
            'Mengosongkan kalender '.$label,
            'shift.calendar.clear_range',
            ['start' => $start, 'end' => $end],
        );
    }

    public function copyWeek(string $sourceWeekStart, string $targetWeekStart): void
    {
        $tz = AppTimezone::display();
        $src = Carbon::parse($sourceWeekStart, $tz)->startOfWeek(Carbon::MONDAY);
        $tgt = Carbon::parse($targetWeekStart, $tz)->startOfWeek(Carbon::MONDAY);

        DB::transaction(function () use ($src, $tgt) {
            $this->suppressAudit = true;
            try {
                for ($i = 0; $i < 7; $i++) {
                    $from = $src->copy()->addDays($i)->toDateString();
                    $to = $tgt->copy()->addDays($i)->toDateString();

                    ShiftCalendarEntry::query()->whereDate('work_date', $to)->delete();
                    ShiftEmployeeLibur::query()->whereDate('work_date', $to)->where('source', 'pattern')->delete();

                    foreach (ShiftCalendarEntry::query()->whereDate('work_date', $from)->get() as $entry) {
                        if ($entry->employee_id) {
                            $this->placeEmployeeOnDate($entry->employee_id, $entry->work_schedule_id, $to);
                        } elseif ($entry->group_id) {
                            $this->placeGroupOnDate($entry->group_id, $entry->work_schedule_id, $to);
                        }
                    }

                    $setting = ShiftDaySetting::query()->whereDate('work_date', $from)->first();
                    if ($setting) {
                        if ($setting->is_company_holiday && $setting->holiday_kind !== ShiftDaySetting::HOLIDAY_EVENT) {
                            $this->setCompanyHoliday($to, ShiftDaySetting::HOLIDAY_ROUTINE, true);
                        }
                        $this->setDayDurations($to, $setting->work_duration_minutes, $setting->break_duration_minutes);
                    }

                    foreach (ShiftEmployeeLibur::query()->whereDate('work_date', $from)->where('source', 'pattern')->get() as $libur) {
                        ShiftEmployeeLibur::query()->firstOrCreate(
                            ['employee_id' => $libur->employee_id, 'work_date' => $to],
                            ['source' => 'pattern', 'created_at' => now()],
                        );
                    }
                }
            } finally {
                $this->suppressAudit = false;
            }
        });

        $this->audit(
            'Menyalin pola minggu '.$src->toDateString().' → '.$tgt->toDateString(),
            'shift.calendar.copy_week',
            ['source' => $src->toDateString(), 'target' => $tgt->toDateString()],
        );
    }

    /**
     * @param  list<string>  $weekStarts
     */
    public function copyWeekToAllWeeksOnPage(string $sourceWeekStart, array $weekStarts): void
    {
        foreach ($weekStarts as $targetWeekStart) {
            if ($targetWeekStart === $sourceWeekStart) {
                continue;
            }

            $this->copyWeek($sourceWeekStart, $targetWeekStart);
        }
    }

    public function copyAllWeeksToNextPeriod(
        string $viewMode,
        ?string $blockStart,
        ?int $viewYear,
        ?int $viewMonth,
    ): void {
        if ($viewMode === 'month') {
            $current = $this->calendarMonth($viewYear ?? (int) date('Y'), $viewMonth ?? (int) date('n'));
            $shifted = $this->shiftMonth($viewYear ?? (int) date('Y'), $viewMonth ?? (int) date('n'), 1);
            $next = $this->calendarMonth($shifted['year'], $shifted['month']);
            $currentWeekStarts = collect($current['weeks'])->map(fn ($w) => $w[0] ?? null)->filter()->values();
            $nextWeekStarts = collect($next['weeks'])->map(fn ($w) => $w[0] ?? null)->filter()->values();
        } else {
            $currentBlock = $this->fourWeekBlock($blockStart ?? '');
            $nextBlockStart = $this->shiftBlock($blockStart ?? '', 1);
            $nextBlock = $this->fourWeekBlock($nextBlockStart);
            $currentWeekStarts = collect($currentBlock['weeks'])->map(fn ($w) => $w[0] ?? null)->filter()->values();
            $nextWeekStarts = collect($nextBlock['weeks'])->map(fn ($w) => $w[0] ?? null)->filter()->values();
        }

        $pairCount = min($currentWeekStarts->count(), $nextWeekStarts->count());
        for ($i = 0; $i < $pairCount; $i++) {
            $this->copyWeek($currentWeekStarts[$i], $nextWeekStarts[$i]);
        }
    }

    /**
     * Payload read-only 4 minggu untuk satu karyawan (dashboard).
     *
     * @return array{block: array, weeks: list<list<array>>}
     */
    public function employeeBoard(Employee|string $employee, ?string $blockStart = null): array
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $block = $this->fourWeekBlock($blockStart);
        $resolver = app(ShiftResolver::class);
        $years = [
            (int) substr($block['start'], 0, 4),
            (int) substr($block['end'], 0, 4),
        ];
        $national = IndonesianHolidays::forYears($years);
        $today = AppTimezone::nowDisplay()->toDateString();

        $weeks = [];
        foreach ($block['weeks'] as $weekDates) {
            $week = [];
            foreach ($weekDates as $date) {
                $resolved = $resolver->resolveDay($employeeId, $date);
                $carbon = Carbon::parse($date, AppTimezone::display());
                $week[] = [
                    'date' => $date,
                    'day' => (int) $carbon->day,
                    'is_today' => $date === $today,
                    'kind' => $resolved->kind,
                    'label' => $resolved->statusLabel(),
                    'is_work' => $resolved->isWorkDay(),
                    'is_excused' => $resolved->isExcused,
                    'is_company_holiday' => $resolved->isCompanyHoliday,
                    'schedule_name' => $resolved->schedule?->name,
                    'clock_in' => $resolved->schedule?->clock_in_time,
                    'clock_out' => $resolved->schedule?->clock_out_time,
                    'national' => $national[$date] ?? null,
                ];
            }
            $weeks[] = $week;
        }

        return [
            'block' => $block,
            'weeks' => $weeks,
        ];
    }

    /**
     * Payload kalender karyawan: struktur boardPayload + resolusi jadwal pribadi per tanggal.
     *
     * @return array<string, mixed>
     */
    public function employeeScheduleBoard(
        Employee|string $employee,
        string $viewMode = 'block',
        ?string $blockStart = null,
        ?int $viewYear = null,
        ?int $viewMonth = null,
    ): array {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $board = $this->boardPayload($viewMode, $blockStart, $viewYear, $viewMonth, false);
        $resolver = app(ShiftResolver::class);
        $scheduleColors = ['#dbeafe', '#fce7f3', '#fef9c3', '#dcfce7', '#e0e7ff'];

        foreach ($board['weeks'] as &$week) {
            foreach ($week as &$cell) {
                $resolved = $resolver->resolveDay($employeeId, $cell['date']);
                $scheduleIndex = $resolved->schedule
                    ? $board['schedules']->search(
                        fn (WorkSchedule $s) => (string) $s->id === (string) $resolved->schedule->id,
                    )
                    : false;
                $cell['mine'] = [
                    'kind' => $resolved->kind,
                    'label' => $resolved->statusLabel(),
                    'is_work' => $resolved->isWorkDay(),
                    'schedule_id' => $resolved->schedule?->id,
                    'schedule_name' => $resolved->schedule?->name,
                    'schedule_color' => $scheduleIndex !== false
                        ? $scheduleColors[$scheduleIndex % count($scheduleColors)]
                        : null,
                    'clock_in' => $resolved->schedule?->clock_in_time,
                    'clock_out' => $resolved->schedule?->clock_out_time,
                ];
            }
        }
        unset($week, $cell);

        return $board;
    }

    /**
     * @return Collection<string, Collection<int, ShiftGroupMember>>
     */
    private function membershipsByGroup(string $start, string $end): Collection
    {
        $today = AppTimezone::nowDisplay()->toDateString();
        $rangeEnd = max($end, $today);
        $rangeStart = min($start, $today);

        return ShiftGroupMember::query()
            ->with(['employee:id,is_active'])
            ->whereDate('effective_from', '<=', $rangeEnd)
            ->where(function ($q) use ($rangeStart) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $rangeStart);
            })
            ->get()
            ->groupBy(fn (ShiftGroupMember $m) => (string) $m->group_id);
    }

    /**
     * @param  Collection<string, Collection<int, ShiftGroupMember>>  $byGroup
     */
    private function groupMembers(Collection $byGroup, string $groupId): Collection
    {
        return $byGroup->get($groupId, collect());
    }

    /**
     * @param  Collection<int, ShiftEmployeeLibur>  $liburForDate
     * @param  array<string|int, array<string, mixed>>  $leaveMap
     * @return Collection<int, string>
     */
    private function employeesOnLeaveForDate(string $date, Collection $liburForDate, array $leaveMap): Collection
    {
        $ids = $liburForDate
            ->pluck('employee_id')
            ->map(fn ($id) => (string) $id);

        foreach ($leaveMap as $empId => $days) {
            if (! empty($days[$date])) {
                $ids->push((string) $empId);
            }
        }

        return $ids->unique()->values();
    }

    /**
     * @param  Collection<int, string>  $onLeaveEmployeeIds
     */
    private function employeeOnLeave(string $employeeId, Collection $onLeaveEmployeeIds): bool
    {
        return $onLeaveEmployeeIds->contains($employeeId);
    }

    /**
     * @param  Collection<string, ShiftEmployeeShiftOverride>  $overrideByEmployee
     * @param  Collection<string, ShiftSwapRequest>  $pendingByEmployee
     */
    private function employeeRelocatedFromSchedule(
        string $employeeId,
        string $scheduleId,
        Collection $overrideByEmployee,
        Collection $pendingByEmployee,
    ): bool {
        $ov = $overrideByEmployee->get($employeeId);
        if ($ov && (string) $ov->work_schedule_id !== $scheduleId) {
            return true;
        }

        $pending = $pendingByEmployee->get($employeeId);
        if ($pending && (string) $pending->to_work_schedule_id !== $scheduleId) {
            return true;
        }

        return false;
    }

    /**
     * @param  Collection<string, Collection<int, ShiftGroupMember>>  $byGroup
     * @param  Collection<string, ShiftEmployeeShiftOverride>  $overrideByEmployee
     * @param  Collection<string, ShiftSwapRequest>  $pendingByEmployee
     */
    private function countGroupMembersOnSchedule(
        Collection $byGroup,
        string $groupId,
        string $date,
        string $scheduleId,
        Collection $overrideByEmployee,
        Collection $pendingByEmployee,
        Collection $onLeaveEmployeeIds,
        string $today,
        bool $planningMode = false,
    ): int {
        if ($planningMode) {
            // Mode atur: roster saat ini (bukan historis), libur rutin pola per tanggal saja.
            return $this->groupMembers($byGroup, $groupId)
                ->filter(function (ShiftGroupMember $m) use ($today, $onLeaveEmployeeIds) {
                    if (! $this->memberActiveOnDate($m, $today) || ! $m->employee?->is_active) {
                        return false;
                    }

                    return ! $this->employeeOnLeave((string) $m->employee_id, $onLeaveEmployeeIds);
                })
                ->count();
        }

        if ($date < $today) {
            // Mode lihat, hari lampau: roster saat ini (tanpa pengurangan libur operasional).
            return $this->groupMembers($byGroup, $groupId)
                ->filter(fn (ShiftGroupMember $m) => $this->memberActiveOnDate($m, $today) && $m->employee?->is_active)
                ->count();
        }

        return $this->groupMembers($byGroup, $groupId)
            ->filter(function (ShiftGroupMember $m) use ($date, $scheduleId, $overrideByEmployee, $pendingByEmployee, $onLeaveEmployeeIds) {
                if (! $this->memberActiveOnDate($m, $date) || ! $m->employee?->is_active) {
                    return false;
                }

                $empId = (string) $m->employee_id;

                if ($this->employeeOnLeave($empId, $onLeaveEmployeeIds)) {
                    return false;
                }

                return ! $this->employeeRelocatedFromSchedule($empId, $scheduleId, $overrideByEmployee, $pendingByEmployee);
            })
            ->count();
    }

    private function memberActiveOnDate(ShiftGroupMember $m, string $date): bool
    {
        $from = $m->effective_from?->toDateString();
        $to = $m->effective_to?->toDateString();
        if ($from !== null && $from > $date) {
            return false;
        }
        if ($to !== null && $to < $date) {
            return false;
        }

        return true;
    }

    /**
     * @param  Collection<string, Collection<int, ShiftGroupMember>>  $byGroup
     */
    private function countMembersOnDate(Collection $byGroup, string $groupId, string $date): int
    {
        return $this->groupMembers($byGroup, $groupId)
            ->filter(fn (ShiftGroupMember $m) => $this->memberActiveOnDate($m, $date) && (bool) $m->employee?->is_active)
            ->count();
    }
}
