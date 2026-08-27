<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $table) {
            $table->boolean('crosses_midnight')->default(false)->after('late_after_time');
        });

        // Tandai shift malam jika jam pulang < jam masuk.
        foreach (DB::table('work_schedules')->get() as $row) {
            $in = substr((string) $row->clock_in_time, 0, 5);
            $out = substr((string) ($row->clock_out_time ?? ''), 0, 5);
            if ($in !== '' && $out !== '' && $out < $in) {
                DB::table('work_schedules')->where('id', $row->id)->update(['crosses_midnight' => true]);
            }
        }

        Schema::create('employee_shift_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('work_schedule_id')->constrained('work_schedules')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['employee_id', 'effective_from']);
            $table->index(['work_schedule_id']);
        });

        // Migrasi: assign semua karyawan aktif ke jadwal default (is_active).
        $defaultId = DB::table('work_schedules')->where('is_active', true)->value('id')
            ?? DB::table('work_schedules')->orderBy('created_at')->value('id');

        if ($defaultId) {
            $now = now();
            $from = now()->subYears(5)->toDateString();
            $rows = DB::table('employees')->pluck('id')->map(fn ($id) => [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'employee_id' => $id,
                'work_schedule_id' => $defaultId,
                'effective_from' => $from,
                'effective_to' => null,
                'created_at' => $now,
            ])->all();

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('employee_shift_assignments')->insert($chunk);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_shift_assignments');

        Schema::table('work_schedules', function (Blueprint $table) {
            $table->dropColumn('crosses_midnight');
        });
    }
};
