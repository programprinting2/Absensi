<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('color', 16)->default('#64748b');
            $table->boolean('is_system_unassigned')->default(false);
            $table->boolean('is_solo')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('shift_group_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('shift_groups')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['employee_id', 'effective_from', 'effective_to']);
            $table->index(['group_id', 'effective_from']);
        });

        Schema::create('shift_calendar_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('work_date');
            $table->foreignUuid('work_schedule_id')->constrained('work_schedules')->restrictOnDelete();
            $table->foreignUuid('group_id')->constrained('shift_groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['work_date', 'work_schedule_id', 'group_id'], 'shift_cal_date_sched_group_uq');
            $table->index(['work_date', 'work_schedule_id']);
            $table->index('group_id');
        });

        Schema::create('shift_day_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('work_date')->unique();
            $table->boolean('is_company_holiday')->default(false);
            // routine = Libur (hari) dari template; event = Libur (tanggal) sekali
            $table->string('holiday_kind', 16)->nullable();
            $table->unsignedSmallInteger('work_duration_minutes')->nullable();
            $table->unsignedSmallInteger('break_duration_minutes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('shift_employee_libur', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->string('source', 24)->default('pattern'); // pattern | adhoc
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['employee_id', 'work_date']);
            $table->index('work_date');
        });

        Schema::create('shift_employee_shift_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignUuid('work_schedule_id')->constrained('work_schedules')->restrictOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['employee_id', 'work_date'], 'shift_emp_override_date_uq');
            $table->index('work_date');
        });

        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignUuid('to_work_schedule_id')->constrained('work_schedules')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->string('status', 24)->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['employee_id', 'status']);
            $table->index('work_date');
        });

        Schema::create('shift_schedule_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });

        // Seed Unassigned system group
        $unassignedId = (string) Str::uuid();
        DB::table('shift_groups')->insert([
            'id' => $unassignedId,
            'name' => 'Unassigned',
            'color' => '#94a3b8',
            'is_system_unassigned' => true,
            'is_solo' => false,
            'sort_order' => 9999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Place active employees into Unassigned (open membership)
        $today = now()->toDateString();
        $employees = DB::table('employees')->where('is_active', true)->pluck('id');
        foreach ($employees as $employeeId) {
            DB::table('shift_group_members')->insert([
                'id' => (string) Str::uuid(),
                'group_id' => $unassignedId,
                'employee_id' => $employeeId,
                'effective_from' => $today,
                'effective_to' => null,
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_schedule_templates');
        Schema::dropIfExists('shift_swap_requests');
        Schema::dropIfExists('shift_employee_shift_overrides');
        Schema::dropIfExists('shift_employee_libur');
        Schema::dropIfExists('shift_day_settings');
        Schema::dropIfExists('shift_calendar_entries');
        Schema::dropIfExists('shift_group_members');
        Schema::dropIfExists('shift_groups');
    }
};
