<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ShiftCalendarEntry;
use App\Models\ShiftGroup;
use App\Models\ShiftGroupMember;
use App\Support\AppTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ShiftGroupService
{
    public function ensureUnassigned(): ShiftGroup
    {
        $group = ShiftGroup::unassigned();
        if ($group) {
            return $group;
        }

        return ShiftGroup::query()->create([
            'name' => 'Unassigned',
            'color' => '#94a3b8',
            'is_system_unassigned' => true,
            'is_solo' => false,
            'sort_order' => 9999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function groupForEmployeeOnDate(Employee|string $employee, Carbon|string $date): ?ShiftGroup
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $day = $this->toDateString($date);

        $member = ShiftGroupMember::query()
            ->with('group')
            ->where('employee_id', $employeeId)
            ->activeOn($day)
            ->orderByDesc('effective_from')
            ->first();

        return $member?->group;
    }

    /**
     * Pindah karyawan ke group lain mulai tanggal $from (default hari ini).
     * Membership lama ditutup; berlaku future. Histori past tetap.
     */
    public function moveEmployee(
        Employee|string $employee,
        ShiftGroup|string $toGroup,
        ?string $effectiveFrom = null,
    ): ShiftGroupMember {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $groupId = $toGroup instanceof ShiftGroup ? $toGroup->id : $toGroup;
        $from = $effectiveFrom ?? AppTimezone::nowDisplay()->toDateString();

        $target = ShiftGroup::query()->findOrFail($groupId);
        if ($target->is_system_unassigned === false && $target->is_solo) {
            // solo group must only hold one employee — OK if same person
        }

        return DB::transaction(function () use ($employeeId, $groupId, $from, $target) {
            $open = ShiftGroupMember::query()
                ->where('employee_id', $employeeId)
                ->whereNull('effective_to')
                ->orderByDesc('effective_from')
                ->get();

            foreach ($open as $row) {
                if ((string) $row->group_id === (string) $groupId
                    && $row->effective_from->toDateString() <= $from) {
                    return $row->load('group');
                }

                $end = Carbon::parse($from, AppTimezone::display())->subDay()->toDateString();
                if ($end < $row->effective_from->toDateString()) {
                    $row->delete();
                } else {
                    $row->update(['effective_to' => $end]);
                }
            }

            if ($target->is_solo) {
                $other = ShiftGroupMember::query()
                    ->where('group_id', $groupId)
                    ->whereNull('effective_to')
                    ->where('employee_id', '!=', $employeeId)
                    ->exists();
                if ($other) {
                    throw new RuntimeException('Group solo sudah berisi karyawan lain.');
                }
            }

            $member = ShiftGroupMember::query()->create([
                'group_id' => $groupId,
                'employee_id' => $employeeId,
                'effective_from' => $from,
                'effective_to' => null,
                'created_at' => now(),
            ])->load('group');

            if ($target->is_system_unassigned) {
                ShiftCalendarEntry::query()->where('employee_id', $employeeId)->delete();
                $this->purgeSoloGroupsForEmployee($employeeId);
            }

            ActivityLogger::normal(
                'Pindah group karyawan ke '.$target->name.' (mulai '.$from.')',
                'shift.group.move',
                ['employee_id' => $employeeId, 'group_id' => $groupId, 'effective_from' => $from],
            );

            return $member;
        });
    }

    /**
     * Resign / nonaktif: tutup membership aktif ke Unassigned (histori past tetap).
     * Nama di pool/kalender disembunyikan via filter is_active.
     */
    public function deactivateEmployee(Employee|string $employee, ?string $effectiveFrom = null): void
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $from = $effectiveFrom ?? AppTimezone::nowDisplay()->toDateString();
        $unassigned = $this->ensureUnassigned();

        $current = $this->groupForEmployeeOnDate($employeeId, $from);
        if ($current && $current->is_system_unassigned) {
            return;
        }

        $this->moveEmployee($employeeId, $unassigned, $from);
    }

    public function createGroup(string $name, string $color = '#3b82f6', int $sortOrder = 0): ShiftGroup
    {
        $name = trim($name);
        if ($name === '' || mb_strtolower($name) === 'unassigned') {
            throw new InvalidArgumentException('Nama group tidak valid.');
        }

        $group = ShiftGroup::query()->create([
            'name' => $name,
            'color' => $color,
            'is_system_unassigned' => false,
            'is_solo' => false,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ActivityLogger::normal(
            'Membuat group "'.$name.'"',
            'shift.group.create',
            ['group_id' => $group->id],
        );

        return $group;
    }

    /**
     * Solo group otomatis untuk 1 karyawan (tim kecil drag langsung ke kalender).
     */
    public function ensureSoloGroupFor(Employee $employee): ShiftGroup
    {
        $existing = ShiftGroup::query()
            ->where('is_solo', true)
            ->where('name', $employee->full_name)
            ->first();

        if ($existing) {
            $this->moveEmployee($employee, $existing);

            return $existing;
        }

        $group = ShiftGroup::query()->create([
            'name' => $employee->full_name,
            'color' => $this->colorFromName($employee->full_name),
            'is_system_unassigned' => false,
            'is_solo' => true,
            'sort_order' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->moveEmployee($employee, $group);

        return $group;
    }

    public function deleteGroup(ShiftGroup|string $group): void
    {
        $model = $group instanceof ShiftGroup ? $group : ShiftGroup::query()->findOrFail($group);
        if ($model->is_system_unassigned) {
            throw new RuntimeException('Group Unassigned tidak bisa dihapus.');
        }

        $unassigned = $this->ensureUnassigned();
        $today = AppTimezone::nowDisplay()->toDateString();

        DB::transaction(function () use ($model, $unassigned, $today) {
            $openMembers = ShiftGroupMember::query()
                ->where('group_id', $model->id)
                ->whereNull('effective_to')
                ->get();

            foreach ($openMembers as $member) {
                $this->moveEmployee($member->employee_id, $unassigned, $today);
            }

            $name = $model->name;
            $id = $model->id;
            $model->delete();

            ActivityLogger::normal(
                'Menghapus group "'.$name.'"',
                'shift.group.delete',
                ['group_id' => $id],
            );
        });
    }

    /**
     * @return list<array{id: string, full_name: string, employee_code: string|null}>
     */
    public function membersOnDate(ShiftGroup|string $group, Carbon|string $date): array
    {
        $groupId = $group instanceof ShiftGroup ? $group->id : $group;
        $day = $this->toDateString($date);

        return ShiftGroupMember::query()
            ->with('employee')
            ->where('group_id', $groupId)
            ->activeOn($day)
            ->get()
            ->filter(fn (ShiftGroupMember $m) => $m->employee?->is_active)
            ->map(fn (ShiftGroupMember $m) => [
                'id' => (string) $m->employee_id,
                'full_name' => $m->employee->full_name,
                'employee_code' => $m->employee->employee_code,
            ])
            ->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Anggota group untuk tampilan kalender. Hari lampau memakai roster saat ini agar konsisten dengan blok mendatang.
     *
     * @return list<array{id: string, full_name: string, employee_code: string|null}>
     */
    public function membersForCalendarDate(ShiftGroup|string $group, Carbon|string $date): array
    {
        $day = $this->toDateString($date);
        $today = AppTimezone::nowDisplay()->toDateString();
        $reference = $day < $today ? $today : $day;

        return $this->membersOnDate($group, $reference);
    }

    private function toDateString(Carbon|string $date): string
    {
        return $date instanceof Carbon
            ? $date->copy()->timezone(AppTimezone::display())->toDateString()
            : Carbon::parse($date, AppTimezone::display())->toDateString();
    }

    public function colorForName(string $name): string
    {
        return $this->colorFromName($name);
    }

    /**
     * Hapus solo group lama milik karyawan (setelah migrasi ke penempatan langsung).
     */
    public function purgeSoloGroupsForEmployee(string $employeeId): void
    {
        $soloGroupIds = ShiftGroupMember::query()
            ->where('employee_id', $employeeId)
            ->whereNull('effective_to')
            ->whereHas('group', fn ($q) => $q->where('is_solo', true))
            ->pluck('group_id');

        if ($soloGroupIds->isEmpty()) {
            return;
        }

        ShiftCalendarEntry::query()->whereIn('group_id', $soloGroupIds)->delete();
        ShiftGroupMember::query()->whereIn('group_id', $soloGroupIds)->delete();
        ShiftGroup::query()->whereIn('id', $soloGroupIds)->where('is_solo', true)->delete();
    }

    private function colorFromName(string $name): string
    {
        $palette = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6', '#3b82f6', '#8b5cf6', '#ec4899'];
        $idx = abs(crc32($name)) % count($palette);

        return $palette[$idx];
    }
}
