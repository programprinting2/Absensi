<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_calendar_entries', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropUnique('shift_cal_date_sched_group_uq');
        });

        Schema::table('shift_calendar_entries', function (Blueprint $table) {
            $table->uuid('group_id')->nullable()->change();
            $table->foreignUuid('employee_id')->nullable()->after('group_id')->constrained('employees')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('shift_groups')->cascadeOnDelete();
        });

        // Konversi entry solo group → penempatan langsung per karyawan
        $soloEntries = DB::table('shift_calendar_entries as e')
            ->join('shift_groups as g', 'g.id', '=', 'e.group_id')
            ->where('g.is_solo', true)
            ->select('e.id', 'e.group_id', 'e.work_date')
            ->get();

        foreach ($soloEntries as $row) {
            $member = DB::table('shift_group_members')
                ->where('group_id', $row->group_id)
                ->where(function ($q) use ($row) {
                    $q->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $row->work_date);
                })
                ->whereDate('effective_from', '<=', $row->work_date)
                ->orderByDesc('effective_from')
                ->first();

            if ($member) {
                DB::table('shift_calendar_entries')
                    ->where('id', $row->id)
                    ->update([
                        'employee_id' => $member->employee_id,
                        'group_id' => null,
                    ]);
            }
        }

        // Bersihkan solo group lama
        $soloGroupIds = DB::table('shift_groups')->where('is_solo', true)->pluck('id');
        if ($soloGroupIds->isNotEmpty()) {
            $unassignedId = DB::table('shift_groups')->where('is_system_unassigned', true)->value('id');
            $today = now()->toDateString();
            $yesterday = \Illuminate\Support\Carbon::parse($today)->subDay()->toDateString();

            foreach ($soloGroupIds as $groupId) {
                $openMembers = DB::table('shift_group_members')
                    ->where('group_id', $groupId)
                    ->whereNull('effective_to')
                    ->get();

                foreach ($openMembers as $member) {
                    DB::table('shift_group_members')->where('id', $member->id)->update([
                        'effective_to' => $yesterday,
                    ]);

                    if ($unassignedId) {
                        $alreadyUnassigned = DB::table('shift_group_members')
                            ->where('group_id', $unassignedId)
                            ->where('employee_id', $member->employee_id)
                            ->whereNull('effective_to')
                            ->exists();

                        if (! $alreadyUnassigned) {
                            DB::table('shift_group_members')->insert([
                                'id' => (string) \Illuminate\Support\Str::uuid(),
                                'group_id' => $unassignedId,
                                'employee_id' => $member->employee_id,
                                'effective_from' => $today,
                                'effective_to' => null,
                                'created_at' => now(),
                            ]);
                        }
                    }
                }

                DB::table('shift_calendar_entries')->where('group_id', $groupId)->delete();
                DB::table('shift_group_members')->where('group_id', $groupId)->delete();
                DB::table('shift_groups')->where('id', $groupId)->delete();
            }
        }

        DB::statement('CREATE UNIQUE INDEX shift_cal_date_sched_group_uq ON shift_calendar_entries (work_date, work_schedule_id, group_id) WHERE group_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX shift_cal_date_sched_emp_uq ON shift_calendar_entries (work_date, work_schedule_id, employee_id) WHERE employee_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS shift_cal_date_sched_emp_uq');
        DB::statement('DROP INDEX IF EXISTS shift_cal_date_sched_group_uq');

        Schema::table('shift_calendar_entries', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
            $table->dropForeign(['group_id']);
        });

        Schema::table('shift_calendar_entries', function (Blueprint $table) {
            $table->uuid('group_id')->nullable(false)->change();
            $table->foreign('group_id')->references('id')->on('shift_groups')->cascadeOnDelete();
            $table->unique(['work_date', 'work_schedule_id', 'group_id'], 'shift_cal_date_sched_group_uq');
        });
    }
};
