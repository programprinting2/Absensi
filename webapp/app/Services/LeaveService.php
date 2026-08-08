<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeLeaveGrant;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Support\AppTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LeaveService
{
    public function annualEntitlement(): int
    {
        return max(0, (int) (PayrollSetting::active()->annual_leave_days ?? 12));
    }

    public function cashDayDivisor(): int
    {
        return max(1, (int) (PayrollSetting::active()->leave_cash_day_divisor ?? 25));
    }

    /**
     * Hitung hari cuti (Senin–Sabtu). Minggu tidak dihitung.
     */
    public function countLeaveDays(Carbon|string $start, Carbon|string $end): int
    {
        return (int) array_sum($this->daysByYear($start, $end));
    }

    /**
     * Pecah hari cuti per tahun kalender (Senin–Sabtu).
     *
     * @return array<int, int> year => days
     */
    public function daysByYear(Carbon|string $start, Carbon|string $end): array
    {
        $tz = AppTimezone::display();
        $from = $start instanceof Carbon
            ? $start->copy()->timezone($tz)->startOfDay()
            : Carbon::parse($start, $tz)->startOfDay();
        $to = $end instanceof Carbon
            ? $end->copy()->timezone($tz)->startOfDay()
            : Carbon::parse($end, $tz)->startOfDay();

        if ($to->lessThan($from)) {
            return [];
        }

        $byYear = [];
        for ($d = $from->copy(); $d->lessThanOrEqualTo($to); $d->addDay()) {
            if ($d->isSunday()) {
                continue;
            }
            $year = (int) $d->year;
            $byYear[$year] = ($byYear[$year] ?? 0) + 1;
        }

        return $byYear;
    }

    public function ensureBalance(Employee|string $employee, int $year): EmployeeLeaveBalance
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return EmployeeLeaveBalance::query()->firstOrCreate(
            ['employee_id' => $employeeId, 'year' => $year],
            [
                'entitlement_days' => 0,
                'used_days' => 0,
                'expired_days' => 0,
                'cashed_days' => 0,
                'cash_amount' => 0,
                'status' => EmployeeLeaveBalance::STATUS_OPEN,
                'created_at' => now(),
            ],
        );
    }

    /**
     * Tambah jatah cuti + simpan history grant.
     *
     * @return array{added: int, grant: EmployeeLeaveGrant}
     */
    public function addEntitlementFromPeriod(
        Employee $employee,
        string $startDate,
        string $endDate,
        ?int $daysOverride = null,
        ?string $note = null,
        ?User $actor = null,
    ): array {
        $actor ??= Auth::user();
        $autoDays = $this->countLeaveDays($startDate, $endDate);
        $days = $daysOverride !== null ? max(0, min(366, $daysOverride)) : $autoDays;

        if ($days < 1) {
            throw new RuntimeException('Jumlah hari jatah minimal 1.');
        }

        $year = (int) Carbon::parse($startDate, AppTimezone::display())->year;

        return DB::transaction(function () use ($employee, $startDate, $endDate, $days, $year, $note, $actor) {
            $balance = $this->ensureBalance($employee, $year);
            if ($balance->status === EmployeeLeaveBalance::STATUS_CLOSED) {
                throw new RuntimeException("Jatah cuti tahun {$year} sudah ditutup; tidak bisa ditambah.");
            }

            $next = min(366, $balance->entitlement_days + $days);
            $actualAdd = $next - $balance->entitlement_days;
            if ($actualAdd < 1) {
                throw new RuntimeException("Jatah cuti tahun {$year} sudah mencapai batas maksimum.");
            }

            $grant = EmployeeLeaveGrant::query()->create([
                'employee_id' => $employee->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $actualAdd,
                'year' => $year,
                'notes' => $note,
                'created_by' => $actor?->id,
                'created_at' => now(),
            ]);

            $balance->update([
                'entitlement_days' => $next,
            ]);

            return [
                'added' => $actualAdd,
                'grant' => $grant->fresh(['employee', 'creator']),
            ];
        });
    }

    /**
     * Hapus history grant dan kurangi jatah (jika masih memungkinkan).
     */
    public function deleteGrant(EmployeeLeaveGrant $grant): void
    {
        DB::transaction(function () use ($grant) {
            $balance = $this->ensureBalance($grant->employee_id, $grant->year);
            if ($balance->status === EmployeeLeaveBalance::STATUS_CLOSED) {
                throw new RuntimeException("Jatah cuti tahun {$grant->year} sudah ditutup; history tidak bisa dihapus.");
            }

            $next = $balance->entitlement_days - $grant->days;
            $committed = $balance->used_days + $balance->expired_days + $balance->cashed_days;
            if ($next < $committed) {
                throw new RuntimeException(
                    "Tidak bisa menghapus: jatah tersisa akan lebih kecil dari yang sudah terpakai/hangus/diuangkan ({$committed} hari)."
                );
            }

            $balance->update(['entitlement_days' => max(0, $next)]);
            $grant->delete();
        });
    }

    /**
     * @return array{0: string, 1: string} [rangeStart, rangeEnd] Y-m-d
     */
    public function historyDateRange(string $period, int $year, int $month): array
    {
        $tz = AppTimezone::display();

        if ($period === 'year') {
            return [sprintf('%d-01-01', $year), sprintf('%d-12-31', $year)];
        }

        $start = Carbon::create($year, max(1, min(12, $month)), 1, 0, 0, 0, $tz);

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function grantHistory(
        ?int $year = null,
        ?string $employeeId = null,
        int $limit = 100,
        ?string $rangeStart = null,
        ?string $rangeEnd = null,
    ): array {
        $query = EmployeeLeaveGrant::query()
            ->with(['employee:id,full_name,employee_code', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('start_date');

        if ($year && ! $rangeStart) {
            $query->where('year', $year);
        }

        if ($rangeStart && $rangeEnd) {
            $query->whereDate('start_date', '<=', $rangeEnd)
                ->whereDate('end_date', '>=', $rangeStart);
        }

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        return $query->limit($limit)->get()->map(fn (EmployeeLeaveGrant $grant) => [
            'id' => $grant->id,
            'kind' => 'grant',
            'kind_label' => 'Penambahan jatah',
            'direction' => 'in',
            'employee' => [
                'id' => $grant->employee?->id,
                'full_name' => $grant->employee?->full_name,
                'employee_code' => $grant->employee?->employee_code,
            ],
            'start_date' => $grant->start_date?->toDateString(),
            'end_date' => $grant->end_date?->toDateString(),
            'start_date_label' => $grant->start_date?->locale('id')->translatedFormat('j M Y'),
            'end_date_label' => $grant->end_date?->locale('id')->translatedFormat('j M Y'),
            'days' => $grant->days,
            'year' => $grant->year,
            'notes' => $grant->notes,
            'created_by' => $grant->creator?->name,
            'created_at' => $grant->created_at?->timezone(AppTimezone::display())->format('Y-m-d H:i'),
            'created_at_label' => $grant->created_at?->timezone(AppTimezone::display())->locale('id')->translatedFormat('j M Y H:i'),
            'sort_at' => $grant->created_at?->timestamp ?? 0,
        ])->all();
    }

    /**
     * History penambahan jatah + pengambilan cuti tahunan (yang mengurangi jatah).
     *
     * @return list<array<string, mixed>>
     */
    public function quotaHistory(
        ?string $employeeId = null,
        string $period = 'month',
        ?int $year = null,
        ?int $month = null,
        int $limit = 200,
    ): array {
        $year ??= (int) AppTimezone::nowDisplay()->year;
        $month ??= (int) AppTimezone::nowDisplay()->month;
        [$rangeStart, $rangeEnd] = $this->historyDateRange($period, $year, $month);

        $grants = $this->grantHistory(null, $employeeId, $limit, $rangeStart, $rangeEnd);

        $usageQuery = EmployeeLeave::query()
            ->with(['employee:id,full_name,employee_code', 'requester:id,name', 'reviewer:id,name'])
            ->where('leave_type', EmployeeLeave::TYPE_TAHUNAN)
            ->where('status', EmployeeLeave::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $rangeEnd)
            ->whereDate('end_date', '>=', $rangeStart)
            ->orderByDesc('reviewed_at')
            ->orderByDesc('created_at');

        if ($employeeId) {
            $usageQuery->where('employee_id', $employeeId);
        }

        $usages = $usageQuery->limit($limit)->get()->map(function (EmployeeLeave $leave) {
            $when = $leave->reviewed_at ?? $leave->created_at;

            return [
                'id' => $leave->id,
                'kind' => 'usage',
                'kind_label' => 'Pengambilan cuti',
                'direction' => 'out',
                'employee' => [
                    'id' => $leave->employee?->id,
                    'full_name' => $leave->employee?->full_name,
                    'employee_code' => $leave->employee?->employee_code,
                ],
                'start_date' => $leave->start_date?->toDateString(),
                'end_date' => $leave->end_date?->toDateString(),
                'start_date_label' => $leave->start_date?->locale('id')->translatedFormat('j M Y'),
                'end_date_label' => $leave->end_date?->locale('id')->translatedFormat('j M Y'),
                'days' => $leave->days_count,
                'year' => (int) ($leave->start_date?->year ?? 0),
                'notes' => $leave->reason ?: $leave->typeLabel(),
                'created_by' => $leave->reviewer?->name ?: $leave->requester?->name,
                'created_at' => $when?->timezone(AppTimezone::display())->format('Y-m-d H:i'),
                'created_at_label' => $when?->timezone(AppTimezone::display())->locale('id')->translatedFormat('j M Y H:i'),
                'sort_at' => $when?->timestamp ?? 0,
            ];
        })->all();

        return collect($grants)
            ->merge($usages)
            ->sortByDesc('sort_at')
            ->take($limit)
            ->values()
            ->all();
    }

    public function updateAnnualDefault(int $days, ?int $cashDivisor = null): void
    {
        $days = max(0, min(366, $days));
        $data = ['annual_leave_days' => $days];

        if ($cashDivisor !== null) {
            $data['leave_cash_day_divisor'] = max(1, min(31, $cashDivisor));
        }

        PayrollSetting::active()->update($data);
    }

    /**
     * @return array{year: int, entitlement: int, used: int, expired: int, cashed: int, remaining: int, pending: int, available: int, status: string, cash_amount: float}
     */
    public function quotaSummary(Employee|string $employee, ?int $year = null): array
    {
        $year ??= (int) AppTimezone::nowDisplay()->year;
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $balance = $this->ensureBalance($employee, $year);
        $pending = $this->pendingTahunanDays($employeeId, $year);
        $remaining = $balance->remainingDays();

        return [
            'year' => $year,
            'entitlement' => $balance->entitlement_days,
            'used' => $balance->used_days,
            'expired' => $balance->expired_days,
            'cashed' => $balance->cashed_days,
            'remaining' => $remaining,
            'pending' => $pending,
            'available' => max(0, $remaining - $pending),
            'status' => $balance->status,
            'cash_amount' => (float) $balance->cash_amount,
        ];
    }

    public function create(
        Employee $employee,
        string $leaveType,
        string $startDate,
        string $endDate,
        ?string $reason = null,
        string $status = EmployeeLeave::STATUS_PENDING,
        ?User $actor = null,
        ?string $reviewNotes = null,
    ): EmployeeLeave {
        $actor ??= Auth::user();
        $daysByYear = $this->daysByYear($startDate, $endDate);
        $days = (int) array_sum($daysByYear);

        if ($days < 1) {
            throw new RuntimeException('Rentang tanggal cuti tidak valid (minimal 1 hari kerja).');
        }

        if (! array_key_exists($leaveType, EmployeeLeave::typeOptions())) {
            throw new RuntimeException('Jenis cuti tidak dikenal.');
        }

        $this->assertNoOverlap($employee->id, $startDate, $endDate);

        // Cuti tahunan selalu cek jatah (baik pending maupun approved).
        if ($leaveType === EmployeeLeave::TYPE_TAHUNAN) {
            $this->assertQuotaAvailable($employee, $daysByYear);
        }

        $approved = $status === EmployeeLeave::STATUS_APPROVED;

        return DB::transaction(function () use ($employee, $leaveType, $startDate, $endDate, $reason, $status, $actor, $reviewNotes, $days, $daysByYear, $approved) {
            $leave = EmployeeLeave::query()->create([
                'employee_id' => $employee->id,
                'leave_type' => $leaveType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_count' => $days,
                'reason' => $reason,
                'status' => $status,
                'requested_by' => $actor?->id,
                'reviewed_by' => $approved ? $actor?->id : null,
                'reviewed_at' => $approved ? now() : null,
                'review_notes' => $approved ? $reviewNotes : null,
                'created_at' => now(),
            ]);

            if ($approved && $leaveType === EmployeeLeave::TYPE_TAHUNAN) {
                $this->applyQuotaUsage($employee, $daysByYear, +1);
            }

            return $leave->fresh(['employee']);
        });
    }

    public function approve(EmployeeLeave $leave, ?User $actor = null, ?string $notes = null): EmployeeLeave
    {
        if ($leave->status !== EmployeeLeave::STATUS_PENDING) {
            throw new RuntimeException('Hanya pengajuan yang menunggu yang bisa disetujui.');
        }

        $this->assertNoOverlap(
            $leave->employee_id,
            $leave->start_date->toDateString(),
            $leave->end_date->toDateString(),
            $leave->id,
        );

        $daysByYear = $this->daysByYear($leave->start_date, $leave->end_date);

        return DB::transaction(function () use ($leave, $actor, $notes, $daysByYear) {
            if ($leave->leave_type === EmployeeLeave::TYPE_TAHUNAN) {
                $this->assertQuotaAvailable($leave->employee, $daysByYear, $leave->id);
                $this->applyQuotaUsage($leave->employee, $daysByYear, +1);
            }

            $leave->update([
                'status' => EmployeeLeave::STATUS_APPROVED,
                'reviewed_by' => ($actor ?? Auth::user())?->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            return $leave->fresh(['employee']);
        });
    }

    public function reject(EmployeeLeave $leave, ?User $actor = null, ?string $notes = null): EmployeeLeave
    {
        if ($leave->status !== EmployeeLeave::STATUS_PENDING) {
            throw new RuntimeException('Hanya pengajuan yang menunggu yang bisa ditolak.');
        }

        $leave->update([
            'status' => EmployeeLeave::STATUS_REJECTED,
            'reviewed_by' => ($actor ?? Auth::user())?->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        return $leave->fresh(['employee']);
    }

    public function cancel(EmployeeLeave $leave): EmployeeLeave
    {
        if (! in_array($leave->status, [EmployeeLeave::STATUS_PENDING, EmployeeLeave::STATUS_APPROVED], true)) {
            throw new RuntimeException('Cuti ini tidak bisa dibatalkan.');
        }

        return DB::transaction(function () use ($leave) {
            $wasApproved = $leave->status === EmployeeLeave::STATUS_APPROVED;

            $leave->update([
                'status' => EmployeeLeave::STATUS_CANCELLED,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            if ($wasApproved && $leave->leave_type === EmployeeLeave::TYPE_TAHUNAN) {
                $this->applyQuotaUsage(
                    $leave->employee,
                    $this->daysByYear($leave->start_date, $leave->end_date),
                    -1,
                );
            }

            return $leave->fresh(['employee']);
        });
    }

    /**
     * Hanguskan sisa jatah cuti tahun tertentu.
     */
    public function expireRemaining(Employee $employee, int $year, ?User $actor = null): EmployeeLeaveBalance
    {
        return DB::transaction(function () use ($employee, $year, $actor) {
            $balance = $this->ensureBalance($employee, $year);
            if ($balance->status === EmployeeLeaveBalance::STATUS_CLOSED) {
                throw new RuntimeException("Jatah cuti {$year} sudah ditutup.");
            }

            $remaining = $balance->remainingDays();
            if ($remaining < 1) {
                throw new RuntimeException("Tidak ada sisa cuti {$year} yang bisa digantungkan.");
            }

            $balance->update([
                'expired_days' => $balance->expired_days + $remaining,
                'status' => EmployeeLeaveBalance::STATUS_CLOSED,
                'notes' => trim(($balance->notes ? $balance->notes.' | ' : '')."Hangus {$remaining} hari"),
                'closed_by' => ($actor ?? Auth::user())?->id,
                'closed_at' => now(),
            ]);

            return $balance->fresh();
        });
    }

    /**
     * Uangkan sisa jatah cuti tahun tertentu.
     */
    public function cashOutRemaining(Employee $employee, int $year, ?User $actor = null): EmployeeLeaveBalance
    {
        return DB::transaction(function () use ($employee, $year, $actor) {
            $balance = $this->ensureBalance($employee, $year);
            if ($balance->status === EmployeeLeaveBalance::STATUS_CLOSED) {
                throw new RuntimeException("Jatah cuti {$year} sudah ditutup.");
            }

            $remaining = $balance->remainingDays();
            if ($remaining < 1) {
                throw new RuntimeException("Tidak ada sisa cuti {$year} yang bisa diuangkan.");
            }

            $rate = $this->dailyCashRate($employee);
            $amount = round($rate * $remaining, 2);

            $balance->update([
                'cashed_days' => $balance->cashed_days + $remaining,
                'cash_amount' => (float) $balance->cash_amount + $amount,
                'status' => EmployeeLeaveBalance::STATUS_CLOSED,
                'notes' => trim(($balance->notes ? $balance->notes.' | ' : '')."Uangkan {$remaining} hari @ Rp ".number_format($rate, 0, ',', '.')),
                'closed_by' => ($actor ?? Auth::user())?->id,
                'closed_at' => now(),
            ]);

            return $balance->fresh();
        });
    }

    public function dailyCashRate(Employee $employee): float
    {
        $salary = (float) ($employee->activeSalary?->base_salary
            ?? $employee->salaries()->where('is_active', true)->value('base_salary')
            ?? 0);

        if ($salary <= 0) {
            throw new RuntimeException('Gaji pokok karyawan belum diisi; tidak bisa menghitung uang cuti.');
        }

        return round($salary / $this->cashDayDivisor(), 2);
    }

    /**
     * Cuti karyawan lain yang rentang tanggalnya berpotongan.
     *
     * @return list<array{id: string, employee_name: string, employee_code: ?string, leave_type_label: string, start_date: string, end_date: string, status: string, status_label: string}>
     */
    public function overlappingPeers(string $startDate, string $endDate, ?string $excludeEmployeeId = null, ?string $excludeLeaveId = null): array
    {
        $peers = EmployeeLeave::query()
            ->with(['employee:id,full_name,employee_code'])
            ->whereIn('status', [EmployeeLeave::STATUS_PENDING, EmployeeLeave::STATUS_APPROVED])
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->when($excludeEmployeeId, fn ($q) => $q->where('employee_id', '!=', $excludeEmployeeId))
            ->when($excludeLeaveId, fn ($q) => $q->where('id', '!=', $excludeLeaveId))
            ->orderBy('start_date')
            ->get();

        return $peers->map(fn (EmployeeLeave $leave) => [
            'id' => $leave->id,
            'employee_name' => $leave->employee?->full_name ?? '—',
            'employee_code' => $leave->employee?->employee_code,
            'leave_type_label' => $leave->typeLabel(),
            'start_date' => $leave->start_date->toDateString(),
            'end_date' => $leave->end_date->toDateString(),
            'start_date_label' => $leave->start_date->locale('id')->translatedFormat('j M Y'),
            'end_date_label' => $leave->end_date->locale('id')->translatedFormat('j M Y'),
            'status' => $leave->status,
            'status_label' => $leave->statusLabel(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function balanceRows(?int $year = null): array
    {
        $year ??= (int) AppTimezone::nowDisplay()->year;

        return Employee::query()
            ->where('is_active', true)
            ->with(['activeSalary'])
            ->orderBy('full_name')
            ->get()
            ->map(function (Employee $employee) use ($year) {
                $balance = $this->ensureBalance($employee, $year);

                $rate = 0.0;
                try {
                    $rate = $this->dailyCashRate($employee);
                } catch (RuntimeException) {
                    $rate = 0.0;
                }

                return [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->full_name,
                    'employee_code' => $employee->employee_code,
                    'year' => $year,
                    'entitlement' => $balance->entitlement_days,
                    'used' => $balance->used_days,
                    'expired' => $balance->expired_days,
                    'cashed' => $balance->cashed_days,
                    'remaining' => $balance->remainingDays(),
                    'status' => $balance->status,
                    'cash_amount' => (float) $balance->cash_amount,
                    'cash_rate' => $rate,
                    'cash_preview' => round($rate * $balance->remainingDays(), 2),
                    'notes' => $balance->notes,
                ];
            })
            ->all();
    }

    /**
     * Peta tanggal cuti disetujui per karyawan dalam rentang.
     *
     * @param  Collection<int, string>|array<int, string>  $employeeIds
     * @return array<string, array<string, EmployeeLeave>>
     */
    public function approvedLeavesByEmployeeDate(Collection|array $employeeIds, Carbon|string $rangeStart, Carbon|string $rangeEnd): array
    {
        $ids = collect($employeeIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $tz = AppTimezone::display();
        $start = $rangeStart instanceof Carbon
            ? $rangeStart->copy()->timezone($tz)->toDateString()
            : Carbon::parse($rangeStart, $tz)->toDateString();
        $end = $rangeEnd instanceof Carbon
            ? $rangeEnd->copy()->timezone($tz)->toDateString()
            : Carbon::parse($rangeEnd, $tz)->toDateString();

        $leaves = EmployeeLeave::query()
            ->whereIn('employee_id', $ids)
            ->where('status', EmployeeLeave::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get();

        $map = [];
        foreach ($leaves as $leave) {
            $from = $leave->start_date->copy()->max(Carbon::parse($start));
            $to = $leave->end_date->copy()->min(Carbon::parse($end));

            for ($d = $from->copy()->startOfDay(); $d->lessThanOrEqualTo($to); $d->addDay()) {
                if ($d->isSunday()) {
                    continue;
                }
                $map[$leave->employee_id][$d->toDateString()] = $leave;
            }
        }

        return $map;
    }

    public function indexPayload(?string $status = null, ?string $employeeId = null): array
    {
        $query = EmployeeLeave::query()
            ->with(['employee:id,full_name,employee_code', 'requester:id,name', 'reviewer:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('start_date');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $leaves = $query->limit(300)->get();

        // Prefetch overlaps for listed leaves (same date windows).
        $items = $leaves->map(function (EmployeeLeave $leave) use ($leaves) {
            $peers = $leaves
                ->filter(function (EmployeeLeave $other) use ($leave) {
                    if ($other->id === $leave->id) {
                        return false;
                    }
                    if ($other->employee_id === $leave->employee_id) {
                        return false;
                    }
                    if (! in_array($other->status, [EmployeeLeave::STATUS_PENDING, EmployeeLeave::STATUS_APPROVED], true)) {
                        return false;
                    }

                    return $other->start_date->toDateString() <= $leave->end_date->toDateString()
                        && $other->end_date->toDateString() >= $leave->start_date->toDateString();
                })
                ->map(fn (EmployeeLeave $other) => [
                    'employee_name' => $other->employee?->full_name ?? '—',
                    'employee_code' => $other->employee?->employee_code,
                    'leave_type_label' => $other->typeLabel(),
                    'start_date_label' => $other->start_date->locale('id')->translatedFormat('j M Y'),
                    'end_date_label' => $other->end_date->locale('id')->translatedFormat('j M Y'),
                    'status_label' => $other->statusLabel(),
                ])
                ->values()
                ->all();

            // Juga cari di DB jika filter status menyembunyikan peer.
            if ($peers === []) {
                $peers = $this->overlappingPeers(
                    $leave->start_date->toDateString(),
                    $leave->end_date->toDateString(),
                    $leave->employee_id,
                    $leave->id,
                );
            }

            $row = $this->toArray($leave);
            $row['overlaps'] = $peers;
            $row['has_overlap'] = $peers !== [];

            return $row;
        });

        return [
            'items' => $items->all(),
            'counts' => [
                'all' => EmployeeLeave::query()->count(),
                'pending' => EmployeeLeave::query()->where('status', EmployeeLeave::STATUS_PENDING)->count(),
                'approved' => EmployeeLeave::query()->where('status', EmployeeLeave::STATUS_APPROVED)->count(),
                'rejected' => EmployeeLeave::query()->where('status', EmployeeLeave::STATUS_REJECTED)->count(),
                'cancelled' => EmployeeLeave::query()->where('status', EmployeeLeave::STATUS_CANCELLED)->count(),
            ],
        ];
    }

    public function toArray(EmployeeLeave $leave): array
    {
        return [
            'id' => $leave->id,
            'employee' => [
                'id' => $leave->employee?->id,
                'full_name' => $leave->employee?->full_name,
                'employee_code' => $leave->employee?->employee_code,
            ],
            'leave_type' => $leave->leave_type,
            'leave_type_label' => $leave->typeLabel(),
            'start_date' => $leave->start_date?->toDateString(),
            'end_date' => $leave->end_date?->toDateString(),
            'start_date_label' => $leave->start_date?->locale('id')->translatedFormat('j M Y'),
            'end_date_label' => $leave->end_date?->locale('id')->translatedFormat('j M Y'),
            'days_count' => $leave->days_count,
            'reason' => $leave->reason,
            'status' => $leave->status,
            'status_label' => $leave->statusLabel(),
            'requested_by' => $leave->requester?->name,
            'reviewed_by' => $leave->reviewer?->name,
            'reviewed_at' => $leave->reviewed_at?->timezone(AppTimezone::display())->format('Y-m-d H:i'),
            'review_notes' => $leave->review_notes,
            'created_at' => $leave->created_at?->timezone(AppTimezone::display())->format('Y-m-d H:i'),
        ];
    }

    /**
     * @param  array<int, int>  $daysByYear
     */
    private function assertQuotaAvailable(Employee $employee, array $daysByYear, ?string $ignoreLeaveId = null): void
    {
        foreach ($daysByYear as $year => $days) {
            $balance = $this->ensureBalance($employee, (int) $year);
            if ($balance->status === EmployeeLeaveBalance::STATUS_CLOSED) {
                throw new RuntimeException("Jatah cuti tahun {$year} sudah ditutup (hangus/diuangkan).");
            }

            $pending = $this->pendingTahunanDays($employee->id, (int) $year, $ignoreLeaveId);
            $available = $balance->remainingDays() - $pending;

            if ($available < $days) {
                throw new RuntimeException(
                    "Jatah cuti tahunan {$year} tidak cukup. Sisa efektif {$available} hari (termasuk pengajuan menunggu), dibutuhkan {$days} hari."
                );
            }
        }
    }

    private function pendingTahunanDays(string $employeeId, int $year, ?string $ignoreLeaveId = null): int
    {
        $leaves = EmployeeLeave::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type', EmployeeLeave::TYPE_TAHUNAN)
            ->where('status', EmployeeLeave::STATUS_PENDING)
            ->when($ignoreLeaveId, fn ($q) => $q->where('id', '!=', $ignoreLeaveId))
            ->whereDate('start_date', '<=', "{$year}-12-31")
            ->whereDate('end_date', '>=', "{$year}-01-01")
            ->get(['start_date', 'end_date']);

        $total = 0;
        foreach ($leaves as $leave) {
            $byYear = $this->daysByYear($leave->start_date, $leave->end_date);
            $total += (int) ($byYear[$year] ?? 0);
        }

        return $total;
    }

    /**
     * @param  array<int, int>  $daysByYear
     */
    private function applyQuotaUsage(Employee $employee, array $daysByYear, int $direction): void
    {
        foreach ($daysByYear as $year => $days) {
            $balance = $this->ensureBalance($employee, (int) $year);
            $next = $balance->used_days + ($direction * $days);
            if ($next < 0) {
                $next = 0;
            }
            $balance->update(['used_days' => $next]);
        }
    }

    private function assertNoOverlap(string $employeeId, string $startDate, string $endDate, ?string $ignoreId = null): void
    {
        $overlap = EmployeeLeave::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [EmployeeLeave::STATUS_PENDING, EmployeeLeave::STATUS_APPROVED])
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($overlap) {
            throw new RuntimeException('Sudah ada pengajuan/cuti lain yang bentrok di tanggal tersebut.');
        }
    }
}
