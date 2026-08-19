<?php

use App\Models\Employee;
use App\Models\ShiftCalendarEntry;
use App\Models\ShiftDaySetting;
use App\Models\ShiftGroup;
use App\Models\ShiftScheduleTemplate;
use App\Models\ShiftSwapRequest;
use App\Models\WorkSchedule;
use App\Services\ShiftCalendarService;
use App\Services\ShiftGroupService;
use App\Services\ShiftSwapService;
use App\Support\AppTimezone;
use App\Support\Toast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url(as: 'tab', except: 'rules', history: false)]
    public string $tab = 'rules';

    // —— Rule shift ——
    public bool $showRuleModal = false;

    public ?string $editingScheduleId = null;

    public string $rule_name = '';

    public string $rule_clock_in = '08:00';

    public float $rule_work_hours = 8;

    public int $rule_break_minutes = 60;

    public string $rule_break_earliest_mode = 'free';

    public string $rule_break_earliest_time = '12:00';

    public string $rule_late_after = '08:15';

    public string $rule_implementation_mode = 'default';

    /** @var list<int|string> */
    public array $rule_implementation_weekdays = [];

    public string $rule_implementation_month_days = '';

    /** @var list<string> */
    public array $rule_implementation_specific_dates = [];

    public string $rule_implementation_specific_date_input = '';

    public bool $showRuleImpactConfirm = false;

    /** @var array{removed_entries: int, unscheduled_employees: list<string>} */
    public array $ruleImpactPreview = [];

    public bool $ruleSaveConfirmed = false;

    // —— Group ——
    public bool $showGroupModal = false;

    public ?string $editingGroupId = null;

    public string $group_name = '';

    public string $group_color = '#3b82f6';

    public string $groupSearch = '';

    // —— Calendar ——
    public string $blockStart = '';

    /** @var list<string> */
    public array $selectedDates = [];

    public bool $showDayMenu = false;

    public string $dayMenuDate = '';

    public bool $dayMenuIsHoliday = false;

    public string $dayMenuHolidayKind = 'routine';

    public ?int $dayMenuWorkMinutes = null;

    public ?int $dayMenuBreakMinutes = null;

    public bool $showTemplateSettings = false;

    public bool $showSaveNewTemplateModal = false;

    public bool $showMemberPanel = false;

    public string $memberPanelDate = '';

    public string $memberPanelGroupId = '';

    public string $templateName = '';

    public string $activeTemplateId = '';

    public string $selectedTemplateId = '';

    public string $copySourceWeek = '';

    public string $copyTargetWeek = '';

    public bool $calendarEditMode = false;

    /** @var 'block'|'month' */
    public string $calendarViewMode = 'block';

    public int $viewYear = 0;

    public int $viewMonth = 0;

    public const COPY_ALL_WEEKS = '__all_weeks__';

    public const COPY_NEXT_PAGE = '__next_page__';

    public function mount(): void
    {
        $today = AppTimezone::nowDisplay();
        $todayStr = $today->toDateString();
        $calendar = app(ShiftCalendarService::class);
        $this->blockStart = $calendar->blockStartContainingDate($todayStr);
        $this->viewYear = (int) $today->year;
        $this->viewMonth = (int) $today->month;

        if (in_array($this->tab, ['placement', 'ops', 'rotation'], true)) {
            $this->tab = $this->tab === 'rotation' ? 'calendar' : 'groups';
        }

        app(ShiftGroupService::class)->ensureUnassigned();

        $this->calendarEditMode = false;
        $storedViewMode = (string) session('shift.calendar_view_mode', 'block');
        $this->calendarViewMode = in_array($storedViewMode, ['block', 'month'], true) ? $storedViewMode : 'block';
        $this->viewYear = (int) session('shift.view_year', $this->viewYear);
        $this->viewMonth = (int) session('shift.view_month', $this->viewMonth);
        if ($this->viewMonth < 1 || $this->viewMonth > 12) {
            $this->viewMonth = (int) $today->month;
            $this->viewYear = (int) $today->year;
        }

        $this->restoreActiveTemplate();
    }

    private function restoreActiveTemplate(): void
    {
        $dbActiveId = ShiftScheduleTemplate::query()->where('is_active', true)->value('id');
        $sessionId = (string) session('shift.active_template_id', '');

        $templateId = match (true) {
            $dbActiveId !== null => (string) $dbActiveId,
            $sessionId !== '' && ShiftScheduleTemplate::query()->whereKey($sessionId)->exists() => $sessionId,
            default => '',
        };

        if ($templateId === '') {
            return;
        }

        $this->activeTemplateId = $templateId;
        $this->selectedTemplateId = $templateId;
        session(['shift.active_template_id' => $templateId]);
    }

    private function rememberActiveTemplate(string $templateId, bool $persistToDatabase = true): void
    {
        $this->activeTemplateId = $templateId;
        $this->selectedTemplateId = $templateId;
        session(['shift.active_template_id' => $templateId]);

        if (! $persistToDatabase) {
            return;
        }

        ShiftScheduleTemplate::query()->update(['is_active' => false]);
        ShiftScheduleTemplate::query()
            ->whereKey($templateId)
            ->update(['is_active' => true]);
    }

    private function clearActiveTemplate(): void
    {
        $this->activeTemplateId = '';
        session()->forget('shift.active_template_id');
        ShiftScheduleTemplate::query()->where('is_active', true)->update(['is_active' => false]);
    }

    private function closeTemplateSettingsModal(): void
    {
        $this->templateName = '';
        $this->showTemplateSettings = false;
        $this->showSaveNewTemplateModal = false;
        $this->resetValidation();
    }

    public function openSaveNewTemplateModal(): void
    {
        if (! $this->ensureCalendarEditMode()) {
            return;
        }

        $this->templateName = '';
        $this->resetValidation();
        $this->showSaveNewTemplateModal = true;
    }

    public function closeSaveNewTemplateModalPublic(): void
    {
        $this->showSaveNewTemplateModal = false;
        $this->templateName = '';
        $this->resetValidation();
    }

    public function openTemplateSettingsModal(): void
    {
        if (! $this->ensureCalendarEditMode()) {
            return;
        }

        if ($this->activeTemplateId !== '') {
            $this->selectedTemplateId = $this->activeTemplateId;
        }
        $this->templateName = '';
        $this->showTemplateSettings = true;
    }

    public function closeTemplateSettingsModalPublic(): void
    {
        $this->closeTemplateSettingsModal();
    }

    private function rememberCalendarPreferences(): void
    {
        session([
            'shift.calendar_view_mode' => $this->calendarViewMode,
            'shift.view_year' => $this->viewYear,
            'shift.view_month' => $this->viewMonth,
        ]);
    }

    /** @return list<string> */
    private function periodDatesInView(ShiftCalendarService $calendar): array
    {
        if ($this->calendarViewMode === 'month') {
            $month = $calendar->calendarMonth($this->viewYear, $this->viewMonth);

            return collect($month['weeks'])
                ->flatten()
                ->filter(fn (string $d) => $d >= $month['month_start'] && $d <= $month['month_end'])
                ->values()
                ->all();
        }

        $block = $calendar->fourWeekBlock($this->blockStart);

        return collect($block['weeks'])->flatten()->values()->all();
    }

    public function toggleCalendarEditMode(): void
    {
        if ($this->calendarEditMode) {
            $this->exitCalendarEditMode();
        } else {
            $this->calendarEditMode = true;
            $anchorStart = $this->activeRepeatingAnchorStart();
            if ($anchorStart !== null) {
                $this->calendarViewMode = 'block';
                $this->blockStart = $anchorStart;
            }
            $this->pruneSelectedDatesToEditable();
        }
        $this->rememberCalendarPreferences();
    }

    private function exitCalendarEditMode(): void
    {
        if (! $this->calendarEditMode) {
            return;
        }

        $this->calendarEditMode = false;
        $this->selectedDates = [];
        $this->showDayMenu = false;
        $this->showTemplateSettings = false;
        $this->showSaveNewTemplateModal = false;
        $this->closeMemberPanel();
    }

    public function exitCalendarEditModeForNavigation(): void
    {
        $this->exitCalendarEditMode();
    }

    public function updatedTab(string $tab): void
    {
        $this->exitCalendarEditMode();
    }

    private function ensureCalendarEditMode(): bool
    {
        if ($this->calendarEditMode) {
            return true;
        }

        Toast::error('Aktifkan Mode Atur untuk mengubah jadwal.', $this);

        return false;
    }

    private function activeRepeatingAnchorStart(): ?string
    {
        if ($this->activeTemplateId === '') {
            return null;
        }

        $template = ShiftScheduleTemplate::query()->find($this->activeTemplateId);
        if (! $template?->is_default) {
            return null;
        }

        $anchorStart = $template->payload['anchor_start'] ?? null;

        return is_string($anchorStart) && $anchorStart !== '' ? $anchorStart : null;
    }

    private function isDateInActiveRepeatingAnchor(string $date): bool
    {
        $anchorStart = $this->activeRepeatingAnchorStart();
        if ($anchorStart === null) {
            return true;
        }

        $anchor = Carbon::parse($anchorStart, AppTimezone::display())->startOfDay();
        $candidate = Carbon::parse($date, AppTimezone::display())->startOfDay();

        return $candidate->betweenIncluded($anchor, $anchor->copy()->addDays(27));
    }

    private function ensureRepeatingAnchorView(): bool
    {
        $anchorStart = $this->activeRepeatingAnchorStart();
        if (
            $anchorStart === null
            || ($this->calendarViewMode === 'block' && $this->blockStart === $anchorStart)
        ) {
            return true;
        }

        Toast::error('Halaman ini hanya dapat dilihat. Kembali ke blok 4 minggu acuan untuk melakukan perubahan.', $this);

        return false;
    }

    public function isCalendarDateEditable(string $date): bool
    {
        if (! $this->calendarEditMode) {
            return false;
        }

        return $this->isCalendarDateInView($date)
            && $this->isDateInActiveRepeatingAnchor($date);
    }

    public function isPastCalendarDate(string $date): bool
    {
        return $date < AppTimezone::nowDisplay()->toDateString();
    }

    /**
     * Mode baca: libur rutin & tukar shift hanya untuk hari ini ke depan.
     * Mode atur: mengikuti aturan editable kalender.
     */
    public function canManageMembersOnDate(string $date): bool
    {
        if ($this->calendarEditMode) {
            return $this->isCalendarDateEditable($date);
        }

        return ! $this->isPastCalendarDate($date);
    }

    public function isCalendarDateInView(string $date): bool
    {
        if ($this->calendarViewMode !== 'month') {
            return true;
        }

        $parsed = Carbon::parse($date, AppTimezone::display());

        return (int) $parsed->year === $this->viewYear
            && (int) $parsed->month === $this->viewMonth;
    }

    public function canManageLiburOnDate(string $date): bool
    {
        if (! $this->isCalendarDateInView($date)
            || ! $this->isDateInActiveRepeatingAnchor($date)) {
            return false;
        }

        if (! $this->calendarEditMode && $this->isPastCalendarDate($date)) {
            return false;
        }

        return true;
    }

    private function ensureCanManageLiburOnDate(string $date): bool
    {
        if (! $this->canManageLiburOnDate($date)) {
            if (! $this->calendarEditMode && $this->isPastCalendarDate($date)) {
                Toast::error('Hari yang sudah lewat tidak dapat diubah di mode baca.', $this);
            } else {
                Toast::error('Hari di luar bulan ini tidak dapat diubah di tampilan Kalender.', $this);
            }

            return false;
        }

        return true;
    }

    private function ensureCanEditCalendarDate(string $date): bool
    {
        if (! $this->ensureCalendarEditMode()) {
            return false;
        }

        if (! $this->isCalendarDateEditable($date)) {
            $message = $this->activeRepeatingAnchorStart() !== null
                ? 'Saat pola berulang aktif, perubahan hanya dapat dilakukan pada blok 4 minggu acuan.'
                : 'Hari di luar bulan ini tidak dapat diubah di tampilan Kalender.';
            Toast::error($message, $this);

            return false;
        }

        return true;
    }

    private function pruneSelectedDatesToEditable(): void
    {
        if ($this->selectedDates === []) {
            return;
        }

        $this->selectedDates = array_values(array_filter(
            $this->selectedDates,
            fn (string $d) => $this->isCalendarDateEditable($d),
        ));
    }

    public function setCalendarViewMode(string $mode, ShiftCalendarService $calendar): void
    {
        if (! in_array($mode, ['block', 'month'], true)) {
            return;
        }

        if ($mode === 'month' && $this->calendarEditMode && $this->activeRepeatingAnchorStart() !== null) {
            Toast::error('Mode pola berulang hanya dapat diatur dari Blok 4 Minggu acuan.', $this);

            return;
        }

        if ($mode === $this->calendarViewMode) {
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
        $this->selectedDates = [];
        $this->pruneSelectedDatesToEditable();
        $this->rememberCalendarPreferences();
    }

    public function goToRepeatingAnchor(): void
    {
        $anchorStart = $this->activeRepeatingAnchorStart();
        if ($anchorStart === null) {
            return;
        }

        $this->calendarViewMode = 'block';
        $this->blockStart = $anchorStart;
        $this->selectedDates = [];
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
        $this->selectedDates = [];
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
        $this->selectedDates = [];
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

        $this->selectedDates = [];
    }

    // ========== RULES ==========

    public function openCreateRule(): void
    {
        $this->resetValidation();
        $this->editingScheduleId = null;
        $this->rule_name = '';
        $this->rule_clock_in = '08:00';
        $this->rule_work_hours = 8;
        $this->rule_break_minutes = 60;
        $this->rule_break_earliest_mode = 'free';
        $this->rule_break_earliest_time = '12:00';
        $this->rule_late_after = '08:15';
        $this->rule_implementation_mode = 'default';
        $this->rule_implementation_weekdays = [];
        $this->rule_implementation_month_days = '';
        $this->rule_implementation_specific_dates = [];
        $this->rule_implementation_specific_date_input = '';
        $this->showRuleImpactConfirm = false;
        $this->ruleImpactPreview = [];
        $this->ruleSaveConfirmed = false;
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
        $this->rule_break_earliest_mode = filled($row->break_earliest_time) ? 'fixed' : 'free';
        $this->rule_break_earliest_time = filled($row->break_earliest_time)
            ? substr((string) $row->break_earliest_time, 0, 5)
            : '12:00';
        $this->rule_late_after = substr((string) ($row->late_after_time ?: $row->clock_in_time), 0, 5);
        $this->rule_implementation_mode = $row->implementation_mode ?? WorkSchedule::IMPLEMENTATION_DEFAULT;
        $this->rule_implementation_weekdays = $row->normalizedWeekdays();
        $this->rule_implementation_month_days = $row->normalizedMonthDays() !== []
            ? implode(', ', $row->normalizedMonthDays())
            : '';
        $this->rule_implementation_specific_dates = $row->normalizedSpecificDates();
        $this->rule_implementation_specific_date_input = '';
        $this->showRuleImpactConfirm = false;
        $this->ruleImpactPreview = [];
        $this->ruleSaveConfirmed = false;
        $this->showRuleModal = true;
    }

    public function closeRuleModal(): void
    {
        $this->showRuleModal = false;
        $this->editingScheduleId = null;
        $this->showRuleImpactConfirm = false;
        $this->ruleImpactPreview = [];
        $this->ruleSaveConfirmed = false;
        $this->resetValidation();
    }

    public function addRuleSpecificDate(): void
    {
        $date = trim($this->rule_implementation_specific_date_input);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Toast::error('Pilih tanggal yang valid.', $this);

            return;
        }
        if (! in_array($date, $this->rule_implementation_specific_dates, true)) {
            $this->rule_implementation_specific_dates[] = $date;
            sort($this->rule_implementation_specific_dates);
        }
        $this->rule_implementation_specific_date_input = '';
    }

    public function removeRuleSpecificDate(string $date): void
    {
        $this->rule_implementation_specific_dates = array_values(array_filter(
            $this->rule_implementation_specific_dates,
            fn (string $d) => $d !== $date,
        ));
    }

    public function cancelRuleImpactConfirm(): void
    {
        $this->showRuleImpactConfirm = false;
        $this->ruleImpactPreview = [];
    }

    public function confirmRuleSave(): void
    {
        $this->ruleSaveConfirmed = true;
        $this->showRuleImpactConfirm = false;
        $this->saveRule();
        $this->ruleSaveConfirmed = false;
    }

    public function updatedRuleImplementationMode(string $value): void
    {
        if ($value !== WorkSchedule::IMPLEMENTATION_WEEKDAYS) {
            $this->rule_implementation_weekdays = [];
        }
        if ($value !== WorkSchedule::IMPLEMENTATION_MONTH_DAYS) {
            $this->rule_implementation_month_days = '';
        }
        if ($value !== WorkSchedule::IMPLEMENTATION_SPECIFIC_DATES) {
            $this->rule_implementation_specific_dates = [];
            $this->rule_implementation_specific_date_input = '';
        }
    }

    public function saveRule(): void
    {
        $calendar = app(ShiftCalendarService::class);
        $monthDays = $this->parseMonthDaysInput($this->rule_implementation_month_days);
        $weekdayValues = collect($this->rule_implementation_weekdays)
            ->map(fn ($d) => (int) $d)
            ->filter(fn (int $d) => $d >= 1 && $d <= 7)
            ->unique()
            ->values()
            ->all();

        $data = $this->validate([
            'rule_name' => ['required', 'string', 'max:255'],
            'rule_clock_in' => ['required', 'date_format:H:i'],
            'rule_work_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'rule_break_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'rule_break_earliest_mode' => ['required', 'in:free,fixed'],
            'rule_break_earliest_time' => ['required_if:rule_break_earliest_mode,fixed', 'nullable', 'date_format:H:i'],
            'rule_late_after' => ['required', 'date_format:H:i'],
            'rule_implementation_mode' => ['required', 'in:default,weekdays,month_days,specific_dates'],
        ], [], [
            'rule_name' => 'nama shift',
            'rule_clock_in' => 'jam masuk',
            'rule_work_hours' => 'jam kerja',
            'rule_break_minutes' => 'istirahat',
            'rule_late_after' => 'telat setelah',
            'rule_implementation_mode' => 'implementasi shift',
        ]);

        if ($data['rule_implementation_mode'] === WorkSchedule::IMPLEMENTATION_WEEKDAYS && $weekdayValues === []) {
            $this->addError('rule_implementation_weekdays', 'Pilih minimal satu hari.');

            return;
        }

        if ($data['rule_implementation_mode'] === WorkSchedule::IMPLEMENTATION_MONTH_DAYS && $monthDays === []) {
            $this->addError('rule_implementation_month_days', 'Masukkan minimal satu tanggal (1–31).');

            return;
        }

        if ($data['rule_implementation_mode'] === WorkSchedule::IMPLEMENTATION_SPECIFIC_DATES
            && $this->rule_implementation_specific_dates === []) {
            $this->addError('rule_implementation_specific_dates', 'Tambahkan minimal satu tanggal.');

            return;
        }

        $weekdays = $data['rule_implementation_mode'] === WorkSchedule::IMPLEMENTATION_WEEKDAYS
            ? $weekdayValues
            : null;
        $specificDates = $data['rule_implementation_mode'] === WorkSchedule::IMPLEMENTATION_SPECIFIC_DATES
            ? array_values(array_unique($this->rule_implementation_specific_dates))
            : null;
        $monthDaysPayload = $data['rule_implementation_mode'] === WorkSchedule::IMPLEMENTATION_MONTH_DAYS
            ? $monthDays
            : null;

        $existing = $this->editingScheduleId
            ? WorkSchedule::find($this->editingScheduleId)
            : null;

        if ($existing && ! $this->ruleSaveConfirmed && $this->implementationRulesChanged(
            $existing,
            $data['rule_implementation_mode'],
            $weekdays,
            $monthDaysPayload,
            $specificDates,
        )) {
            try {
                $preview = $calendar->previewImplementationImpact(
                    $this->editingScheduleId,
                    $data['rule_implementation_mode'],
                    $weekdays,
                    $monthDaysPayload,
                    $specificDates,
                );
                if ($preview['removed_entries'] > 0) {
                    $this->ruleImpactPreview = $preview;
                    $this->showRuleImpactConfirm = true;

                    return;
                }
            } catch (\Throwable $e) {
                Toast::error('Gagal memeriksa dampak perubahan: '.$e->getMessage(), $this);

                return;
            }
        }

        $workMinutes = (int) round(((float) $data['rule_work_hours']) * 60);
        $clockOut = $this->calcClockOut($data['rule_clock_in'], $workMinutes, (int) $data['rule_break_minutes']);

        $payload = [
            'name' => $data['rule_name'],
            'clock_in_time' => $data['rule_clock_in'],
            'clock_out_time' => $clockOut,
            'break_duration_minutes' => (int) $data['rule_break_minutes'],
            'break_earliest_time' => $data['rule_break_earliest_mode'] === 'fixed'
                ? $data['rule_break_earliest_time']
                : null,
            'work_duration_minutes' => $workMinutes,
            'late_after_time' => $data['rule_late_after'],
            'crosses_midnight' => $clockOut < $data['rule_clock_in'],
            'implementation_mode' => $data['rule_implementation_mode'],
            'implementation_weekdays' => $weekdays,
            'implementation_month_days' => $monthDaysPayload,
            'implementation_specific_dates' => $specificDates,
        ];

        if ($this->editingScheduleId) {
            $schedule = WorkSchedule::findOrFail($this->editingScheduleId);
            $schedule->update($payload);
            $impact = $calendar->cleanupInvalidScheduleEntries($schedule->fresh());
            $message = 'Shift diperbarui.';
            if ($impact['removed_entries'] > 0) {
                $message .= ' '.$impact['removed_entries'].' penempatan kalender dihapus.';
                if ($impact['unscheduled_employees'] !== []) {
                    $names = implode(', ', array_slice($impact['unscheduled_employees'], 0, 5));
                    $extra = count($impact['unscheduled_employees']) > 5 ? '…' : '';
                    $message .= ' Karyawan tanpa jadwal lain: '.$names.$extra;
                }
            }
            Toast::success($message, $this);
        } else {
            WorkSchedule::create($payload);
            Toast::success('Shift dibuat.', $this);
        }

        $this->closeRuleModal();
    }

    /**
     * @return list<int>
     */
    private function parseMonthDaysInput(string $input): array
    {
        $days = [];
        foreach (preg_split('/[\s,;]+/', trim($input)) ?: [] as $part) {
            if ($part === '') {
                continue;
            }
            $n = (int) $part;
            if ($n >= 1 && $n <= 31) {
                $days[] = $n;
            }
        }

        return array_values(array_unique($days));
    }

    private function implementationRulesChanged(
        WorkSchedule $existing,
        string $mode,
        ?array $weekdays,
        ?array $monthDays,
        ?array $specificDates,
    ): bool {
        return ($existing->implementation_mode ?? WorkSchedule::IMPLEMENTATION_DEFAULT) !== $mode
            || $existing->normalizedWeekdays() !== ($weekdays ?? [])
            || $existing->normalizedMonthDays() !== ($monthDays ?? [])
            || $existing->normalizedSpecificDates() !== ($specificDates ?? []);
    }

    public function toggleShiftEnabled(string $id): void
    {
        $schedule = WorkSchedule::findOrFail($id);
        $newEnabled = ! $schedule->is_enabled;

        $schedule->update(['is_enabled' => $newEnabled]);
        Toast::success(
            $newEnabled
                ? "Shift \"{$schedule->name}\" diaktifkan."
                : "Shift \"{$schedule->name}\" dinonaktifkan — tidak tampil di kalender.",
            $this
        );
    }

    public function deleteRule(string $id): void
    {
        $schedule = WorkSchedule::findOrFail($id);
        if (WorkSchedule::query()->count() <= 1) {
            Toast::error('Minimal harus ada satu shift.', $this);

            return;
        }
        if (ShiftCalendarEntry::query()->where('work_schedule_id', $id)->exists()) {
            Toast::error('Shift masih dipakai di kalender Jadwal Shift.', $this);

            return;
        }
        $name = $schedule->name;
        $schedule->delete();
        Toast::success("Shift \"{$name}\" dihapus.", $this);
    }

    // ========== GROUPS ==========

    public function openCreateGroup(): void
    {
        $this->resetValidation();
        $this->editingGroupId = null;
        $this->group_name = '';
        $this->group_color = '#3b82f6';
        $this->showGroupModal = true;
    }

    public function openEditGroup(string $id): void
    {
        $group = ShiftGroup::findOrFail($id);
        if ($group->is_system_unassigned) {
            Toast::error('Unassigned tidak bisa diubah namanya.', $this);

            return;
        }
        $this->editingGroupId = $group->id;
        $this->group_name = $group->name;
        $this->group_color = $group->color;
        $this->showGroupModal = true;
    }

    public function closeGroupModal(): void
    {
        $this->showGroupModal = false;
        $this->editingGroupId = null;
    }

    public function saveGroup(ShiftGroupService $groups): void
    {
        $data = $this->validate([
            'group_name' => ['required', 'string', 'max:100'],
            'group_color' => ['required', 'string', 'max:16'],
        ]);

        try {
            if ($this->editingGroupId) {
                $group = ShiftGroup::findOrFail($this->editingGroupId);
                if ($group->is_system_unassigned) {
                    throw new \RuntimeException('Unassigned tidak bisa diubah.');
                }
                $group->update([
                    'name' => trim($data['group_name']),
                    'color' => $data['group_color'],
                    'updated_at' => now(),
                ]);
                Toast::success('Group diperbarui.', $this);
            } else {
                $groups->createGroup($data['group_name'], $data['group_color']);
                Toast::success('Group dibuat.', $this);
            }
            $this->closeGroupModal();
        } catch (\Throwable $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function deleteGroup(string $id, ShiftGroupService $groups): void
    {
        try {
            $groups->deleteGroup($id);
            Toast::success('Group dihapus. Anggota kembali ke Unassigned.', $this);
        } catch (\Throwable $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function moveEmployeeToGroup(string $employeeId, string $groupId, ShiftGroupService $groups): void
    {
        try {
            $groups->moveEmployee($employeeId, $groupId);
            Toast::success('Karyawan dipindahkan.', $this);
        } catch (\Throwable $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    // ========== CALENDAR ==========

    public function prevBlock(ShiftCalendarService $calendar): void
    {
        $this->prevPeriod($calendar);
    }

    public function nextBlock(ShiftCalendarService $calendar): void
    {
        $this->nextPeriod($calendar);
    }

    public function toggleSelectDate(string $date): void
    {
        if (in_array($date, $this->selectedDates, true)) {
            $this->selectedDates = array_values(array_filter(
                $this->selectedDates,
                fn ($d) => $d !== $date
            ));
        } else {
            $this->selectedDates[] = $date;
        }
        $this->skipRender();
    }

    public function clearSelection(): void
    {
        $this->selectedDates = [];
    }

    public function placeGroup(string $groupId, string $scheduleId, string $date, ShiftCalendarService $calendar): void
    {
        if (! $this->ensureCanEditCalendarDate($date)) {
            return;
        }

        try {
            $calendar->placeGroupOnDate($groupId, $scheduleId, $date);
            Toast::success('Group ditempatkan.', $this);
        } catch (\Throwable $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function placeEmployee(string $employeeId, string $scheduleId, string $date, ShiftCalendarService $calendar): void
    {
        if (! $this->ensureCanEditCalendarDate($date)) {
            return;
        }

        try {
            $employee = Employee::findOrFail($employeeId);
            $calendar->placeEmployeeOnDate($employeeId, $scheduleId, $date);
            Toast::success($employee->full_name.' dijadwalkan.', $this);
        } catch (\Throwable $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function removeCalendarEntry(string $entryId, ShiftCalendarService $calendar): void
    {
        if (! $this->ensureCalendarEditMode()) {
            return;
        }

        $entry = ShiftCalendarEntry::query()->find($entryId);
        if ($entry && ! $this->ensureCanEditCalendarDate($entry->work_date->toDateString())) {
            return;
        }

        $calendar->removeEntry($entryId);
        Toast::success('Dihapus dari kalender.', $this);
    }

    public function removeEmployeeFromCalendar(string $employeeId, string $date, string $scheduleId, ShiftCalendarService $calendar): void
    {
        if (! $this->ensureCanEditCalendarDate($date)) {
            return;
        }

        $calendar->removeEmployeeFromDate($employeeId, $date, $scheduleId);
        Toast::success('Karyawan dikembalikan ke Unassigned.', $this);
    }

    public function removeGroupChip(string $groupId, string $date, string $scheduleId, ShiftCalendarService $calendar): void
    {
        if (! $this->ensureCanEditCalendarDate($date)) {
            return;
        }

        $calendar->removeGroupFromDate($groupId, $date, $scheduleId);
        Toast::success('Group dihapus dari hari ini.', $this);
    }

    public function moveCalendarChip(
        string $kind,
        string $itemId,
        string $fromDate,
        string $fromScheduleId,
        string $toDate,
        string $toScheduleId,
        ShiftCalendarService $calendar,
    ): void {
        if (
            ! $this->ensureCanEditCalendarDate($fromDate)
            || ! $this->ensureCanEditCalendarDate($toDate)
        ) {
            return;
        }

        if ($fromDate === $toDate && $fromScheduleId === $toScheduleId) {
            return;
        }

        try {
            if ($kind === 'group') {
                $calendar->moveGroupOnCalendar(
                    $itemId,
                    $fromDate,
                    $fromScheduleId,
                    $toDate,
                    $toScheduleId,
                );
                Toast::success('Group berhasil dipindahkan.', $this);

                return;
            }

            if ($kind === 'employee') {
                $employee = Employee::findOrFail($itemId);
                $calendar->moveEmployeeOnCalendar(
                    $itemId,
                    $fromDate,
                    $fromScheduleId,
                    $toDate,
                    $toScheduleId,
                );
                Toast::success($employee->full_name.' berhasil dipindahkan.', $this);

                return;
            }

            Toast::error('Jenis item kalender tidak valid.', $this);
        } catch (\Throwable $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function openDayMenu(string $date): void
    {
        if (! $this->ensureCanEditCalendarDate($date)) {
            return;
        }

        $this->dayMenuDate = $date;
        $setting = ShiftDaySetting::query()->whereDate('work_date', $date)->first();
        $this->dayMenuWorkMinutes = $setting?->work_duration_minutes;
        $this->dayMenuBreakMinutes = $setting?->break_duration_minutes;
        $this->dayMenuIsHoliday = (bool) ($setting?->is_company_holiday);
        $this->dayMenuHolidayKind = $setting?->holiday_kind ?? ShiftDaySetting::HOLIDAY_ROUTINE;
        $this->showDayMenu = true;
    }

    public function closeDayMenu(): void
    {
        $this->showDayMenu = false;
        $this->dayMenuDate = '';
        $this->dayMenuIsHoliday = false;
        $this->dayMenuHolidayKind = ShiftDaySetting::HOLIDAY_ROUTINE;
    }

    public function setLiburRutin(ShiftCalendarService $calendar, ?string $forDate = null): void
    {
        if (! $this->ensureCalendarEditMode()) {
            return;
        }

        if (is_string($forDate) && $forDate !== '') {
            $this->dayMenuDate = $forDate;
        }
        $dates = $this->selectedDates !== [] ? $this->selectedDates : [$this->dayMenuDate];

        foreach ($dates as $date) {
            if ($date === '' || ! $this->isCalendarDateEditable($date)) {
                continue;
            }
            $calendar->setCompanyHoliday($date, ShiftDaySetting::HOLIDAY_ROUTINE, true);
        }
        Toast::success('Libur rutin diset.', $this);
        $this->closeDayMenu();
        $this->clearSelection();
    }

    public function setLiburEvent(ShiftCalendarService $calendar, ?string $forDate = null): void
    {
        if (! $this->ensureCalendarEditMode()) {
            return;
        }

        if (is_string($forDate) && $forDate !== '') {
            $this->dayMenuDate = $forDate;
        }
        $dates = $this->selectedDates !== [] ? $this->selectedDates : [$this->dayMenuDate];
        foreach ($dates as $date) {
            if ($date === '' || ! $this->isCalendarDateEditable($date)) {
                continue;
            }
            $calendar->setCompanyHoliday($date, ShiftDaySetting::HOLIDAY_EVENT, true);
        }
        Toast::success('Libur event diset.', $this);
        $this->closeDayMenu();
        $this->clearSelection();
    }

    public function clearLibur(ShiftCalendarService $calendar, ?string $forDate = null): void
    {
        if (! $this->ensureCalendarEditMode()) {
            return;
        }

        if (is_string($forDate) && $forDate !== '') {
            $this->dayMenuDate = $forDate;
        }
        $dates = $this->selectedDates !== [] ? $this->selectedDates : [$this->dayMenuDate];
        foreach ($dates as $date) {
            if ($date === '' || ! $this->isCalendarDateEditable($date)) {
                continue;
            }
            $calendar->setCompanyHoliday($date, ShiftDaySetting::HOLIDAY_ROUTINE, false);
        }
        Toast::success('Libur dilepas.', $this);
        $this->closeDayMenu();
        $this->clearSelection();
    }

    public function saveDayDurations(
        ShiftCalendarService $calendar,
        mixed $workMinutes = null,
        mixed $breakMinutes = null,
        ?string $forDate = null,
        bool $applyToWeekday = false,
        ?string $breakEarliestMode = null,
        ?string $breakEarliestTime = null,
    ): void {
        if (! $this->ensureCalendarEditMode()) {
            return;
        }

        if (is_string($forDate) && $forDate !== '') {
            $this->dayMenuDate = $forDate;
        }

        $workValue = $this->normalizeDurationInput($workMinutes);
        $breakValue = $this->normalizeDurationInput($breakMinutes);
        $breakEarliestValue = $this->normalizeBreakEarliestInput($breakEarliestMode, $breakEarliestTime);
        $this->dayMenuWorkMinutes = $workValue;
        $this->dayMenuBreakMinutes = $breakValue;

        $dates = $this->selectedDates !== [] ? $this->selectedDates : [$this->dayMenuDate];
        $anchorDate = $this->dayMenuDate !== '' ? $this->dayMenuDate : ($dates[0] ?? '');
        $weekday = $anchorDate !== ''
            ? (int) Carbon::parse($anchorDate, AppTimezone::display())->dayOfWeekIso
            : 0;
        $datesInView = array_values(array_filter(
            $this->periodDatesInView($calendar),
            fn (string $d) => $this->isCalendarDateEditable($d),
        ));

        if ($applyToWeekday && $weekday > 0) {
            $calendar->setDayDurationsForWeekday(
                $datesInView,
                $weekday,
                $workValue,
                $breakValue,
                $breakEarliestValue,
            );
        }

        foreach ($dates as $date) {
            if ($date === '' || ! $this->isCalendarDateEditable($date)) {
                continue;
            }

            $isAnchorWeekday = $weekday > 0
                && (int) Carbon::parse($date, AppTimezone::display())->dayOfWeekIso === $weekday;

            if (! $applyToWeekday || ! $isAnchorWeekday) {
                $calendar->setDayDurations($date, $workValue, $breakValue, $breakEarliestValue);
            }
        }

        $message = $workValue === null && $breakValue === null && $breakEarliestValue === null
            ? 'Override jam kerja / istirahat dikembalikan ke default.'
            : 'Jam kerja / istirahat hari disimpan.';

        Toast::success($message, $this);
        $this->closeDayMenu();
    }

    public function resetDayDurations(
        ShiftCalendarService $calendar,
        ?string $forDate = null,
        bool $applyToWeekday = false,
    ): void {
        $this->saveDayDurations($calendar, null, null, $forDate, $applyToWeekday, 'free', null);
    }

    private function normalizeBreakEarliestInput(?string $mode, ?string $time): ?string
    {
        if ($mode === null) {
            return null;
        }

        if ($mode !== 'fixed') {
            return null;
        }

        if (! is_string($time) || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return null;
        }

        return $time;
    }

    public ?string $memberPanelEmployeeId = null;

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

    public function toggleLiburKaryawan(string $employeeId, ShiftCalendarService $calendar): void
    {
        if ($this->calendarEditMode && ! $this->isCalendarDateEditable($this->memberPanelDate)) {
            Toast::error('Hari di luar bulan ini tidak dapat diubah di tampilan Kalender.', $this);

            return;
        }

        if (! $this->canManageMembersOnDate($this->memberPanelDate)) {
            Toast::error('Hari yang sudah lewat tidak dapat diubah di mode baca.', $this);

            return;
        }

        if (! $this->ensureCanManageLiburOnDate($this->memberPanelDate)) {
            return;
        }

        $on = $calendar->toggleEmployeeLibur($employeeId, $this->memberPanelDate, 'pattern');
        Toast::success($on ? 'Libur rutin diset.' : 'Libur rutin dilepas.', $this);
    }

    public function moveEmployeeShift(string $employeeId, string $scheduleId, ShiftCalendarService $calendar): void
    {
        if ($this->calendarEditMode && ! $this->ensureCanEditCalendarDate($this->memberPanelDate)) {
            return;
        }

        if (! $this->canManageMembersOnDate($this->memberPanelDate)) {
            Toast::error('Hari yang sudah lewat tidak dapat diubah di mode baca.', $this);

            return;
        }

        $calendar->setShiftOverride($employeeId, $this->memberPanelDate, $scheduleId, 'admin');
        Toast::success('Tukar shift (override tanggal) disimpan.', $this);
    }

    public function clearEmployeeShiftOverride(string $employeeId, ShiftCalendarService $calendar, ?string $date = null): void
    {
        $workDate = $date ?? $this->memberPanelDate;
        if ($this->calendarEditMode && ! $this->ensureCanEditCalendarDate($workDate)) {
            return;
        }

        if (! $this->canManageMembersOnDate($workDate)) {
            Toast::error('Hari yang sudah lewat tidak dapat diubah di mode baca.', $this);

            return;
        }

        $calendar->clearShiftOverride($employeeId, $workDate);
        Toast::success('Tukar shift dibatalkan.', $this);
    }

    public function saveTemplate(ShiftCalendarService $calendar): void
    {
        if (! $this->ensureCalendarEditMode() || ! $this->ensureRepeatingAnchorView()) {
            return;
        }

        $data = $this->validate([
            'templateName' => ['required', 'string', 'max:120'],
        ]);
        $tpl = $calendar->saveTemplateFromCurrentView(
            $data['templateName'],
            $this->calendarViewMode,
            $this->blockStart,
            $this->viewYear,
            $this->viewMonth,
            false,
        );
        $this->rememberActiveTemplate((string) $tpl->id);
        $this->closeSaveNewTemplateModalPublic();
        Toast::success('Template baru disimpan.', $this);
    }

    public function updateSelectedTemplate(ShiftCalendarService $calendar): void
    {
        if (! $this->ensureCalendarEditMode() || ! $this->ensureRepeatingAnchorView()) {
            return;
        }

        if ($this->selectedTemplateId === '') {
            Toast::error('Pilih template dulu.', $this);

            return;
        }

        $tpl = ShiftScheduleTemplate::findOrFail($this->selectedTemplateId);
        $calendar->updateTemplateFromCurrentView(
            $tpl,
            $this->calendarViewMode,
            $this->blockStart,
            $this->viewYear,
            $this->viewMonth,
        );
        $this->closeTemplateSettingsModal();
        Toast::success('Template "'.$tpl->name.'" diperbarui.', $this);
    }

    public function deleteTemplate(): void
    {
        if ($this->selectedTemplateId === '') {
            Toast::error('Pilih template dulu.', $this);

            return;
        }

        $tpl = ShiftScheduleTemplate::findOrFail($this->selectedTemplateId);
        $name = $tpl->name;
        $deletedId = (string) $tpl->id;
        $tpl->delete();

        if ($this->activeTemplateId === $deletedId) {
            $this->clearActiveTemplate();
        }

        $this->selectedTemplateId = ShiftScheduleTemplate::query()->orderByDesc('is_active')->value('id') ?? '';
        $this->closeTemplateSettingsModal();
        Toast::success('Template "'.$name.'" dihapus.', $this);
    }

    public function clearCalendar(ShiftCalendarService $calendar): void
    {
        if (! $this->ensureCalendarEditMode() || ! $this->ensureRepeatingAnchorView()) {
            return;
        }

        if ($this->calendarViewMode === 'month') {
            $calendar->clearMonth($this->viewYear, $this->viewMonth);
        } else {
            $calendar->clearBlock($this->blockStart);
        }
        Toast::success('Kalender dikosongkan.', $this);
    }

    public function activateRepeatingPattern(ShiftCalendarService $calendar): void
    {
        if (! $this->ensureCalendarEditMode()) {
            return;
        }

        if ($this->calendarViewMode !== 'block') {
            Toast::error('Pola berulang hanya tersedia di tampilan Blok 4 Minggu.', $this);

            return;
        }

        if ($this->selectedTemplateId === '' && $this->activeTemplateId === '') {
            Toast::error('Pilih template dulu.', $this);

            return;
        }

        $tpl = ShiftScheduleTemplate::findOrFail(
            $this->selectedTemplateId !== '' ? $this->selectedTemplateId : $this->activeTemplateId,
        );
        $calendar->applyRepeatingPattern($tpl, $this->blockStart);
        $this->rememberActiveTemplate((string) $tpl->id);
        Toast::success(
            'Pola berulang diaktifkan. Jadwal di blok setelah periode acuan diganti mengikuti pola 4 minggu ini.',
            $this,
        );
    }

    public function deactivateRepeatingPattern(ShiftCalendarService $calendar): void
    {
        if (! $this->ensureCalendarEditMode()) {
            return;
        }

        if ($this->selectedTemplateId === '' && $this->activeTemplateId === '') {
            Toast::error('Pilih template dulu.', $this);

            return;
        }

        $tpl = ShiftScheduleTemplate::findOrFail(
            $this->selectedTemplateId !== '' ? $this->selectedTemplateId : $this->activeTemplateId,
        );
        $calendar->deactivateRepeatingPattern($tpl);
        Toast::success(
            'Pola berulang dinonaktifkan. Jadwal di blok mendatang telah dikosongkan — atur tiap blok secara mandiri.',
            $this,
        );
    }

    public function loadTemplate(ShiftCalendarService $calendar): void
    {
        if (! $this->ensureCalendarEditMode() || ! $this->ensureRepeatingAnchorView()) {
            return;
        }

        if ($this->selectedTemplateId === '') {
            Toast::error('Pilih template dulu.', $this);

            return;
        }
        $tpl = ShiftScheduleTemplate::findOrFail($this->selectedTemplateId);
        $calendar->applyTemplateToCurrentView(
            $tpl,
            $this->calendarViewMode,
            $this->blockStart,
            $this->viewYear,
            $this->viewMonth,
            true,
        );
        $this->rememberActiveTemplate((string) $tpl->id);
        $this->closeTemplateSettingsModal();
        $label = $this->calendarViewMode === 'month' ? 'bulan ini' : 'blok ini';
        Toast::success('Template "'.$tpl->name.'" diaktifkan dan dimuat ke '.$label.'.', $this);
    }

    public function copyWeek(ShiftCalendarService $calendar): void
    {
        if (! $this->ensureCalendarEditMode() || ! $this->ensureRepeatingAnchorView()) {
            return;
        }

        $source = $this->copySourceWeek;
        $target = $this->copyTargetWeek;

        if ($source === '' || $target === '') {
            Toast::error('Pilih sumber dan tujuan copy minggu.', $this);

            return;
        }

        $weekStarts = collect($calendar->boardPayload(
            $this->calendarViewMode,
            $this->blockStart,
            $this->viewYear,
            $this->viewMonth,
            true,
        )['block']['weeks'] ?? [])->map(fn ($w) => $w[0] ?? null)->filter()->values()->all();

        if ($source === self::COPY_ALL_WEEKS && $target === self::COPY_NEXT_PAGE) {
            $calendar->copyAllWeeksToNextPeriod(
                $this->calendarViewMode,
                $this->blockStart,
                $this->viewYear,
                $this->viewMonth,
            );
            Toast::success('Pola minggu disalin ke halaman berikutnya.', $this);

            return;
        }

        if ($target === self::COPY_ALL_WEEKS) {
            if ($source === self::COPY_ALL_WEEKS) {
                Toast::error('Pilih minggu sumber spesifik atau tujuan Halaman Berikutnya.', $this);

                return;
            }

            $this->validate(['copySourceWeek' => ['required', 'date']]);
            $calendar->copyWeekToAllWeeksOnPage($source, $weekStarts);
            Toast::success('Pola minggu disalin ke semua minggu di halaman ini.', $this);

            return;
        }

        if ($source === self::COPY_ALL_WEEKS) {
            Toast::error('Pilih tujuan Halaman Berikutnya saat sumber Semua Minggu.', $this);

            return;
        }

        if ($target === self::COPY_NEXT_PAGE) {
            Toast::error('Halaman Berikutnya hanya tersedia saat sumber Semua Minggu.', $this);

            return;
        }

        $data = $this->validate([
            'copySourceWeek' => ['required', 'date'],
            'copyTargetWeek' => ['required', 'date', 'different:copySourceWeek'],
        ]);
        $calendar->copyWeek($data['copySourceWeek'], $data['copyTargetWeek']);
        Toast::success('Pola minggu disalin.', $this);
    }

    public function updatedCopySourceWeek(): void
    {
        if ($this->copySourceWeek !== self::COPY_ALL_WEEKS && $this->copyTargetWeek === self::COPY_NEXT_PAGE) {
            $this->copyTargetWeek = '';
        }
    }

    public function approveSwap(string $id, ShiftSwapService $swaps): void
    {
        try {
            $req = ShiftSwapRequest::query()->findOrFail($id);
            $swaps->approve($req, auth()->user());
            Toast::success('Tukar shift disetujui.', $this);
        } catch (\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    public function rejectSwap(string $id, ShiftSwapService $swaps): void
    {
        try {
            $req = ShiftSwapRequest::query()->findOrFail($id);
            $swaps->reject($req, auth()->user());
            Toast::success('Tukar shift ditolak.', $this);
        } catch (\RuntimeException $e) {
            Toast::error($e->getMessage(), $this);
        }
    }

    private function normalizeDurationInput(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        $minutes = (int) $value;

        return $minutes > 0 ? $minutes : null;
    }

    private function calcClockOut(string $clockIn, int $workMinutes, int $breakMinutes): string
    {
        [$h, $m] = array_map('intval', explode(':', $clockIn));
        $total = $h * 60 + $m + $workMinutes + $breakMinutes;
        $total = $total % (24 * 60);

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    public function with(ShiftGroupService $groupService, ShiftCalendarService $calendar): array
    {
        $isGroups = $this->tab === 'groups';
        $isCalendar = $this->tab === 'calendar';
        $isSwaps = $this->tab === 'swaps';

        $schedules = WorkSchedule::query()
            ->withCount('assignments')
            ->orderBy('clock_in_time')
            ->orderBy('name')
            ->get();

        $today = AppTimezone::nowDisplay()->toDateString();
        $groupCards = collect();
        $unassignedCard = [
            'id' => '',
            'name' => 'Unassigned',
            'color' => '#94a3b8',
            'is_solo' => false,
            'is_unassigned' => true,
            'members' => [],
        ];

        if ($isGroups) {
            $namedGroups = ShiftGroup::query()
                ->where('is_system_unassigned', false)
                ->where('is_solo', false)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
            $unassigned = $groupService->ensureUnassigned();

            $groupCards = $namedGroups->map(function (ShiftGroup $g) use ($groupService, $today) {
                return [
                    'id' => $g->id,
                    'name' => $g->name,
                    'color' => $g->color,
                    'is_solo' => $g->is_solo,
                    'is_unassigned' => false,
                    'members' => $groupService->membersOnDate($g, $today),
                ];
            });

            $unassignedCard = [
                'id' => $unassigned->id,
                'name' => 'Unassigned',
                'color' => $unassigned->color,
                'is_solo' => false,
                'is_unassigned' => true,
                'members' => $groupService->membersOnDate($unassigned, $today),
            ];

            if (trim($this->groupSearch) !== '') {
                $q = mb_strtolower(trim($this->groupSearch));
                $filterMembers = function (array $card) use ($q) {
                    $card['members'] = collect($card['members'])->filter(function ($m) use ($q) {
                        return str_contains(mb_strtolower($m['full_name']), $q)
                            || str_contains(mb_strtolower((string) ($m['employee_code'] ?? '')), $q);
                    })->values()->all();

                    return $card;
                };
                $groupCards = $groupCards->map($filterMembers);
                $unassignedCard = $filterMembers($unassignedCard);
            }
        }

        if ($isCalendar) {
            $board = $calendar->boardPayload(
                $this->calendarViewMode,
                $this->blockStart,
                $this->viewYear,
                $this->viewMonth,
                $this->calendarEditMode,
            );
            $templates = ShiftScheduleTemplate::query()
                ->orderByDesc('is_default')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get();
        } else {
            $block = $this->blockStart !== ''
                ? $calendar->fourWeekBlock($this->blockStart)
                : ['start' => '', 'end' => '', 'weeks' => []];
            $board = [
                'block' => $block,
                'weeks' => [],
                'schedules' => collect(),
                'poolGroups' => collect(),
                'poolEmployees' => collect(),
                'today' => $today,
            ];
            $templates = collect();
        }

        $memberPanelMembers = [];
        $memberPanelLiburIds = [];
        $memberPanelOverrides = [];
        $memberPanelGroupName = '';
        if ($isCalendar && $this->showMemberPanel) {
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
            $memberPanelLiburIds = \App\Models\ShiftEmployeeLibur::query()
                ->whereDate('work_date', $this->memberPanelDate)
                ->whereIn('employee_id', collect($memberPanelMembers)->pluck('id'))
                ->pluck('employee_id')
                ->map(fn ($id) => (string) $id)
                ->all();
            $memberPanelOverrides = \App\Models\ShiftEmployeeShiftOverride::query()
                ->whereDate('work_date', $this->memberPanelDate)
                ->whereIn('employee_id', collect($memberPanelMembers)->pluck('id'))
                ->get()
                ->keyBy(fn ($r) => (string) $r->employee_id);
        }

        $weekStarts = collect($board['block']['weeks'] ?? [])->map(fn ($w) => $w[0] ?? null)->filter()->values()->all();

        $pendingSwapCount = ShiftSwapRequest::query()
            ->where('status', ShiftSwapRequest::STATUS_PENDING)
            ->count();

        $pendingSwaps = collect();
        $recentSwaps = collect();
        if ($isSwaps) {
            $pendingSwaps = ShiftSwapRequest::query()
                ->with(['employee', 'toSchedule'])
                ->where('status', ShiftSwapRequest::STATUS_PENDING)
                ->orderBy('work_date')
                ->orderBy('created_at')
                ->get();

            $recentSwaps = ShiftSwapRequest::query()
                ->with(['employee', 'toSchedule'])
                ->where('status', '!=', ShiftSwapRequest::STATUS_PENDING)
                ->orderByDesc('reviewed_at')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();
        }

        $periodLabel = '';
        $activeTemplate = null;
        if ($isCalendar) {
            if ($this->calendarViewMode === 'month') {
                $periodLabel = Carbon::create($this->viewYear, $this->viewMonth, 1, 0, 0, 0, AppTimezone::display())
                    ->locale('id')
                    ->translatedFormat('F Y');
            } else {
                $periodLabel = Carbon::parse($board['block']['start'])->translatedFormat('d M')
                    .' – '.Carbon::parse($board['block']['end'])->translatedFormat('d M Y');
            }

            if ($this->activeTemplateId !== '') {
                $activeTemplate = $templates->first(
                    fn ($t) => (string) $t->id === $this->activeTemplateId,
                );
            }
        }

        $repeatingPatternActive = $isCalendar && $activeTemplate?->is_default === true;
        $repeatingAnchorStart = $repeatingPatternActive
            ? ($activeTemplate->payload['anchor_start'] ?? null)
            : null;
        $repeatingAnchorEnd = is_string($repeatingAnchorStart) && $repeatingAnchorStart !== ''
            ? Carbon::parse($repeatingAnchorStart, AppTimezone::display())->addDays(27)->toDateString()
            : null;
        $viewingRepeatingAnchor = ! $repeatingPatternActive
            || (
                $this->calendarViewMode === 'block'
                && $repeatingAnchorStart !== null
                && $this->blockStart === $repeatingAnchorStart
            );
        $repeatingEditLocked = $this->calendarEditMode
            && $repeatingPatternActive
            && ! $viewingRepeatingAnchor;

        $selectedTemplateForRepeating = null;
        $selectedTemplateRepeatingActive = false;
        if ($isCalendar && $this->selectedTemplateId !== '') {
            $selectedTemplateForRepeating = $templates->first(
                fn ($t) => (string) $t->id === $this->selectedTemplateId,
            );
            $selectedTemplateRepeatingActive = $selectedTemplateForRepeating?->is_default === true;
        }

        return [
            'schedules' => $schedules,
            'groupCards' => $groupCards,
            'unassignedCard' => $unassignedCard,
            'board' => $board,
            'templates' => $templates,
            'activeTemplate' => $activeTemplate,
            'memberPanelMembers' => $memberPanelMembers,
            'memberPanelLiburIds' => $memberPanelLiburIds,
            'memberPanelOverrides' => $memberPanelOverrides,
            'memberPanelGroupName' => $memberPanelGroupName,
            'weekStarts' => $weekStarts,
            'weekdayLabels' => ['SENIN', 'SELASA', 'RABU', 'KAMIS', 'JUMAT', 'SABTU', 'MINGGU'],
            'pendingSwaps' => $pendingSwaps,
            'recentSwaps' => $recentSwaps,
            'pendingSwapCount' => $pendingSwapCount,
            'repeatingPatternActive' => $repeatingPatternActive,
            'repeatingAnchorStart' => $repeatingAnchorStart,
            'repeatingAnchorEnd' => $repeatingAnchorEnd,
            'viewingRepeatingAnchor' => $viewingRepeatingAnchor,
            'repeatingEditLocked' => $repeatingEditLocked,
            'selectedTemplateRepeatingActive' => $selectedTemplateRepeatingActive,
            'periodLabel' => $periodLabel,
        ];
    }
}; ?>

<div class="flex flex-col min-h-0 flex-1">
<div class="bg-white border-b border-gray-200 shrink-0">
    <div class="py-3 px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Shift Kerja</h2>
        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            @if ($tab === 'calendar')
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
                        @disabled($calendarEditMode && $repeatingPatternActive)
                        @class([
                            'inline-flex items-center justify-center h-7 shrink-0 rounded px-2.5 text-xs font-semibold whitespace-nowrap transition',
                            'cursor-not-allowed opacity-40' => $calendarEditMode && $repeatingPatternActive,
                            $calendarViewMode === 'month'
                                ? 'bg-[#f7340d] text-white'
                                : 'text-gray-500 hover:text-gray-700',
                        ])
                    >
                        Kalender
                    </button>
                </div>
                <button
                    type="button"
                    wire:click="toggleCalendarEditMode"
                    class="inline-flex items-center justify-center h-8 shrink-0 rounded-md px-3 text-xs font-semibold whitespace-nowrap bg-gray-800 text-white hover:bg-gray-700"
                >
                    {{ $calendarEditMode ? 'Keluar' : 'Atur Jadwal' }}
                </button>
            @endif
            @if ($tab === 'rules')
                <button
                    type="button"
                    wire:click="openCreateRule"
                    class="inline-flex items-center justify-center h-8 shrink-0 rounded-md px-3 text-xs font-semibold whitespace-nowrap bg-gray-800 text-white hover:bg-gray-700"
                >
                    + Buat Shift
                </button>
            @endif
            @if ($tab === 'groups')
                <input
                    type="search"
                    wire:model.live.debounce.300ms="groupSearch"
                    placeholder="Cari karyawan…"
                    class="h-8 min-w-[12rem] rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                <button
                    type="button"
                    wire:click="openCreateGroup"
                    class="inline-flex items-center justify-center h-8 shrink-0 rounded-md px-3 text-xs font-semibold whitespace-nowrap bg-gray-800 text-white hover:bg-gray-700"
                >
                    + Buat Group
                </button>
            @endif
        </div>
    </div>
</div>

<div class="h-[calc(100vh-8rem)] flex flex-col" x-data="{
    dragGroupId: null,
    dragEmployeeId: null,
    dragCalendarPayload: null,
    dragKind: null,
    selectedDates: [],
    onDragStartGroup(id, e) { this.dragKind='group'; this.dragGroupId=id; this.dragEmployeeId=null; this.dragCalendarPayload=null; e.dataTransfer.setData('text/plain', 'group:'+id); },
    onDragStartEmployee(id, e) { this.dragKind='employee'; this.dragEmployeeId=id; this.dragGroupId=null; this.dragCalendarPayload=null; e.dataTransfer.setData('text/plain', 'employee:'+id); },
    onDragStartCalendarChip(payload, e) {
        this.dragCalendarPayload = payload;
        this.dragKind = payload.kind + '-cal';
        e.dataTransfer.setData('text/plain', JSON.stringify(payload));
    },
    onDropToShift(scheduleId, date, e) {
        e.preventDefault();
        const raw = e.dataTransfer.getData('text/plain') || '';
        if (raw.startsWith('group:')) {
            $wire.placeGroup(raw.slice(6), scheduleId, date);
        } else if (raw.startsWith('employee:')) {
            $wire.placeEmployee(raw.slice(9), scheduleId, date);
        } else if (raw.startsWith('{')) {
            try {
                const p = JSON.parse(raw);
                if (p.kind === 'group') {
                    $wire.moveCalendarChip('group', p.group_id, p.date, p.schedule_id, date, scheduleId);
                } else if (p.kind === 'employee') {
                    $wire.moveCalendarChip('employee', p.employee_id, p.date, p.schedule_id, date, scheduleId);
                }
            } catch (_) {}
        } else if (this.dragKind==='group' && this.dragGroupId) {
            $wire.placeGroup(this.dragGroupId, scheduleId, date);
        } else if (this.dragKind==='employee' && this.dragEmployeeId) {
            $wire.placeEmployee(this.dragEmployeeId, scheduleId, date);
        }
        this.dragKind=null; this.dragGroupId=null; this.dragEmployeeId=null; this.dragCalendarPayload=null;
    },
    onDropToUnassigned(e) {
        e.preventDefault();
        const raw = e.dataTransfer.getData('text/plain') || '';
        if (raw.startsWith('{')) {
            try {
                const p = JSON.parse(raw);
                if (p.kind === 'employee') {
                    $wire.removeEmployeeFromCalendar(p.employee_id, p.date, p.schedule_id);
                } else if (p.kind === 'group') {
                    $wire.removeGroupChip(p.group_id, p.date, p.schedule_id);
                } else if (p.kind === 'override') {
                    $wire.clearEmployeeShiftOverride(p.employee_id, p.date);
                }
            } catch (_) {}
        }
        this.dragKind=null; this.dragGroupId=null; this.dragEmployeeId=null; this.dragCalendarPayload=null;
    },
    onDropToGroup(groupId, e) {
        e.preventDefault();
        const raw = e.dataTransfer.getData('text/plain') || '';
        const empId = raw.startsWith('employee:') ? raw.slice(9) : this.dragEmployeeId;
        if (empId) { $wire.moveEmployeeToGroup(empId, groupId); }
        this.dragKind=null; this.dragEmployeeId=null;
    },
    init() {
        this.selectedDates = Array.isArray(this.$wire.selectedDates) ? [...this.$wire.selectedDates] : [];
        this.showPoolPanel = !!this.$wire.calendarEditMode;
        this.$wire.$watch('selectedDates', (value) => {
            this.selectedDates = Array.isArray(value) ? [...value] : [];
        });
        this.$wire.$watch('calendarEditMode', (value) => {
            if (value) {
                this.showPoolPanel = true;
            } else {
                this.showPoolPanel = false;
                this.showDayMenu = false;
                this.showTemplateSettings = false;
            }
        });
        this._exitEditOnNavigate = () => {
            if (this.$wire?.calendarEditMode) {
                this.$wire.exitCalendarEditModeForNavigation();
            }
        };
        document.addEventListener('livewire:navigating', this._exitEditOnNavigate);
    },
    onCalendarUiClick(e) {
        const dayBtn = e.target.closest('[data-open-day-menu]');
        if (dayBtn) {
            e.preventDefault();
            e.stopPropagation();
            const raw = dayBtn.getAttribute('data-day-menu');
            if (raw) {
                try { this.openDayMenuQuick(JSON.parse(raw)); } catch (_) {}
            }
            return;
        }
        if (e.target.closest('[data-open-template-settings]')) {
            e.preventDefault();
            this.openTemplateQuick();
            return;
        }
        const selectBtn = e.target.closest('[data-toggle-select-date]');
        if (selectBtn) {
            e.preventDefault();
            this.toggleSelectDate(selectBtn.dataset.toggleSelectDate);
            return;
        }
        if (e.target.closest('[data-toggle-pool-panel]')) {
            e.preventDefault();
            this.showPoolPanel = !this.showPoolPanel;
            return;
        }
        if (e.target.closest('[data-clear-selected-dates]')) {
            e.preventDefault();
            this.selectedDates = [];
            this.$wire.set('selectedDates', [], false);
        }
    },
    isSelected(date) {
        return Array.isArray(this.selectedDates) && this.selectedDates.includes(date);
    },
    toggleSelectDate(date) {
        const cur = Array.isArray(this.selectedDates) ? this.selectedDates : [];
        this.selectedDates = cur.includes(date)
            ? cur.filter((d) => d !== date)
            : [...cur, date];
        this.$wire.set('selectedDates', this.selectedDates, false);
    },
    showDayMenu: false,
    showTemplateSettings: false,
    dayMenu: {
        date: '',
        label: '',
        isHoliday: false,
        holidayKind: 'routine',
        workMinutes: '',
        breakMinutes: '',
        breakEarliestMode: 'free',
        breakEarliestTime: '12:00',
        hasDurationOverride: false,
        applyScope: 'date',
        dateApplyLabel: '',
        weekdayApplyLabel: '',
    },
    openDayMenuQuick(payload) {
        this.dayMenu = {
            date: payload.date,
            label: payload.label,
            isHoliday: !!payload.isHoliday,
            holidayKind: payload.holidayKind || 'routine',
            workMinutes: payload.workMinutes ?? '',
            breakMinutes: payload.breakMinutes ?? '',
            breakEarliestMode: payload.breakEarliestMode || 'free',
            breakEarliestTime: payload.breakEarliestTime || '12:00',
            hasDurationOverride: !!payload.hasDurationOverride,
            applyScope: 'date',
            dateApplyLabel: payload.dateApplyLabel || payload.label,
            weekdayApplyLabel: payload.weekdayApplyLabel || '',
        };
        this.showDayMenu = true;
    },
    closeDayMenuQuick() {
        this.showDayMenu = false;
    },
    async runDayMenuAction(action) {
        await action();
        this.closeDayMenuQuick();
    },
    openTemplateQuick() {
        this.showTemplateSettings = true;
        if (this.$wire.activeTemplateId) {
            this.$wire.set('selectedTemplateId', this.$wire.activeTemplateId);
        }
        this.$wire.set('templateName', '', false);
    },
    closeTemplateQuick() {
        this.showTemplateSettings = false;
        this.$wire.closeTemplateSettingsModalPublic();
    },
    showPoolPanel: false,
}" @click="onCalendarUiClick($event)">
    <div class="flex-1 flex flex-col min-h-0 px-4 sm:px-6 lg:px-8 py-4 space-y-4">
        <div class="bg-white shadow-sm rounded-lg overflow-hidden flex-1 flex flex-col min-h-0">
            <nav class="shrink-0 border-b border-gray-200 px-4">
                <div class="flex gap-1 -mb-px overflow-x-auto">
                    @foreach ([
                        'rules' => 'Shift',
                        'groups' => 'Group',
                        'calendar' => 'Jadwal Shift',
                        'swaps' => 'Tukar Shift'.($pendingSwapCount ? ' ('.$pendingSwapCount.')' : ''),
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

            <div @class([
                'flex-1 min-h-0 p-4 sm:p-6',
                $tab === 'calendar' ? 'overflow-hidden flex flex-col' : 'overflow-y-auto',
            ])>
                {{-- ========== RULE SHIFT ========== --}}
                @if ($tab === 'rules')
                    <div class="mb-4">
                        <h3 class="text-base font-semibold text-gray-900">Shift</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Jam masuk, pulang, dan telat per sif. Nonaktifkan shift sementara (mis. Ramadan) tanpa menghapus — aktifkan lagi saat dibutuhkan.</p>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="w-full min-w-[1180px] table-fixed divide-y divide-gray-200 text-sm">
                            <colgroup>
                                <col style="width: 17%">
                                <col style="width: 20%">
                                <col style="width: 7%">
                                <col style="width: 11%">
                                <col style="width: 7%">
                                <col style="width: 8%">
                                <col style="width: 10%">
                                <col style="width: 9%">
                                <col style="width: 5%">
                                <col style="width: 6%">
                            </colgroup>
                            <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-2.5">Nama Shift</th>
                                    <th class="px-4 py-2.5">Implementasi</th>
                                    <th class="px-4 py-2.5">Masuk</th>
                                    <th class="px-4 py-2.5">Terlambat Setelah</th>
                                    <th class="px-4 py-2.5">Pulang</th>
                                    <th class="px-4 py-2.5">Jam Kerja</th>
                                    <th class="px-4 py-2.5">Durasi Istirahat</th>
                                    <th class="px-4 py-2.5">Jam Istirahat</th>
                                    <th class="px-4 py-2.5">Aktif</th>
                                    <th class="px-4 py-2.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse ($schedules as $row)
                                    @php
                                        $specificDateLabels = $row->implementationSpecificDateLabels();
                                    @endphp
                                    <tr wire:key="rule-{{ $row->id }}" @class(['opacity-60' => ! $row->is_enabled])>
                                        <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap truncate">
                                            {{ $row->name }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                                            @if (count($specificDateLabels) > 1)
                                                <span
                                                    x-data="{
                                                        showImplementationTip: false,
                                                        implementationTipStyle: '',
                                                        showTip() {
                                                            const r = this.$el.getBoundingClientRect();
                                                            const left = Math.max(8, Math.min(r.left, window.innerWidth - 288));
                                                            this.implementationTipStyle = `left:${left}px;top:${r.top - 4}px;transform:translateY(-100%)`;
                                                            this.showImplementationTip = true;
                                                        },
                                                    }"
                                                    class="inline-block cursor-help border-b border-dotted border-gray-400"
                                                    tabindex="0"
                                                    @mouseenter="showTip()"
                                                    @mouseleave="showImplementationTip = false"
                                                    @focus="showTip()"
                                                    @blur="showImplementationTip = false"
                                                >
                                                    <template x-teleport="body">
                                                        <span
                                                            x-show="showImplementationTip"
                                                            role="tooltip"
                                                            :style="implementationTipStyle"
                                                            class="pointer-events-none fixed z-[9999] max-w-[280px] rounded-md bg-gray-900 px-2.5 py-1.5 text-xs font-medium leading-snug text-white shadow-lg whitespace-normal"
                                                        >
                                                            @foreach ($specificDateLabels as $specificDateLabel)
                                                                <span class="block whitespace-nowrap">{{ $specificDateLabel }}</span>
                                                            @endforeach
                                                        </span>
                                                    </template>
                                                    {{ $row->implementationSummary() }}
                                                </span>
                                            @else
                                                {{ $row->implementationSummary() }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 font-mono tabular-nums">{{ substr((string) $row->clock_in_time, 0, 5) }}</td>
                                        <td class="px-4 py-3 font-mono tabular-nums">{{ substr((string) ($row->late_after_time ?: $row->clock_in_time), 0, 5) }}</td>
                                        <td class="px-4 py-3 font-mono tabular-nums">{{ substr((string) $row->clock_out_time, 0, 5) }}</td>
                                        <td class="px-4 py-3">{{ (($row->work_duration_minutes ?? 480) / 60) }} jam</td>
                                        <td class="px-4 py-3">{{ $row->break_duration_minutes }} m</td>
                                        <td class="px-4 py-3 font-mono tabular-nums">
                                            {{ filled($row->break_earliest_time) ? substr((string) $row->break_earliest_time, 0, 5) : 'Bebas' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <button type="button"
                                                wire:click="toggleShiftEnabled('{{ $row->id }}')"
                                                role="switch"
                                                aria-checked="{{ $row->is_enabled ? 'true' : 'false' }}"
                                                title="{{ $row->is_enabled ? 'Nonaktifkan shift' : 'Aktifkan shift' }}"
                                                @class([
                                                    'relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                                                    'bg-indigo-600' => $row->is_enabled,
                                                    'bg-gray-200' => ! $row->is_enabled,
                                                ])>
                                                <span @class([
                                                    'pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition',
                                                    'translate-x-4' => $row->is_enabled,
                                                    'translate-x-0' => ! $row->is_enabled,
                                                ])></span>
                                            </button>
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <button type="button" wire:click="openEditRule('{{ $row->id }}')" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">Edit</button>
                                            <button type="button" wire:click="deleteRule('{{ $row->id }}')" wire:confirm="Hapus shift {{ $row->name }}?" class="ml-3 text-red-600 hover:text-red-800 text-sm font-medium">Hapus</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10" class="px-4 py-8 text-center text-gray-500">Belum ada shift. Buat shift pertama.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- ========== GROUP ========== --}}
                @if ($tab === 'groups')
                    <div class="mb-4">
                        <h3 class="text-base font-semibold text-gray-900">Group</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Kelompokkan karyawan untuk jadwal tim. Karyawan tanpa group tetap di Unassigned — tarik langsung ke kalender tanpa perlu buat group.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        @foreach ($groupCards as $card)
                            <div wire:key="gcard-{{ $card['id'] }}"
                                class="rounded-lg border border-gray-200 bg-white flex flex-col min-h-[12rem]"
                                @dragover.prevent
                                @drop.prevent="onDropToGroup('{{ $card['id'] }}', $event)">
                                <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-100">
                                    <span class="h-3 w-3 rounded-full shrink-0" style="background: {{ $card['color'] }}"></span>
                                    <span class="font-semibold text-sm text-gray-900 truncate flex-1">{{ $card['name'] }}</span>
                                    <span class="text-xs text-gray-400">{{ count($card['members']) }}</span>
                                    @unless ($card['is_solo'])
                                        <button type="button" wire:click="openEditGroup('{{ $card['id'] }}')" class="text-xs text-indigo-600">Edit</button>
                                        <button type="button" wire:click="deleteGroup('{{ $card['id'] }}')" wire:confirm="Hapus group {{ $card['name'] }}?" class="text-xs text-red-600">Hapus</button>
                                    @endunless
                                </div>
                                <div class="flex-1 p-2 space-y-1 overflow-y-auto max-h-64">
                                    @forelse ($card['members'] as $m)
                                        <div draggable="true"
                                            @dragstart="onDragStartEmployee('{{ $m['id'] }}', $event)"
                                            class="cursor-grab active:cursor-grabbing rounded-md bg-gray-50 hover:bg-gray-100 px-2 py-1.5 text-sm text-gray-800">
                                            {{ $m['full_name'] }}
                                            @if ($m['employee_code'])
                                                <span class="text-xs text-gray-400">{{ $m['employee_code'] }}</span>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-xs text-gray-400 px-1 py-2">Kosong — drop karyawan ke sini</p>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach

                        <div wire:key="gcard-unassigned"
                            class="rounded-lg border border-dashed border-gray-300 bg-slate-50 flex flex-col min-h-[12rem]"
                            @dragover.prevent
                            @drop.prevent="onDropToGroup('{{ $unassignedCard['id'] }}', $event)">
                            <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-200">
                                <span class="h-3 w-3 rounded-full shrink-0 bg-slate-400"></span>
                                <span class="font-semibold text-sm text-gray-700 truncate flex-1">Unassigned</span>
                                <span class="text-xs text-gray-400">{{ count($unassignedCard['members']) }}</span>
                            </div>
                            <div class="flex-1 p-2 space-y-1 overflow-y-auto max-h-64">
                                @forelse ($unassignedCard['members'] as $m)
                                    <div draggable="true"
                                        @dragstart="onDragStartEmployee('{{ $m['id'] }}', $event)"
                                        class="cursor-grab active:cursor-grabbing rounded-md bg-white border border-gray-100 px-2 py-1.5 text-sm text-gray-800">
                                        {{ $m['full_name'] }}
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400 px-1 py-2">Semua karyawan sudah punya group</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ========== CALENDAR ========== --}}
                @if ($tab === 'calendar')
                    @php
                        $copyFieldStyle = 'width:7.84rem;min-width:7.84rem;max-width:7.84rem;height:2rem;box-sizing:border-box;';
                        $fieldClass = 'block rounded-md border border-gray-300 bg-white px-2.5 text-xs text-gray-900 placeholder:text-gray-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500';
                        $fieldWrapClass = 'flex flex-col gap-1 shrink-0';
                        $btnClass = 'inline-flex items-center justify-center h-8 shrink-0 rounded-md px-3 text-xs font-semibold whitespace-nowrap';
                    @endphp
                    <div class="flex flex-col flex-1 min-h-0 -m-4 sm:-m-6">
                        <div class="shift-calendar-toolbar shrink-0 z-30 bg-white px-4 sm:px-6 pt-3 pb-3">
                            <div class="flex flex-wrap items-end gap-2">
                                <div class="{{ $fieldWrapClass }} justify-end shrink-0" wire:key="toolbar-active-template-{{ $activeTemplateId }}">
                                    <span class="text-xs text-gray-500 leading-none">Template Aktif</span>
                                    <div class="h-8 flex items-center gap-2 min-w-0">
                                        @if ($activeTemplate)
                                            <p class="text-base font-semibold text-gray-900 truncate max-w-[10rem] sm:max-w-[12rem]">
                                                {{ $activeTemplate->name }}
                                            </p>
                                        @else
                                            <p class="text-base font-normal text-gray-400">— belum dipilih —</p>
                                        @endif
                                        @if ($repeatingPatternActive)
                                            <span class="inline-flex shrink-0 items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 whitespace-nowrap">
                                                Pola Berulang Aktif
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                @if ($calendarEditMode)
                                    <button type="button" data-open-template-settings class="{{ $btnClass }} bg-gray-800 text-white hover:bg-gray-700">Atur Template</button>

                                    <div class="hidden sm:block w-px h-8 bg-gray-200 shrink-0"></div>

                                    <label class="{{ $fieldWrapClass }}">
                                        <span class="text-xs text-gray-500 leading-none">Copy minggu dari</span>
                                        <select wire:model.live="copySourceWeek" @disabled($repeatingEditLocked) class="{{ $fieldClass }} disabled:cursor-not-allowed disabled:opacity-40" style="{{ $copyFieldStyle }}">
                                            <option value="">— sumber —</option>
                                            <option value="{{ self::COPY_ALL_WEEKS }}">Semua Minggu</option>
                                            @foreach ($weekStarts as $i => $ws)
                                                <option value="{{ $ws }}">Minggu {{ $i + 1 }} ({{ \Illuminate\Support\Carbon::parse($ws)->translatedFormat('d M') }})</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="{{ $fieldWrapClass }}">
                                        <span class="text-xs text-gray-500 leading-none">ke</span>
                                        <select wire:model="copyTargetWeek" @disabled($repeatingEditLocked) class="{{ $fieldClass }} disabled:cursor-not-allowed disabled:opacity-40" style="{{ $copyFieldStyle }}">
                                            <option value="">— tujuan —</option>
                                            <option value="{{ self::COPY_ALL_WEEKS }}">Semua Minggu</option>
                                            @if ($copySourceWeek === self::COPY_ALL_WEEKS)
                                                <option value="{{ self::COPY_NEXT_PAGE }}">Halaman Berikutnya</option>
                                            @endif
                                            @foreach ($weekStarts as $i => $ws)
                                                <option value="{{ $ws }}">Minggu {{ $i + 1 }} ({{ \Illuminate\Support\Carbon::parse($ws)->translatedFormat('d M') }})</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <button type="button" wire:click="copyWeek" @disabled($repeatingEditLocked)
                                        class="{{ $btnClass }} bg-gray-800 text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-40">Copy</button>

                                    <div class="hidden sm:block w-px h-8 bg-gray-200 shrink-0"></div>

                                    <button type="button"
                                        wire:click="clearCalendar"
                                        wire:confirm="Kosongkan semua group dan karyawan di periode kalender ini? Libur dan pengaturan hari tidak diubah."
                                        @disabled($repeatingEditLocked)
                                        class="{{ $btnClass }} border border-red-300 bg-white text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40">Clear</button>
                                @endif

                                <div class="flex flex-wrap items-end gap-2 ml-auto">
                                    @if ($calendarEditMode)
                                        <div class="flex flex-wrap items-center gap-2 text-sm" x-show="selectedDates.length" x-cloak>
                                            <span class="text-gray-600"><span x-text="selectedDates.length"></span> tanggal dipilih</span>
                                            <button type="button" wire:click="setLiburEvent" class="rounded-md bg-amber-600 px-2.5 h-8 text-white text-xs font-medium">Libur event</button>
                                            <button type="button" wire:click="setLiburRutin" class="rounded-md bg-red-600 px-2.5 h-8 text-white text-xs font-medium hover:bg-red-700">Libur rutin</button>
                                            <button type="button" wire:click="clearLibur" class="rounded-md border border-gray-300 px-2.5 h-8 text-xs">Lepas libur</button>
                                            <button type="button" data-clear-selected-dates class="text-xs text-gray-500 underline">Batal</button>
                                        </div>
                                    @endif
                                    <div class="flex items-center gap-2 shrink-0">
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
                            @if ($repeatingEditLocked)
                                <div class="mt-2 flex items-center justify-between gap-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                    <span>Mode baca saja. Pola berulang hanya dapat diedit pada blok acuan {{ \Illuminate\Support\Carbon::parse($repeatingAnchorStart)->translatedFormat('d M') }} – {{ \Illuminate\Support\Carbon::parse($repeatingAnchorEnd)->translatedFormat('d M Y') }}.</span>
                                    <button type="button" wire:click="goToRepeatingAnchor"
                                        class="shrink-0 rounded-md bg-amber-700 px-3 py-1.5 font-semibold text-white hover:bg-amber-800">
                                        Kembali ke Blok Acuan
                                    </button>
                                </div>
                            @endif
                            @error('copySourceWeek') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            @error('copyTargetWeek') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Panel kiri (sticky) + kalender (scroll) --}}
                        <div class="flex flex-1 min-h-0 items-stretch px-4 sm:px-6">
                            @if ($calendarEditMode)
                            <div
                                x-show="showPoolPanel"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 -translate-x-2"
                                x-transition:enter-end="opacity-100 translate-x-0"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 translate-x-0"
                                x-transition:leave-end="opacity-0 -translate-x-2"
                                @class([
                                    'shift-pool-panel shrink-0 flex flex-col min-h-0 self-stretch bg-gray-50 transition-opacity',
                                    'pointer-events-none opacity-40' => $repeatingEditLocked,
                                ])
                            >
                                <div class="flex-1 min-h-0 overflow-y-auto space-y-3" style="padding: 0.5rem;">
                                    <div class="space-y-3 rounded-lg border border-gray-200 bg-white" style="padding: 0.5rem;">
                                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wide">GROUP</h4>
                                        <div class="space-y-1">
                                            @forelse ($board['poolGroups'] as $pg)
                                                <div draggable="true"
                                                    @dragstart="onDragStartGroup('{{ $pg['id'] }}', $event)"
                                                    class="cursor-grab active:cursor-grabbing flex w-full min-w-0 items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-semibold text-white"
                                                    style="background: {{ $pg['color'] }}">
                                                    <span class="truncate text-left min-w-0 flex-1">{{ $pg['name'] }}</span>
                                                    <x-chip-count :count="$pg['member_count']" class="ml-auto" />
                                                </div>
                                            @empty
                                                <p class="text-xs text-gray-400">Belum ada group. Buat di tab Group atau tarik karyawan di bawah.</p>
                                            @endforelse
                                        </div>
                                    </div>

                                    <div class="space-y-3 rounded-lg border border-gray-200 bg-white"
                                        style="padding: 0.5rem;"
                                        @dragover.prevent
                                        @drop.prevent="onDropToUnassigned($event)">
                                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wide">KARYAWAN (Unassigned)</h4>
                                        <div class="space-y-1">
                                            @forelse ($board['poolEmployees'] as $pe)
                                                <div draggable="true"
                                                    @dragstart="onDragStartEmployee('{{ $pe['id'] }}', $event)"
                                                    class="cursor-grab active:cursor-grabbing flex w-full min-w-0 items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-semibold text-white"
                                                    style="background: {{ $pe['color'] }}">
                                                    <span class="truncate text-left">{{ $pe['full_name'] }}</span>
                                                </div>
                                            @empty
                                                <p class="text-xs text-gray-400">Tidak ada karyawan unassigned.</p>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="relative z-20 flex w-0 shrink-0 items-center justify-center self-stretch">
                                <button
                                    type="button"
                                    data-toggle-pool-panel
                                    class="inline-flex h-10 w-3.5 items-center justify-center rounded-sm border border-gray-300 bg-white px-0 text-gray-500 shadow-sm hover:bg-gray-50"
                                    style="margin: -10px;"
                                    :title="showPoolPanel ? 'Sembunyikan panel' : 'Tampilkan panel'">
                                    <svg x-show="showPoolPanel" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                    </svg>
                                    <svg x-show="!showPoolPanel" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>
                            @endif

                            {{-- Kalender --}}
                            <div @class([
                                'flex-1 min-h-0 flex flex-col min-w-0',
                                $calendarEditMode ? 'border-l border-gray-200' : '',
                            ])>
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
                                    <section wire:key="shift-week-{{ $wi }}-{{ $week[0]['date'] ?? $wi }}" class="rounded-lg border border-gray-200 bg-gray-50/60 overflow-hidden">
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
                                            $cellCarbon = \Illuminate\Support\Carbon::parse($cell['date'])->locale('id');
                                            $weekdayName = ucfirst($cellCarbon->translatedFormat('l'));
                                            $dateApplyLabel = $weekdayName.' baris Minggu '.($wi + 1).' (Kolom ini)';
                                            $weekdayApplyLabel = 'Semua Hari '.$weekdayName;
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
                                            $dayMenuPayload = [
                                                'date' => $cell['date'],
                                                'label' => $cellCarbon->translatedFormat('d M Y'),
                                                'dateApplyLabel' => $dateApplyLabel,
                                                'weekdayApplyLabel' => $weekdayApplyLabel,
                                                'isHoliday' => (bool) $cell['is_holiday'],
                                                'holidayKind' => $cell['holiday_kind'] ?? 'routine',
                                                'workMinutes' => $cell['work_duration_minutes'],
                                                'breakMinutes' => $cell['break_duration_minutes'],
                                                'breakEarliestMode' => filled($cell['break_earliest_time']) ? 'fixed' : 'free',
                                                'breakEarliestTime' => $cell['break_earliest_time'] ?? '12:00',
                                                'hasDurationOverride' => filled($cell['work_duration_minutes'])
                                                    || filled($cell['break_duration_minutes'])
                                                    || filled($cell['break_earliest_time']),
                                            ];
                                            $cellInRepeatingAnchor = ! $repeatingPatternActive
                                                || (
                                                    $repeatingAnchorStart !== null
                                                    && $repeatingAnchorEnd !== null
                                                    && $cell['date'] >= $repeatingAnchorStart
                                                    && $cell['date'] <= $repeatingAnchorEnd
                                                );
                                            $cellEditable = $calendarEditMode
                                                && ($calendarViewMode !== 'month' || ! empty($cell['in_month']))
                                                && $cellInRepeatingAnchor;
                                            $hasDurationOverride = filled($cell['work_duration_minutes'])
                                                || filled($cell['break_duration_minutes'])
                                                || filled($cell['break_earliest_time']);
                                            $cellMemberManageable = $this->canManageMembersOnDate($cell['date']);
                                        @endphp
                                        <div wire:key="cell-{{ $cell['date'] }}"
                                            style="padding: 0.5rem; gap: 0.5rem"
                                            @class([
                                                'rounded border flex flex-col min-h-[11rem] text-xs relative overflow-visible',
                                                $cell['is_today'] ? 'border-blue-500 border-2' : 'border-gray-200',
                                                $cell['is_holiday'] ? 'bg-red-100' : 'bg-white',
                                                ($calendarViewMode === 'month' && empty($cell['in_month'])) ? 'shift-cell-outside-month' : '',
                                                ($calendarEditMode && ! $cellInRepeatingAnchor) ? 'shift-cell-outside-anchor' : '',
                                            ])>
                                            <div class="relative z-10 flex shrink-0 items-stretch min-w-0" style="gap: 0.5rem">
                                                @if ($cellEditable)
                                                    <button type="button" data-toggle-select-date="{{ $cell['date'] }}"
                                                        class="inline-flex flex-col shrink-0 items-center justify-center rounded font-semibold tabular-nums box-border text-white"
                                                        :style="'padding: 0.2rem 0.25rem; width: 2.5rem; min-height: 2.5rem; background-color: ' + (selectedDates.includes('{{ $cell['date'] }}') ? '#f7340d' : '#1f2937')">
                                                        <span class="text-sm leading-none">{{ $cell['day'] }}</span>
                                                        <span class="text-[10px] font-semibold uppercase leading-none mt-0.5 opacity-80">{{ $monthShort }}</span>
                                                    </button>
                                                @else
                                                    <div class="inline-flex flex-col shrink-0 items-center justify-center rounded font-semibold tabular-nums box-border text-white bg-gray-800"
                                                        style="padding: 0.2rem 0.25rem; width: 2.5rem; min-height: 2.5rem;">
                                                        <span class="text-sm leading-none">{{ $cell['day'] }}</span>
                                                        <span class="text-[10px] font-semibold uppercase leading-none mt-0.5 opacity-80">{{ $monthShort }}</span>
                                                    </div>
                                                @endif
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
                                                        @if ($cellEditable)
                                                            <button type="button"
                                                                data-open-day-menu
                                                                data-day-menu='@json($dayMenuPayload)'
                                                                title="Pengaturan hari"
                                                                @class([
                                                                    'inline-flex shrink-0 items-center justify-end rounded self-stretch pr-0.5 text-red-600 hover:text-red-800 hover:bg-red-50',
                                                                    $cell['is_holiday'] ? 'hover:bg-red-200' : '',
                                                                ])
                                                                style="padding-top: 0.25rem; padding-bottom: 0.25rem;">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>
                                                            </button>
                                                        @else
                                                            <span class="inline-flex shrink-0 items-center justify-end rounded self-stretch pr-0.5 text-red-600 cursor-default"
                                                                style="padding-top: 0.25rem; padding-bottom: 0.25rem;">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>
                                                            </span>
                                                        @endif
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
                                                @elseif ($cellEditable)
                                                <button type="button"
                                                    data-open-day-menu
                                                    data-day-menu='@json($dayMenuPayload)'
                                                    title="Pengaturan hari"
                                                    @class([
                                                        'inline-flex shrink-0 items-center justify-end rounded self-stretch pr-0.5',
                                                        $cell['is_holiday']
                                                            ? 'text-red-700 hover:text-red-900 hover:bg-red-200'
                                                            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                                                    ])
                                                    style="padding-top: 0.25rem; padding-bottom: 0.25rem;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                </button>
                                                @endif
                                            </div>

                                            @if ($cell['is_holiday'])
                                                <div class="pointer-events-none absolute inset-0 z-0 flex items-center justify-center">
                                                    <span class="text-sm font-bold tracking-wide text-red-700">LIBUR</span>
                                                </div>
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
                                                            style="padding: 0.5rem; gap: 0.5rem; background: {{ ['#dbeafe','#fce7f3','#fef9c3','#dcfce7','#e0e7ff'][$loop->index % 5] }}"
                                                            @if ($cellEditable)
                                                                @dragover.prevent
                                                                @drop.prevent="onDropToShift('{{ $sched->id }}', '{{ $cell['date'] }}', $event)"
                                                            @endif>
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
                                                                        $chipDragPayload = [
                                                                            'kind' => $chipKind,
                                                                            'date' => $cell['date'],
                                                                            'schedule_id' => $sched->id,
                                                                            'entry_id' => $chip['entry_id'],
                                                                            'group_id' => $chip['group_id'] ?? null,
                                                                            'employee_id' => $chip['employee_id'] ?? null,
                                                                        ];
                                                                        $chipDraggable = $cellEditable && in_array($chipKind, ['group', 'employee'], true);
                                                                    @endphp
                                                                    <div class="relative flex w-full min-w-0 items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-semibold text-white"
                                                                        style="background: {{ $chip['color'] }}">
                                                                        <button type="button"
                                                                            @if ($chipDraggable) draggable="true" @dragstart="onDragStartCalendarChip(@js($chipDragPayload), $event)" @endif
                                                                            @if ($chipKind === 'group')
                                                                                wire:click="openMemberPanel('{{ $cell['date'] }}', '{{ $chip['group_id'] }}')"
                                                                            @elseif (!empty($chip['employee_id']))
                                                                                wire:click="openEmployeePanel('{{ $cell['date'] }}', '{{ $chip['employee_id'] }}')"
                                                                            @endif
                                                                            class="flex-1 min-w-0 truncate text-left p-0 bg-transparent border-0 font-semibold text-white"
                                                                            title="{{ $chipKind === 'group'
                                                                                ? ($cellMemberManageable ? 'Klik: anggota · Drag: pindah' : 'Lihat anggota group')
                                                                                : ($chipKind === 'swap_pending'
                                                                                    ? 'Menunggu persetujuan tukar shift'
                                                                                    : ($cellMemberManageable ? 'Klik: pengaturan · Drag: pindah' : 'Lihat detail')) }}">
                                                                            {{ $chip['name'] }}
                                                                        </button>
                                                                        @if ($chipKind === 'group')
                                                                            <x-chip-count
                                                                                :count="max(0, (int) ($chip['member_count'] ?? 0))"
                                                                                class="shrink-0"
                                                                            />
                                                                        @endif
                                                                        @if ($cellEditable && !empty($chip['entry_id']))
                                                                            <button type="button"
                                                                                wire:click.stop="removeCalendarEntry('{{ $chip['entry_id'] }}')"
                                                                                class="shrink-0 flex items-center justify-center p-0 text-white hover:bg-white/10 rounded leading-none"
                                                                                title="Hapus dari kalender">
                                                                                <span class="text-[11px] font-bold leading-none">&times;</span>
                                                                            </button>
                                                                        @elseif ($cellEditable && $chipKind === 'override' && !empty($chip['employee_id']))
                                                                            <button type="button"
                                                                                wire:click.stop="clearEmployeeShiftOverride('{{ $chip['employee_id'] }}', '{{ $cell['date'] }}')"
                                                                                class="shrink-0 flex items-center justify-center p-0 text-white hover:bg-white/10 rounded leading-none"
                                                                                title="Batalkan tukar shift">
                                                                                <span class="text-[11px] font-bold leading-none">&times;</span>
                                                                            </button>
                                                                        @elseif (! $cellEditable && $chipKind === 'override' && !empty($chip['employee_id']))
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
                                                @if (count($cell['foot']))
                                                    <div class="border-t border-gray-100 flex flex-col min-w-0 max-h-24 overflow-y-auto" style="gap: 0.5rem; padding-top: 0.5rem">
                                                        @foreach ($cell['foot'] as $f)
                                                            <div class="flex w-full min-w-0 items-center justify-between text-[9px]" style="gap: 0.5rem">
                                                                @if (! empty($f['employee_id']) && $cellMemberManageable)
                                                                    <button type="button"
                                                                        wire:click="openEmployeePanel('{{ $cell['date'] }}', '{{ $f['employee_id'] }}')"
                                                                        class="truncate text-left text-gray-700 hover:underline"
                                                                        title="Klik untuk mengatur libur">
                                                                        {{ $f['name'] }}
                                                                    </button>
                                                                @else
                                                                    <span class="truncate text-gray-700">{{ $f['name'] }}</span>
                                                                @endif
                                                                @if (! empty($f['employee_id']))
                                                                    @if ($cellMemberManageable)
                                                                        <button type="button"
                                                                            wire:click="openEmployeePanel('{{ $cell['date'] }}', '{{ $f['employee_id'] }}')"
                                                                            title="Klik untuk mengatur libur / sif"
                                                                            @class([
                                                                                'shrink-0 rounded px-1.5 py-0.5 font-medium whitespace-nowrap cursor-pointer transition hover:opacity-80',
                                                                                $f['badge_kind'] === 'libur_request' ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : '',
                                                                                $f['badge_kind'] === 'libur_karyawan' ? 'bg-slate-200 text-slate-700 hover:bg-slate-300' : '',
                                                                                $f['badge_kind'] === 'swap' ? 'bg-indigo-100 text-indigo-800 hover:bg-indigo-200' : '',
                                                                            ])>{{ $f['badge'] }}</button>
                                                                    @else
                                                                        <span @class([
                                                                            'shrink-0 rounded px-1.5 py-0.5 font-medium whitespace-nowrap',
                                                                            $f['badge_kind'] === 'libur_request' ? 'bg-amber-100 text-amber-800' : '',
                                                                            $f['badge_kind'] === 'libur_karyawan' ? 'bg-slate-200 text-slate-700' : '',
                                                                            $f['badge_kind'] === 'swap' ? 'bg-indigo-100 text-indigo-800' : '',
                                                                        ])>{{ $f['badge'] }}</span>
                                                                    @endif
                                                                @else
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
                @endif

                {{-- ========== TUKAR SHIFT ========== --}}
                @if ($tab === 'swaps')
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Pengajuan Tukar Shift</h3>
                            <p class="text-sm text-gray-500 mt-0.5">Setujui atau tolak permintaan karyawan. Persetujuan membuat override tanggal saja (group &amp; template tidak berubah).</p>
                        </div>

                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <tr>
                                        <th class="px-4 py-2.5">Karyawan</th>
                                        <th class="px-4 py-2.5">Tanggal</th>
                                        <th class="px-4 py-2.5">Ke shift</th>
                                        <th class="px-4 py-2.5">Alasan</th>
                                        <th class="px-4 py-2.5 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    @forelse ($pendingSwaps as $swap)
                                        <tr wire:key="swap-pending-{{ $swap->id }}">
                                            <td class="px-4 py-3 font-medium text-gray-900">{{ $swap->employee?->full_name ?? '—' }}</td>
                                            <td class="px-4 py-3 tabular-nums">{{ $swap->work_date->translatedFormat('D, d M Y') }}</td>
                                            <td class="px-4 py-3">{{ $swap->toSchedule?->name ?? '—' }}</td>
                                            <td class="px-4 py-3 text-gray-600 max-w-xs truncate">{{ $swap->reason ?: '—' }}</td>
                                            <td class="px-4 py-3 text-right whitespace-nowrap space-x-2">
                                                <button type="button" wire:click="approveSwap('{{ $swap->id }}')" class="rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white">Setujui</button>
                                                <button type="button" wire:click="rejectSwap('{{ $swap->id }}')" class="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700">Tolak</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Tidak ada pengajuan menunggu.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($recentSwaps->isNotEmpty())
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800 mb-2">Riwayat terbaru</h4>
                                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <tr>
                                                <th class="px-4 py-2.5">Karyawan</th>
                                                <th class="px-4 py-2.5">Tanggal</th>
                                                <th class="px-4 py-2.5">Ke shift</th>
                                                <th class="px-4 py-2.5">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            @foreach ($recentSwaps as $swap)
                                                <tr wire:key="swap-recent-{{ $swap->id }}">
                                                    <td class="px-4 py-3">{{ $swap->employee?->full_name ?? '—' }}</td>
                                                    <td class="px-4 py-3 tabular-nums">{{ $swap->work_date->translatedFormat('d M Y') }}</td>
                                                    <td class="px-4 py-3">{{ $swap->toSchedule?->name ?? '—' }}</td>
                                                    <td class="px-4 py-3">
                                                        <span @class([
                                                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                                            'bg-emerald-50 text-emerald-800' => $swap->status === \App\Models\ShiftSwapRequest::STATUS_APPROVED,
                                                            'bg-rose-50 text-rose-800' => $swap->status === \App\Models\ShiftSwapRequest::STATUS_REJECTED,
                                                            'bg-gray-100 text-gray-600' => $swap->status === \App\Models\ShiftSwapRequest::STATUS_CANCELLED,
                                                        ])>
                                                            {{ \App\Models\ShiftSwapRequest::statusLabel($swap->status) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Rule modal --}}
    @if ($showRuleModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col overflow-hidden">
                <div class="p-5 space-y-4 overflow-y-auto flex-1">
                <h3 class="text-lg font-semibold">{{ $editingScheduleId ? 'Edit Shift' : 'Buat Shift' }}</h3>
                @if ($errors->any())
                    <div class="rounded-md bg-red-50 border border-red-100 px-3 py-2 text-sm text-red-800">
                        <p class="font-medium">Periksa isian berikut:</p>
                        <ul class="mt-1 list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Nama Shift</label>
                        <input type="text" wire:model="rule_name" class="w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900">
                        @error('rule_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Jam masuk</label>
                            <input type="time" wire:model="rule_clock_in" class="w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Telat setelah</label>
                            <input type="time" wire:model="rule_late_after" class="w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Jam kerja default (jam)</label>
                            <input type="number" step="0.5" wire:model="rule_work_hours" class="w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-600 mb-1">Istirahat default (menit)</label>
                            <input type="number" wire:model="rule_break_minutes" class="w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900">
                        </div>
                    </div>
                    <div>
                        <p class="block text-sm text-gray-600 mb-2">Jam Istirahat</p>
                        <div class="flex flex-col gap-3">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" value="free" wire:model.live="rule_break_earliest_mode" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Bebas
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" value="fixed" wire:model.live="rule_break_earliest_mode" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Tentukan jam istirahat dimulai
                            </label>
                        </div>
                        @if ($rule_break_earliest_mode === 'fixed')
                            <input type="time" wire:model="rule_break_earliest_time" class="mt-2 w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900">
                            @error('rule_break_earliest_time') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        @endif
                    </div>
                    <div class="border-t border-gray-200 pt-3">
                        <p class="block text-sm text-gray-600 mb-2">Implementasi Shift</p>
                        <div class="flex flex-wrap gap-x-4 gap-y-2">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" value="default" wire:model.live="rule_implementation_mode" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Harian
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" value="weekdays" wire:model.live="rule_implementation_mode" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Hari Khusus
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" value="month_days" wire:model.live="rule_implementation_mode" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Setiap Tanggal
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="radio" value="specific_dates" wire:model.live="rule_implementation_mode" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Hanya Tanggal
                            </label>
                        </div>
                        @if ($rule_implementation_mode === 'weekdays')
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                @foreach ([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'] as $iso => $label)
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" wire:model="rule_implementation_weekdays" value="{{ $iso }}" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            @error('rule_implementation_weekdays') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        @elseif ($rule_implementation_mode === 'month_days')
                            <div class="mt-3">
                                <label class="block text-xs text-gray-500 mb-1">Angka tanggal (1–31), pisahkan koma</label>
                                <input type="text" wire:model="rule_implementation_month_days" placeholder="mis. 15 atau 1, 15, 31" class="w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900">
                                <p class="text-xs text-gray-500 mt-1">Tanggal 31 hanya berlaku di bulan yang memiliki tanggal tersebut.</p>
                                @error('rule_implementation_month_days') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        @elseif ($rule_implementation_mode === 'specific_dates')
                            <div class="mt-3 space-y-2">
                                <div class="flex items-center gap-2">
                                    <input type="date" wire:model="rule_implementation_specific_date_input" class="flex-1 rounded-md border border-gray-300 bg-white text-sm text-gray-900">
                                    <button type="button" wire:click="addRuleSpecificDate" class="shrink-0 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Tambah</button>
                                </div>
                                @error('rule_implementation_specific_dates') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                @if ($rule_implementation_specific_dates !== [])
                                    <ul class="space-y-1">
                                        @foreach ($rule_implementation_specific_dates as $specificDate)
                                            <li wire:key="rule-specific-date-{{ $specificDate }}" class="flex items-center justify-between rounded-md bg-gray-50 px-2 py-1 text-sm">
                                                <span>{{ \Illuminate\Support\Carbon::parse($specificDate, \App\Support\AppTimezone::display())->translatedFormat('l, d M Y') }}</span>
                                                <button type="button" wire:click="removeRuleSpecificDate('{{ $specificDate }}')" class="text-red-600 hover:text-red-800 text-xs">Hapus</button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500">Jam pulang dihitung otomatis. Override jam kerja/istirahat per tanggal di Jadwal Shift.</p>
                </div>
                </div>
                <div class="flex justify-end gap-2 p-5 border-t border-gray-200 bg-gray-50 shrink-0">
                    <button type="button" wire:click="closeRuleModal" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="button" wire:click="saveRule" wire:loading.attr="disabled" wire:target="saveRule,confirmRuleSave" class="inline-flex items-center rounded-md bg-gray-800 border border-transparent px-4 py-2 text-xs font-semibold text-white uppercase tracking-widest hover:bg-gray-700 transition disabled:opacity-50">
                        <span wire:loading.remove wire:target="saveRule,confirmRuleSave">Simpan</span>
                        <span wire:loading wire:target="saveRule,confirmRuleSave">Menyimpan…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showRuleImpactConfirm)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-5 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">Konfirmasi perubahan implementasi</h3>
                <p class="text-sm text-gray-600">
                    {{ $ruleImpactPreview['removed_entries'] ?? 0 }} penempatan kalender akan dihapus karena tidak lagi sesuai aturan implementasi shift.
                </p>
                @if (! empty($ruleImpactPreview['unscheduled_employees']))
                    <div class="rounded-md bg-amber-50 border border-amber-100 px-3 py-2 text-sm text-amber-900">
                        <p class="font-medium">Karyawan tanpa jadwal lain:</p>
                        <p class="mt-1">{{ implode(', ', $ruleImpactPreview['unscheduled_employees']) }}</p>
                    </div>
                @endif
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancelRuleImpactConfirm" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="button" wire:click="confirmRuleSave" class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-xs font-semibold text-white uppercase tracking-widest hover:bg-red-700">Lanjutkan</button>
                </div>
            </div>
        </div>
    @endif

    <div
        x-show="showTemplateSettings"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40"
        style="display: none;"
    >
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-5 py-3.5 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900">Pengaturan Template</h3>
                <button type="button" @click="closeTemplateQuick()" class="text-gray-400 hover:text-gray-600" aria-label="Tutup">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Pilih template</label>
                    <div class="flex items-center gap-2">
                        <select wire:model="selectedTemplateId" wire:key="template-select-{{ $activeTemplateId }}" class="flex-1 min-w-0 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-900">
                            <option value="">— pilih —</option>
                            @foreach ($templates as $tpl)
                                <option value="{{ $tpl->id }}">{{ $tpl->name }}{{ $tpl->is_default ? ' (pola berulang)' : '' }}</option>
                            @endforeach
                        </select>
                        <button type="button"
                            wire:click="loadTemplate"
                            wire:loading.attr="disabled"
                            wire:target="loadTemplate"
                            @disabled($repeatingEditLocked)
                            class="shrink-0 inline-flex items-center rounded-md bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <span wire:loading.remove wire:target="loadTemplate">Load & Aktifkan</span>
                            <span wire:loading wire:target="loadTemplate">...</span>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1.5">Pilih template lalu klik Load & Aktifkan untuk menerapkan ke {{ $calendarViewMode === 'month' ? 'bulan ini' : 'blok ini' }} dan menandai sebagai template aktif.</p>
                </div>

                <div class="border-t border-gray-200 pt-4">
                    <p class="text-xs font-medium text-gray-500 mb-2">Pola Berulang</p>
                    @if ($selectedTemplateRepeatingActive)
                        <button type="button"
                            wire:click="deactivateRepeatingPattern"
                            wire:confirm="Nonaktifkan pola berulang? Semua jadwal di blok setelah periode acuan akan dikosongkan agar dapat diatur mandiri. Blok acuan tidak berubah. Libur event dan tukar shift per tanggal tetap ada."
                            wire:loading.attr="disabled"
                            wire:target="deactivateRepeatingPattern"
                            title="Hentikan pengulangan dan kosongkan blok mendatang"
                            class="inline-flex items-center rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <span wire:loading.remove wire:target="deactivateRepeatingPattern">Nonaktifkan Pola Berulang</span>
                            <span wire:loading wire:target="deactivateRepeatingPattern">...</span>
                        </button>
                    @else
                        <button type="button"
                            wire:click="activateRepeatingPattern"
                            wire:confirm="Aktifkan pola berulang? Jadwal di semua blok setelah periode acuan ini akan diganti mengikuti pola 4 minggu saat ini. Penempatan manual di blok mendatang akan hilang. Libur event dan tukar shift per tanggal tetap di tanggal asalnya."
                            wire:loading.attr="disabled"
                            wire:target="activateRepeatingPattern"
                            @disabled($selectedTemplateId === '' && $activeTemplateId === '')
                            title="{{ $calendarViewMode === 'month' ? 'Beralih ke tampilan Blok 4 Minggu untuk mengaktifkan pola berulang' : 'Terapkan pola 4 minggu ini ke blok-blok berikutnya (mengganti jadwal manual di sana)' }}"
                            class="inline-flex items-center rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <span wire:loading.remove wire:target="activateRepeatingPattern">Aktifkan Pola Berulang</span>
                            <span wire:loading wire:target="activateRepeatingPattern">...</span>
                        </button>
                        @if ($calendarViewMode === 'month')
                            <p class="text-xs text-gray-500 mt-1.5">Aktivasi hanya tersedia di tampilan Blok 4 Minggu.</p>
                        @endif
                    @endif
                </div>

                <div class="border-t border-gray-200 pt-4">
                    <p class="text-xs font-medium text-gray-500 mb-2">Aksi template terpilih</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button"
                            wire:click="updateSelectedTemplate"
                            wire:loading.attr="disabled"
                            wire:target="updateSelectedTemplate"
                            @disabled($repeatingEditLocked)
                            class="inline-flex items-center rounded-md bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <span wire:loading.remove wire:target="updateSelectedTemplate">Simpan Perubahan</span>
                            <span wire:loading wire:target="updateSelectedTemplate">...</span>
                        </button>
                        <button type="button"
                            wire:click="openSaveNewTemplateModal"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Simpan Template Baru
                        </button>
                        <button type="button"
                            wire:click="deleteTemplate"
                            wire:confirm="Hapus template yang dipilih?"
                            wire:loading.attr="disabled"
                            wire:target="deleteTemplate"
                            class="inline-flex items-center rounded-md border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50">
                            <span wire:loading.remove wire:target="deleteTemplate">Hapus Template</span>
                            <span wire:loading wire:target="deleteTemplate">...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($showSaveNewTemplateModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/40">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-5 py-3.5 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900">Simpan Template Baru</h3>
                    <button type="button" wire:click="closeSaveNewTemplateModalPublic" class="text-gray-400 hover:text-gray-600" aria-label="Tutup">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Nama template</label>
                        <input type="text"
                            wire:model.defer="templateName"
                            placeholder="Nama template"
                            class="w-full rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-900 placeholder:text-gray-400">
                        @error('templateName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button"
                            wire:click="closeSaveNewTemplateModalPublic"
                            class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="button"
                            wire:click="saveTemplate"
                            wire:loading.attr="disabled"
                            wire:target="saveTemplate"
                            @disabled($repeatingEditLocked)
                            class="inline-flex items-center rounded-md bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <span wire:loading.remove wire:target="saveTemplate">Simpan</span>
                            <span wire:loading wire:target="saveTemplate">...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Group modal --}}
    @if ($showGroupModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-5 space-y-4">
                <h3 class="text-lg font-semibold">{{ $editingGroupId ? 'Edit Group' : 'Buat Group' }}</h3>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Nama</label>
                    <input type="text" wire:model="group_name" class="w-full rounded-md border border-gray-300 bg-white text-sm text-gray-900">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Warna</label>
                    <input type="color" wire:model="group_color" class="h-10 w-20 rounded border border-gray-300">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeGroupModal" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Batal</button>
                    <button type="button" wire:click="saveGroup" class="inline-flex items-center rounded-md bg-gray-800 border border-transparent px-4 py-2 text-xs font-semibold text-white uppercase tracking-widest hover:bg-gray-700 transition">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Day menu (client-side — data sudah ada di payload sel) --}}
    <div
        x-show="showDayMenu"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none;"
    >
        <div class="fixed inset-0 bg-gray-500/75 transition-opacity" @click="closeDayMenuQuick()"></div>

        <div class="flex min-h-full items-center justify-center p-4 sm:p-6 pointer-events-none">
            <div class="relative w-full max-w-md bg-white rounded-xl shadow-2xl flex flex-col overflow-hidden pointer-events-auto">
                <div class="flex items-center justify-between px-6 py-3.5 border-b border-gray-200 bg-gray-50 shrink-0">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Pengaturan hari</h2>
                        <p class="text-sm text-gray-500 mt-0.5" x-text="dayMenu.label"></p>
                    </div>
                    <button type="button" @click="closeDayMenuQuick()" class="text-gray-400 hover:text-gray-600 transition" aria-label="Tutup">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-5">
                    <p
                        x-show="selectedDates.length > 1"
                        x-cloak
                        class="text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-md px-3 py-2"
                    >
                        Berlaku untuk <span x-text="selectedDates.length"></span> tanggal terpilih.
                    </p>

                    <template x-if="dayMenu.isHoliday">
                        <div class="rounded-md bg-red-50 border border-red-100 px-4 py-3">
                            <p class="text-sm font-medium text-red-800" x-text="dayMenu.holidayKind === 'event' ? 'Libur event' : 'Libur rutin'"></p>
                            <p class="text-sm text-red-700 mt-1">Hari ini ditandai libur. Lepas libur untuk mengembalikan jadwal sif.</p>
                        </div>
                    </template>

                    <template x-if="!dayMenu.isHoliday">
                        <div>
                            <p class="block text-sm font-medium text-gray-700 mb-2">Jadikan libur</p>
                            <div class="flex flex-wrap gap-3">
                                <button type="button" @click="runDayMenuAction(() => $wire.setLiburRutin(dayMenu.date))" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                                    Libur rutin
                                </button>
                                <button type="button" @click="runDayMenuAction(() => $wire.setLiburEvent(dayMenu.date))" class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 transition">
                                    Libur event
                                </button>
                            </div>

                            <div class="space-y-4 mt-5">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Jam kerja (menit)</label>
                                    <input type="number" x-model="dayMenu.workMinutes" placeholder="default" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm placeholder:text-gray-400">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Durasi istirahat (menit)</label>
                                    <input type="number" x-model="dayMenu.breakMinutes" placeholder="default" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm placeholder:text-gray-400">
                                </div>
                                <div>
                                    <p class="block text-sm font-medium text-gray-700 mb-2">Jam Istirahat</p>
                                    <div class="flex flex-col gap-3">
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                            <input type="radio" value="free" x-model="dayMenu.breakEarliestMode" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            Bebas
                                        </label>
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                            <input type="radio" value="fixed" x-model="dayMenu.breakEarliestMode" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            Tentukan jam istirahat dimulai
                                        </label>
                                    </div>
                                    <div x-show="dayMenu.breakEarliestMode === 'fixed'" x-cloak class="mt-2">
                                        <input type="time" x-model="dayMenu.breakEarliestTime" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Bebas mengikuti default shift. Scan istirahat sebelum jam minimum ditolak di mesin.</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Terapkan ke :</label>
                                    <select x-model="dayMenu.applyScope" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                        <option value="date" x-text="dayMenu.dateApplyLabel"></option>
                                        <option value="weekday" x-text="dayMenu.weekdayApplyLabel"></option>
                                    </select>
                                </div>
                                <p class="text-xs text-gray-500">Kosongkan field lalu simpan untuk mengembalikan ke default shift.</p>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3 px-6 py-3.5 border-t border-gray-200 bg-gray-50 shrink-0">
                    <button type="button" @click="closeDayMenuQuick()" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition">
                        Tutup
                    </button>
                    <button
                        type="button"
                        x-show="!dayMenu.isHoliday && dayMenu.hasDurationOverride"
                        x-cloak
                        @click="runDayMenuAction(() => $wire.resetDayDurations(dayMenu.date, dayMenu.applyScope === 'weekday'))"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition"
                    >
                        Kembalikan default
                    </button>
                    <button
                        type="button"
                        x-show="dayMenu.isHoliday"
                        x-cloak
                        @click="runDayMenuAction(() => $wire.clearLibur(dayMenu.date))"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition"
                    >
                        Lepas libur
                    </button>
                    <button
                        type="button"
                        x-show="!dayMenu.isHoliday"
                        x-cloak
                        @click="runDayMenuAction(() => $wire.saveDayDurations(dayMenu.workMinutes, dayMenu.breakMinutes, dayMenu.date, dayMenu.applyScope === 'weekday', dayMenu.breakEarliestMode, dayMenu.breakEarliestTime))"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition"
                    >
                        SIMPAN
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Member panel --}}
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
                            $canEditMember = $this->canManageMembersOnDate($memberPanelDate);
                        @endphp
                        <li class="py-3 space-y-3.5" wire:key="member-panel-{{ $m['id'] }}">
                            <div class="flex items-start gap-2 min-w-0">
                                <p class="flex-1 min-w-0 text-sm font-medium text-gray-900 truncate" title="{{ $m['full_name'] }}">
                                    {{ $m['full_name'] }}
                                </p>
                                @if ($isLibur)
                                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 whitespace-nowrap">Libur Rutin</span>
                                @elseif ($ov)
                                    <span class="shrink-0 inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 max-w-[45%]" title="Tukar shift → {{ $ov->schedule?->name }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="truncate">{{ $ov->schedule?->name }}</span>
                                    </span>
                                @endif
                            </div>
                            @if ($canEditMember)
                                <div class="flex flex-wrap gap-1.5">
                                    @if ($this->canManageLiburOnDate($memberPanelDate))
                                        <button type="button" wire:click="toggleLiburKaryawan('{{ $m['id'] }}')"
                                            @class([
                                                'rounded-md border px-2.5 py-1 text-xs font-medium whitespace-nowrap',
                                                'border-gray-300 text-gray-700 hover:bg-gray-50' => ! $isLibur,
                                                'border-slate-300 bg-slate-100 text-slate-700' => $isLibur,
                                            ])>{{ $isLibur ? 'Lepas libur rutin' : 'Libur Rutin' }}</button>
                                    @endif
                                    @foreach ($schedules->where('is_enabled', true) as $sched)
                                        <button type="button" wire:click="moveEmployeeShift('{{ $m['id'] }}', '{{ $sched->id }}')"
                                            @class([
                                                'rounded-md px-2.5 py-1 text-xs font-medium whitespace-nowrap',
                                                'bg-indigo-600 text-white' => $ov && (string) $ov->work_schedule_id === (string) $sched->id,
                                                'bg-indigo-50 text-indigo-800 hover:bg-indigo-100' => ! $ov || (string) $ov->work_schedule_id !== (string) $sched->id,
                                            ])>→ {{ $sched->name }}</button>
                                    @endforeach
                                    @if ($ov)
                                        <button type="button" wire:click="clearEmployeeShiftOverride('{{ $m['id'] }}')"
                                            class="rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100 whitespace-nowrap">
                                            Batalkan tukar shift
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="py-8 text-center text-sm text-gray-500">Tidak ada anggota aktif di group ini.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endif
</div>
</div>
